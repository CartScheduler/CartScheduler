<?php

use App\Enums\DBPeriod;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\Location;
use App\Models\NotificationLog;
use App\Models\Shift;
use App\Models\ShiftUser;
use App\Models\User;
use App\Notifications\ShiftAssignmentsReleasedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Tests\Traits\SetConfig;

uses(RefreshDatabase::class);

uses(SetConfig::class);

beforeEach(function () {
    $this->setConfig(1, DBPeriod::Week, false, 'MON', '12:30');
});

test('logs the email as sent once the mail is sent', function () {
    [$user, $shiftUser] = releasedMailAssignmentFixture();

    $user->notify(releasedShiftsNotification([$shiftUser->id]));

    expect(releasedEmails())->toHaveCount(1)
        ->and(releasedEmails()->first()->getTo()[0]->getAddress())->toBe($user->email);

    $log = NotificationLog::query()->where('type', NotificationType::ShiftAssignmentsReleased->value)->sole();
    expect($log->status)->toBe(NotificationStatus::Sent)
        ->and($log->type)->toBe(NotificationType::ShiftAssignmentsReleased->value)
        ->and($log->channel)->toBe('mail')
        ->and($log->notifiable->is($user))->toBeTrue()
        ->and($log->attempts)->toBe(1)
        ->and($log->sent_at)->not->toBeNull()
        ->and($log->recipient)->toBe($user->email)
        ->and($log->subject)->toContain('Shift Assignments')
        ->and($log->shiftUsers->pluck('id')->all())->toBe([$shiftUser->id]);
});

test('a failed send is logged as retrying, then failed when the queue gives up', function () {
    useFailingMailer();
    Config::set('queue.default', 'database');
    [$user, $shiftUser] = releasedMailAssignmentFixture();
    DB::table('jobs')->delete();
    NotificationLog::query()->delete();

    $user->notify(releasedShiftsNotification([$shiftUser->id]));

    expect(NotificationLog::sole()->status)->toBe(NotificationStatus::Queued);

    $this->artisan('queue:work', ['--once' => true, '--sleep' => 0])->assertSuccessful();

    $log = NotificationLog::sole();
    expect($log->status)->toBe(NotificationStatus::Retrying)
        ->and($log->attempts)->toBe(1)
        ->and($log->error)->toContain('Connection could not be established');

    foreach (range(2, 5) as $attempt) {
        $this->travel(10)->minutes();
        $this->artisan('queue:work', ['--once' => true, '--sleep' => 0])->assertSuccessful();
    }

    $log->refresh();
    expect($log->status)->toBe(NotificationStatus::Failed)
        ->and($log->attempts)->toBe(5)
        ->and($log->failed_at)->not->toBeNull()
        ->and(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('jobs')->count())->toBe(0);
});

test('retrying an already sent notification does not send a second email', function () {
    [$user, $shiftUser] = releasedMailAssignmentFixture();

    $notification = releasedShiftsNotification([$shiftUser->id]);
    $notification->id = '9a1f7a0e-1111-4c5e-9d1a-2f4b6c8d0e12';

    $user->notify($notification);
    $user->notify($notification);

    expect(releasedEmails())->toHaveCount(1)
        ->and(NotificationLog::query()->where('notification_id', $notification->id)->sole()->status)
        ->toBe(NotificationStatus::Sent);
});

test('the email is skipped when its assignments were removed while queued', function () {
    [$user, $shiftUser] = releasedMailAssignmentFixture();
    Config::set('queue.default', 'database');

    $user->notify(releasedShiftsNotification([$shiftUser->id]));
    $shiftUser->delete();

    $this->artisan('queue:work', ['--once' => true, '--sleep' => 0])->assertSuccessful();

    expect(releasedEmails())->toBeEmpty()
        ->and(NotificationLog::query()->where('type', NotificationType::ShiftAssignmentsReleased->value)->sole()->status)
        ->toBe(NotificationStatus::Skipped);
});

test('the queued job stores ids and dates but no names or phone numbers', function () {
    [$user, $shiftUser] = releasedMailAssignmentFixture();
    $partner = User::factory()->userRoleUser()->enabled()->create([
        'name' => 'Pat Partner',
        'mobile_phone' => '0499888777',
    ]);
    $partner->attachShiftOnDate($shiftUser->shift, '2023-02-14');
    Config::set('queue.default', 'database');

    $user->notify(releasedShiftsNotification([$shiftUser->id]));

    $payload = DB::table('jobs')->value('payload');
    expect($payload)
        ->not->toContain('Pat Partner')
        ->not->toContain('0499888777')
        ->not->toContain($user->name);
});

test('the email lists other volunteers as they are when it is sent', function () {
    [$user, $shiftUser] = releasedMailAssignmentFixture();
    Config::set('queue.default', 'database');

    $user->notify(releasedShiftsNotification([$shiftUser->id]));

    $partner = User::factory()->userRoleUser()->enabled()->create(['name' => 'Late Joiner']);
    $partner->attachShiftOnDate($shiftUser->shift, '2023-02-14');

    $this->artisan('queue:work', ['--once' => true, '--sleep' => 0])->assertSuccessful();

    expect(releasedEmails()->sole()->getHtmlBody())->toContain('Late Joiner');
});

test('emails are rate limited and retried with backoff', function () {
    $notification = releasedShiftsNotification([1]);

    expect($notification->middleware(new User, 'mail')[0])->toBeInstanceOf(RateLimited::class)
        ->and($notification->middleware(new User, 'push'))->toBe([])
        ->and($notification->backoff())->toBe([30, 60, 120, 240])
        ->and($notification->maxExceptions)->toBe(5)
        ->and($notification->retryUntil()->getTimestamp())->toBeGreaterThan(now()->addHours(11)->getTimestamp());
});

/**
 * @return array{0: User, 1: ShiftUser}
 */
function releasedMailAssignmentFixture(): array
{
    $user = User::factory()->userRoleUser()->enabled()->create();
    $location = Location::factory()->create();
    $shift = Shift::factory()->everyDay9am()->for($location)->create();
    $user->attachShiftOnDate($shift, '2023-02-14');

    $shiftUser = ShiftUser::query()->where('user_id', $user->id)->firstOrFail();

    return [$user, $shiftUser];
}

/**
 * @param  list<int>  $shiftUserIds
 */
function releasedShiftsNotification(array $shiftUserIds): ShiftAssignmentsReleasedNotification
{
    return new ShiftAssignmentsReleasedNotification($shiftUserIds, [
        'start' => '13 February 2023',
        'end' => '19 February 2023',
        'label' => '13 February 2023 – 19 February 2023',
    ]);
}

/**
 * Makes every email fail the way an unreachable SMTP server does.
 */
function useFailingMailer(): void
{
    Mail::extend('failing', fn () => new class extends AbstractTransport
    {
        protected function doSend(SentMessage $message): void
        {
            throw new TransportException('Connection could not be established with host "smtp.example.test:587"');
        }

        public function __toString(): string
        {
            return 'failing://';
        }
    });

    Config::set('mail.mailers.failing', ['transport' => 'failing']);
    Config::set('mail.default', 'failing');
}

/**
 * Shift assignment emails delivered to the in-memory `array` mailer used by the tests.
 *
 * @return Collection<int, Email>
 */
function releasedEmails(): Collection
{
    return app('mailer')->getSymfonyTransport()->messages()
        ->map(fn (SentMessage $sent) => $sent->getOriginalMessage())
        ->filter(fn (Email $email) => str_contains((string) $email->getSubject(), 'Shift Assignments'))
        ->values();
}
