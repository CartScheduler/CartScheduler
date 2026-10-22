<?php

use App\Actions\NotifyVolunteersOfReleasedShifts;
use App\Console\Commands\NotifyReleasedShiftAssignmentsCommand;
use App\Enums\DBPeriod;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Mail\ShiftAssignmentsReleased;
use App\Models\Location;
use App\Models\NotificationLog;
use App\Models\Shift;
use App\Models\ShiftUser;
use App\Models\User;
use App\Notifications\ShiftAssignmentsReleasedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Traits\SetConfig;

uses(RefreshDatabase::class);

uses(SetConfig::class);

expect()->extend('toContainMail', function (Closure $matches) {
    expect($this->value->contains($matches))->toBeTrue('No matching Shift Assignments Released email was sent.');

    return $this;
});

test('weekly release sends one email per user for newly opened slice only', function () {
    Notification::fake();
    /*
     * February 2023
     * Mo Tu We Th Fr Sa Su
     *        1  2  3  4  5
     *  6  7  8  9 10 11 12
     * 13 14 15 16 17 18 19 <- newly opened after Mon 6 at 12:30
     * 20 21 22 23 24 25 26
     */
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

    $userWithNewShift = User::factory()->userRoleUser()->enabled()->create();
    $userWithOldShift = User::factory()->userRoleUser()->enabled()->create();
    $userWithNoShift = User::factory()->userRoleUser()->enabled()->create();

    $location = Location::factory()->create(['name' => 'Town Square']);
    $shift = Shift::factory()->everyDay9am()->for($location)->create();

    $userWithOldShift->attachShiftOnDate($shift, '2023-02-08');
    $userWithNewShift->attachShiftOnDate($shift, '2023-02-14');
    $userWithNewShift->attachShiftOnDate($shift, '2023-02-16');

    $this->travelTo('2023-02-06 12:29:59');
    $this->assertSame(
        ['users_notified' => 0, 'assignments_notified' => 0],
        app(NotifyVolunteersOfReleasedShifts::class)->execute()
    );
    expect(releasedMails())->toBeEmpty();

    $this->travelTo('2023-02-06 12:30:01');
    $result = app(NotifyVolunteersOfReleasedShifts::class)->execute();

    expect($result)->toBe(['users_notified' => 1, 'assignments_notified' => 2]);
    expect(releasedMails())->toHaveCount(1);
    expect(releasedMails())->toContainMail(function (ShiftAssignmentsReleased $mail) use ($userWithNewShift) {
        return $mail->hasTo($userWithNewShift->email)
            && $mail->shifts->count() === 2
            && $mail->period === [
                'start' => '13 February 2023',
                'end' => '19 February 2023',
                'label' => '13 February 2023 – 19 February 2023',
            ];
    });
    expect(releasedMails())->not->toContainMail(fn (ShiftAssignmentsReleased $mail) => $mail->hasTo($userWithOldShift->email));
    expect(releasedMails())->not->toContainMail(fn (ShiftAssignmentsReleased $mail) => $mail->hasTo($userWithNoShift->email));
});

test('a second run does not notify the same assignments again', function () {
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

    $user = User::factory()->userRoleUser()->enabled()->create();
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();
    $user->attachShiftOnDate($shift, '2023-02-14');
    $user->attachShiftOnDate($shift, '2023-02-16');

    $this->travelTo('2023-02-06 12:30:01');

    expect(app(NotifyVolunteersOfReleasedShifts::class)->execute())
        ->toBe(['users_notified' => 1, 'assignments_notified' => 2])
        ->and(app(NotifyVolunteersOfReleasedShifts::class)->execute())
        ->toBe(['users_notified' => 0, 'assignments_notified' => 0]);

    $log = NotificationLog::query()->where('type', NotificationType::ShiftAssignmentsReleased->value)->sole();
    expect($log->status)->toBe(NotificationStatus::Sent)
        ->and($log->notifiable_id)->toBe($user->id)
        ->and($log->subject)->toContain('Shift Assignments')
        ->and($log->shiftUsers)->toHaveCount(2);
});

test('monthly release notifies assignments in newly opened month', function () {
    Notification::fake();
    $this->setConfig(1, DBPeriod::Month, false, null, '09:00');

    $user = User::factory()->userRoleUser()->enabled()->create();
    $location = Location::factory()->create(['name' => 'Hall']);
    $shift = Shift::factory()->everyDay9am()->for($location)->create();

    $user->attachShiftOnDate($shift, '2023-01-15');
    $user->attachShiftOnDate($shift, '2023-02-10');

    $this->travelTo('2023-01-01 08:59:59');
    $this->assertSame(
        ['users_notified' => 0, 'assignments_notified' => 0],
        app(NotifyVolunteersOfReleasedShifts::class)->execute()
    );

    $this->travelTo('2023-01-01 09:00:01');
    $result = app(NotifyVolunteersOfReleasedShifts::class)->execute();

    expect($result)->toBe(['users_notified' => 1, 'assignments_notified' => 1]);
    expect(releasedMails())->toContainMail(fn (ShiftAssignmentsReleased $mail) => $mail->shifts->count() === 1
        && str_contains($mail->shifts->first()['date'], '10 February 2023'));
});

test('daily release notifies assignments on newly opened day', function () {
    Notification::fake();
    $this->setConfig(1, DBPeriod::Week, true, 'MON', '08:00');

    $user = User::factory()->userRoleUser()->enabled()->create();
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();

    $user->attachShiftOnDate($shift, '2023-01-09');
    $user->attachShiftOnDate($shift, '2023-01-08');

    $this->travelTo('2023-01-03 07:59:59');
    $this->assertSame(
        ['users_notified' => 0, 'assignments_notified' => 0],
        app(NotifyVolunteersOfReleasedShifts::class)->execute()
    );

    $this->travelTo('2023-01-03 08:00:01');
    $result = app(NotifyVolunteersOfReleasedShifts::class)->execute();

    expect($result)->toBe(['users_notified' => 1, 'assignments_notified' => 1]);
    expect(releasedMails())->toContainMail(fn (ShiftAssignmentsReleased $mail) => $mail->shifts->count() === 1
        && str_contains($mail->shifts->first()['date'], '9 January 2023'));
});

test('email includes other volunteers on the same shift', function () {
    Notification::fake();
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

    $recipient = User::factory()->userRoleUser()->enabled()->create([
        'name' => 'Alice Recipient',
        'mobile_phone' => '0411111111',
    ]);
    $partner = User::factory()->userRoleUser()->enabled()->create([
        'name' => 'Bob Partner',
        'mobile_phone' => '0422222222',
    ]);
    $solo = User::factory()->userRoleUser()->enabled()->create(['name' => 'Carol Solo']);
    $location = Location::factory()->create(['name' => 'Town Square']);
    $shift = Shift::factory()->everyDay9am()->for($location)->create();

    $recipient->attachShiftOnDate($shift, '2023-02-14');
    $partner->attachShiftOnDate($shift, '2023-02-14');
    $solo->attachShiftOnDate($shift, '2023-02-16');

    $this->travelTo('2023-02-06 12:30:01');

    app(NotifyVolunteersOfReleasedShifts::class)->execute();

    expect(releasedMails())->toContainMail(function (ShiftAssignmentsReleased $mail) use ($recipient) {
        return $mail->hasTo($recipient->email)
            && $mail->shifts->count() === 1
            && $mail->shifts->first()['other_volunteers'] === [
                ['name' => 'Bob Partner', 'mobile_phone' => '0422 222 222'],
            ];
    });
    expect(releasedMails())->toContainMail(function (ShiftAssignmentsReleased $mail) use ($partner) {
        return $mail->hasTo($partner->email)
            && $mail->shifts->first()['other_volunteers'] === [
                ['name' => 'Alice Recipient', 'mobile_phone' => '0411 111 111'],
            ];
    });
    expect(releasedMails())->toContainMail(function (ShiftAssignmentsReleased $mail) use ($solo) {
        return $mail->hasTo($solo->email)
            && $mail->shifts->first()['other_volunteers'] === [];
    });
});

test('email includes location notes and map url', function () {
    Notification::fake();
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

    $user = User::factory()->userRoleUser()->enabled()->create();
    $mapped = Location::factory()->create([
        'name' => 'Town Square',
        'description' => '<p>Meet at the <strong>north</strong> entrance.</p>',
        'latitude' => '-37.8136',
        'longitude' => '144.9631',
    ]);
    $unmapped = Location::factory()->create([
        'name' => 'Hall',
        'description' => '<p></p>',
        'latitude' => null,
        'longitude' => null,
    ]);
    $mappedShift = Shift::factory()->everyDay9am()->for($mapped)->create();
    $unmappedShift = Shift::factory()->everyDay9am()->for($unmapped)->create();

    $user->attachShiftOnDate($mappedShift, '2023-02-14');
    $user->attachShiftOnDate($unmappedShift, '2023-02-16');

    $mapped->refresh();

    $this->travelTo('2023-02-06 12:30:01');

    app(NotifyVolunteersOfReleasedShifts::class)->execute();

    expect(releasedMails())->toContainMail(function (ShiftAssignmentsReleased $mail) use ($user, $mapped) {
        $shifts = $mail->shifts->values();

        return $mail->hasTo($user->email)
            && $shifts[0]['location'] === 'Town Square'
            && $shifts[0]['location_description'] === 'Meet at the north entrance.'
            && $shifts[0]['location_map_url'] === 'https://www.google.com/maps?q='.$mapped->latitude.','.$mapped->longitude
            && $shifts[1]['location'] === 'Hall'
            && $shifts[1]['location_description'] === ''
            && $shifts[1]['location_map_url'] === null;
    });
});

test('force flag allows catch up outside release moment', function () {
    Notification::fake();
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

    $user = User::factory()->userRoleUser()->enabled()->create();
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();
    $user->attachShiftOnDate($shift, '2023-02-14');

    $this->travelTo('2023-02-07 10:00:00');
    $this->assertSame(
        ['users_notified' => 0, 'assignments_notified' => 0],
        app(NotifyVolunteersOfReleasedShifts::class)->execute()
    );

    $result = app(NotifyVolunteersOfReleasedShifts::class)->execute(force: true);

    expect($result)->toBe(['users_notified' => 1, 'assignments_notified' => 1]);
    expect(releasedMails())->toHaveCount(1);
});

test('skips disabled users and already tracked assignments', function () {
    Notification::fake();
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

    $enabledUser = User::factory()->userRoleUser()->enabled()->create();
    $disabledUser = User::factory()->userRoleUser()->create(['is_enabled' => false]);
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();

    $enabledUser->attachShiftOnDate($shift, '2023-02-14');
    $disabledUser->attachShiftOnDate($shift, '2023-02-15');

    $tracked = ShiftUser::query()->where('user_id', $enabledUser->id)->first();
    NotificationLog::factory()
        ->forUser($enabledUser)
        ->ofType(NotificationType::ShiftAssignmentsReleased)
        ->create()
        ->shiftUsers()
        ->attach($tracked);

    $this->travelTo('2023-02-06 12:30:01');

    $this->assertSame(
        ['users_notified' => 0, 'assignments_notified' => 0],
        app(NotifyVolunteersOfReleasedShifts::class)->execute()
    );
    expect(releasedMails())->toBeEmpty();
});

test('artisan command outputs summary', function () {
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');
    Config::set('cart-scheduler.shift_assignment_notifications_enabled', true);

    $user = User::factory()->userRoleUser()->enabled()->create();
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();
    $user->attachShiftOnDate($shift, '2023-02-14');

    $this->travelTo('2023-02-06 12:30:01');

    $this->artisan(NotifyReleasedShiftAssignmentsCommand::class)
        ->expectsOutput('Queued notifications for 1 volunteer(s) covering 1 assignment(s).')
        ->assertSuccessful();
});

test('artisan command respects disabled flag', function () {
    Notification::fake();
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');
    Config::set('cart-scheduler.shift_assignment_notifications_enabled', false);

    $user = User::factory()->userRoleUser()->enabled()->create();
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();
    $user->attachShiftOnDate($shift, '2023-02-14');

    $this->travelTo('2023-02-06 12:30:01');

    $this->artisan(NotifyReleasedShiftAssignmentsCommand::class)
        ->expectsOutput('Shift assignment notifications are disabled (CA_SHIFT_ASSIGNMENT_NOTIFICATIONS_ENABLED).')
        ->assertSuccessful();
    expect(releasedMails())->toBeEmpty();
});

test('a failed earlier notification does not stop the assignment being notified again', function () {
    Notification::fake();
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

    $user = User::factory()->userRoleUser()->enabled()->create();
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();
    $user->attachShiftOnDate($shift, '2023-02-14');

    $assignment = ShiftUser::query()->where('user_id', $user->id)->first();
    NotificationLog::factory()
        ->forUser($user)
        ->ofType(NotificationType::ShiftAssignmentsReleased)
        ->failed()
        ->create()
        ->shiftUsers()
        ->attach($assignment);

    $this->travelTo('2023-02-06 12:30:01');

    expect(app(NotifyVolunteersOfReleasedShifts::class)->execute())
        ->toBe(['users_notified' => 1, 'assignments_notified' => 1]);
    expect(releasedMails())->toHaveCount(1);
});

test('execute sends one notification per user holding only assignment ids', function () {
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

    $user = User::factory()->userRoleUser()->enabled()->create();
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();
    $user->attachShiftOnDate($shift, '2023-02-14');
    $assignment = ShiftUser::query()->where('user_id', $user->id)->first();

    Notification::fake();
    $this->travelTo('2023-02-06 12:30:01');

    $result = app(NotifyVolunteersOfReleasedShifts::class)->execute();

    expect($result)->toBe(['users_notified' => 1, 'assignments_notified' => 1]);
    Notification::assertSentToTimes($user, ShiftAssignmentsReleasedNotification::class, 1);
    Notification::assertSentTo($user, ShiftAssignmentsReleasedNotification::class,
        fn (ShiftAssignmentsReleasedNotification $notification) => $notification->shiftUserIds === [$assignment->id]
            && $notification->period === [
                'start' => '13 February 2023',
                'end' => '19 February 2023',
                'label' => '13 February 2023 – 19 February 2023',
            ]);
});

test('does not queue a second notification for the same assignments while the first is pending', function () {
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');

    $user = User::factory()->userRoleUser()->enabled()->create();
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();
    $user->attachShiftOnDate($shift, '2023-02-14');

    Config::set('queue.default', 'database');

    $this->travelTo('2023-02-06 12:30:01');

    expect(app(NotifyVolunteersOfReleasedShifts::class)->execute()['users_notified'])->toBe(1)
        ->and(app(NotifyVolunteersOfReleasedShifts::class)->execute()['users_notified'])->toBe(0);

    expect(DB::table('jobs')->count())->toBe(1);
    $this->assertDatabaseHas('notification_logs', [
        'type' => NotificationType::ShiftAssignmentsReleased->value,
        'status' => NotificationStatus::Queued->value,
    ]);
});

/**
 * The emails the faked notifications would have sent, built the same way the mail channel builds them.
 *
 * @return Collection<int, ShiftAssignmentsReleased>
 */
function releasedMails(): Collection
{
    return collect(Notification::sentNotifications())
        ->flatten(1)
        ->flatMap(fn (array $notificationsByClass) => $notificationsByClass[ShiftAssignmentsReleasedNotification::class] ?? [])
        ->map(fn (array $sent) => $sent['notification']->toMail($sent['notifiable']))
        ->values();
}
