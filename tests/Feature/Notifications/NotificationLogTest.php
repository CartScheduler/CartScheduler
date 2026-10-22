<?php

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Mail\UserAccountCreated;
use App\Models\Location;
use App\Models\NotificationLog;
use App\Models\Shift;
use App\Models\ShiftUser;
use App\Models\User;
use App\Notifications\UserAccountCreatedNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

uses(RefreshDatabase::class);

test('the welcome email goes through the queue', function () {
    $user = User::factory()->userRoleUser()->create();
    Config::set('queue.default', 'database');

    User::sendWelcomeEmail($user);

    expect(DB::table('jobs')->count())->toBe(1);
    $log = NotificationLog::sole();
    expect($log->status)->toBe(NotificationStatus::Queued)
        ->and($log->type)->toBe(NotificationType::UserAccountCreated->value)
        ->and($log->notifiable->is($user))->toBeTrue()
        ->and($log->recipient)->toBe($user->email)
        ->and($log->queued_at)->not->toBeNull();

    $this->artisan('queue:work', ['--once' => true, '--sleep' => 0])->assertSuccessful();

    expect($log->refresh()->status)->toBe(NotificationStatus::Sent)
        ->and($log->subject)->toContain('Account Activation');
});

test('each notification and channel is logged only once', function () {
    $user = User::factory()->userRoleUser()->create();

    $user->notify(new UserAccountCreatedNotification);

    expect(NotificationLog::query()->where('notifiable_id', $user->id)->count())->toBe(1)
        ->and(NotificationLog::sole()->attempts)->toBe(1);
});

test('a plain mailable is logged as an email', function () {
    $user = User::factory()->userRoleUser()->create();
    NotificationLog::query()->delete();

    Mail::to($user->email)->send(new UserAccountCreated($user));

    $log = NotificationLog::sole();
    expect($log->status)->toBe(NotificationStatus::Sent)
        ->and($log->notification_id)->toBeNull()
        ->and($log->channel)->toBe('mail')
        ->and($log->type)->toBe(NotificationType::UserAccountCreated->value)
        ->and($log->recipient)->toBe($user->email)
        ->and($log->subject)->toContain('Account Activation');
});

test('framework notifications such as password resets are logged', function () {
    $user = User::factory()->userRoleUser()->create();
    NotificationLog::query()->delete();

    Password::sendResetLink(['email' => $user->email]);

    $log = NotificationLog::sole();
    expect($log->type)->toBe(NotificationType::PasswordReset->value)
        ->and($log->status)->toBe(NotificationStatus::Sent)
        ->and(NotificationType::labelFor($log->type))->toBe('Password Reset');
});

test('a failure outside the queue is logged as failed straight away', function () {
    $user = User::factory()->userRoleUser()->create();
    NotificationLog::query()->delete();

    Mail::extend('failing', fn () => new class extends AbstractTransport
    {
        protected function doSend(SentMessage $message): void
        {
            throw new TransportException('Expected response code "235" but got code "535", with message "535 Authentication failed".');
        }

        public function __toString(): string
        {
            return 'failing://';
        }
    });
    Config::set('mail.mailers.failing', ['transport' => 'failing']);
    Config::set('mail.default', 'failing');

    expect(fn () => $user->notify(new ResetPassword('token')))->toThrow(TransportException::class);

    $log = NotificationLog::sole();
    expect($log->status)->toBe(NotificationStatus::Failed)
        ->and($log->error)->toContain('535 Authentication failed')
        ->and($log->failed_at)->not->toBeNull();
});

test('rows left sending count as failed once stalled', function () {
    NotificationLog::factory()->create();
    NotificationLog::factory()->failed()->create();
    $stalled = NotificationLog::factory()->stalled()->create();
    NotificationLog::factory()->create(['status' => NotificationStatus::Sending]);

    expect(NotificationLog::query()->failedOrStalled()->count())->toBe(2)
        ->and($stalled->refresh()->isStalled())->toBeTrue();
});

test('old rows are pruned unless they protect a future shift from a repeat email', function () {
    Config::set('cart-scheduler.notification_log_retention_days', 90);

    $recent = NotificationLog::factory()->create(['created_at' => now()->subDays(89)]);
    $old = NotificationLog::factory()->create(['created_at' => now()->subDays(91)]);
    $oldForFutureShift = NotificationLog::factory()
        ->ofType(NotificationType::ShiftAssignmentsReleased)
        ->create(['created_at' => now()->subDays(91)]);
    $oldForPastShift = NotificationLog::factory()
        ->ofType(NotificationType::ShiftAssignmentsReleased)
        ->create(['created_at' => now()->subDays(91)]);

    $user = User::factory()->userRoleUser()->create();
    $shift = Shift::factory()->everyDay9am()->for(Location::factory())->create();
    $user->attachShiftOnDate($shift, now()->addDays(10)->toDateString());
    $user->attachShiftOnDate($shift, now()->subDays(100)->toDateString());
    [$past, $future] = ShiftUser::query()->where('user_id', $user->id)->orderBy('shift_date')->get();
    $oldForFutureShift->shiftUsers()->attach($future);
    $oldForPastShift->shiftUsers()->attach($past);

    $this->artisan('model:prune', ['--model' => [NotificationLog::class]])->assertSuccessful();

    expect(NotificationLog::query()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$recent->id, $oldForFutureShift->id])->sort()->values()->all())
        ->and(NotificationLog::find($old->id))->toBeNull()
        ->and(DB::table('notification_log_subjects')->where('notification_log_id', $oldForPastShift->id)->exists())->toBeFalse();
});
