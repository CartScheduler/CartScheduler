<?php

use App\Enums\NotificationType;
use App\Models\Location;
use App\Models\NotificationLog;
use App\Models\Shift;
use App\Models\ShiftUser;
use App\Models\User;
use App\Notifications\ShiftAssignmentsReleasedNotification;
use App\Notifications\UserAccountCreatedNotification;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->enabled()->adminRoleUser()->create();
    $settings = app(GeneralSettings::class);
    $settings->allowedSettingsUsers = [$this->admin->getKey()];
    $settings->save();
});

/**
 * A failed shift email for a volunteer with one assignment on the given date.
 *
 * @return array{0: User, 1: ShiftUser, 2: NotificationLog}
 */
function failedShiftEmailFor(string $shiftDate): array
{
    $user = User::factory()->userRoleUser()->enabled()->create();
    $shift = Shift::factory()->everyDay9am()->for(Location::factory())->create();
    $user->attachShiftOnDate($shift, $shiftDate);
    $assignment = ShiftUser::query()->where('user_id', $user->id)->firstOrFail();

    $log = NotificationLog::factory()->forUser($user)->ofType(NotificationType::ShiftAssignmentsReleased)->failed()->create();
    $log->shiftUsers()->attach($assignment);

    return [$user, $assignment, $log];
}

test('a failed welcome email can be sent again', function () {
    Notification::fake();
    $user = User::factory()->enabled()->create(['password' => null]);
    $log = NotificationLog::factory()->forUser($user)->failed()->create();

    $this->actingAs($this->admin)
        ->from('/admin/notifications')
        ->post("/admin/notifications/{$log->id}/resend")
        ->assertRedirect('/admin/notifications')
        ->assertSessionHas('flash.bannerStyle', 'success');

    Notification::assertSentTo($user, UserAccountCreatedNotification::class);
});

test('a failed shift email is sent again for its upcoming assignments', function () {
    Notification::fake();
    [$user, $assignment, $log] = failedShiftEmailFor(now()->addDays(3)->toDateString());

    $this->actingAs($this->admin)
        ->post("/admin/notifications/{$log->id}/resend")
        ->assertSessionHas('flash.bannerStyle', 'success');

    Notification::assertSentTo($user, ShiftAssignmentsReleasedNotification::class,
        fn (ShiftAssignmentsReleasedNotification $notification) => $notification->shiftUserIds === [$assignment->id]
            && $notification->period['start'] === now()->addDays(3)->format('j F Y'));
});

test('emails that cannot be rebuilt are not sent again', function (NotificationLog $log) {
    Notification::fake();

    $this->actingAs($this->admin)
        ->post("/admin/notifications/{$log->id}/resend")
        ->assertSessionHas('flash.bannerStyle', 'danger');

    Notification::assertNothingSent();
})->with([
    'already sent' => fn () => NotificationLog::factory()
        ->forUser(User::factory()->enabled()->create(['password' => null]))->create(),
    'welcome email for someone who has logged in' => fn () => NotificationLog::factory()
        ->forUser(User::factory()->enabled()->create())->failed()->create(),
    'password reset' => fn () => NotificationLog::factory()
        ->forUser(User::factory()->enabled()->create())->ofType(NotificationType::PasswordReset)->failed()->create(),
    'shift email for past shifts' => fn () => failedShiftEmailFor(now()->subDays(2)->toDateString())[2],
    'no recipient record' => fn () => NotificationLog::factory()->failed()->create(),
]);

test('a failed email that was later sent successfully is not sent again', function () {
    Notification::fake();
    $user = User::factory()->enabled()->create(['password' => null]);
    $log = NotificationLog::factory()->forUser($user)->failed()->create();
    NotificationLog::factory()->forUser($user)->create(['sent_at' => now()->addMinute()]);

    $this->actingAs($this->admin)
        ->post("/admin/notifications/{$log->id}/resend")
        ->assertSessionHas('flash.bannerStyle', 'danger');

    Notification::assertNothingSent();
});

test('the log shows later attempts and whether a failed email can be sent again', function () {
    $user = User::factory()->enabled()->create(['password' => null]);
    $first = NotificationLog::factory()->forUser($user)->failed()->create(['attempts' => 5]);
    NotificationLog::factory()->forUser($user)->failed()->create();
    $resendable = NotificationLog::query()->latest('id')->first();

    $other = User::factory()->enabled()->create(['password' => null]);
    $sentLater = NotificationLog::factory()->forUser($other)->failed()->create();
    NotificationLog::factory()->forUser($other)->create();

    $this->actingAs($this->admin)
        ->get('/admin/notifications?status=failed')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('logs.data', function ($logs) use ($first, $resendable, $sentLater) {
                $logs = collect($logs)->keyBy('id');

                return $logs[$first->id]['later_attempts'] === 1
                    && $logs[$first->id]['later_sent_at'] === null
                    && $logs[$first->id]['can_resend'] === true
                    && $logs[$resendable->id]['later_attempts'] === 0
                    && $logs[$resendable->id]['can_resend'] === true
                    && $logs[$sentLater->id]['later_sent_at'] !== null
                    && $logs[$sentLater->id]['can_resend'] === false;
            }));
});

test('shift emails only count later attempts about the same assignments', function () {
    [$user, , $log] = failedShiftEmailFor(now()->addDays(3)->toDateString());
    NotificationLog::factory()->forUser($user)->ofType(NotificationType::ShiftAssignmentsReleased)->create();

    $this->actingAs($this->admin)
        ->get('/admin/notifications?status=failed')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('logs.data.0.id', $log->id)
            ->where('logs.data.0.later_attempts', 0)
            ->where('logs.data.0.can_resend', true));
});

test('a resend that fails straight away is reported in plain english', function () {
    $user = User::factory()->enabled()->create(['password' => null]);
    $log = NotificationLog::factory()->forUser($user)->failed()->create();
    config(['queue.default' => 'sync', 'mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'unreachable.invalid', 'mail.mailers.smtp.port' => 2525]);

    $this->actingAs($this->admin)
        ->post("/admin/notifications/{$log->id}/resend")
        ->assertSessionHas('flash.bannerStyle', 'danger')
        ->assertSessionHas('flash.banner', fn (string $banner) => str_starts_with($banner, 'Sending failed again.'));
});

test('admins not on the settings list cannot resend', function () {
    $log = NotificationLog::factory()->failed()->create();
    $otherAdmin = User::factory()->enabled()->adminRoleUser()->create();

    $this->actingAs($otherAdmin)->post("/admin/notifications/{$log->id}/resend")->assertNotFound();
});
