<?php

use App\Enums\CacheKey;
use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\UserAccountCreatedNotification;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Config::set('queue.default', 'database');
    $this->admin = User::factory()->enabled()->adminRoleUser()->create();
    $settings = app(GeneralSettings::class);
    $settings->allowedSettingsUsers = [$this->admin->getKey()];
    $settings->save();
});

/**
 * Queues a real welcome email, then moves it to failed_jobs the way the queue does when it gives up.
 *
 * @return array{0: string, 1: NotificationLog}
 */
function addFailedWelcomeJob(string $exception, ?User $user = null): array
{
    $user ??= User::factory()->enabled()->create();
    $user->notify(new UserAccountCreatedNotification);

    $job = DB::table('jobs')->latest('id')->first();
    DB::table('jobs')->where('id', $job->id)->delete();

    $uuid = json_decode($job->payload, true)['uuid'];
    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => $job->queue,
        'payload' => $job->payload,
        'exception' => $exception,
        'failed_at' => now(),
    ]);

    $log = NotificationLog::query()->where('notifiable_id', $user->id)->latest('id')->firstOrFail();
    $log->update(['status' => NotificationStatus::Failed, 'error' => $exception, 'failed_at' => now()]);

    return [$uuid, $log];
}

test('an allowed admin sees queue health, waiting jobs and failed jobs in plain english', function () {
    $volunteer = User::factory()->enabled()->create();
    [, $log] = addFailedWelcomeJob("Symfony\\Component\\Mailer\\Exception\\TransportException: Expected response code \"235\" but got code \"535\" in /var/www/html/vendor/x.php:12\nStack trace:\n#0 ...");

    Cache::forever(CacheKey::SchedulerHeartbeat, now()->getTimestamp());
    Cache::forever(CacheKey::QueueHeartbeat, now()->getTimestamp());
    $volunteer->notify(new UserAccountCreatedNotification);

    $this->actingAs($this->admin)
        ->get('/admin/queue')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Queue/Show')
            ->where('status.healthy', true)
            ->where('status.waiting', 1)
            ->where('status.failed', 1)
            ->has('pendingJobs', 1)
            ->where('pendingJobs.0.name', 'User Account Created email')
            ->where('pendingJobs.0.state', 'waiting')
            ->where('pendingJobs.0.recipient', $volunteer->email)
            ->has('failedJobs.data', 1)
            ->where('failedJobs.data.0.error_summary', 'The mail server rejected the username or password. Check MAIL_USERNAME and MAIL_PASSWORD.')
            ->where('failedJobs.data.0.error', 'Expected response code "235" but got code "535"')
            ->where('failedJobs.data.0.recipient', $log->recipient)
            ->where('failedJobs.data.0.notification_log_id', $log->id));
});

test('admins not on the settings list cannot see or change the queue', function () {
    $otherAdmin = User::factory()->enabled()->adminRoleUser()->create();
    [$uuid] = addFailedWelcomeJob('RuntimeException: boom');

    $this->actingAs($otherAdmin)->get('/admin/queue')->assertNotFound();
    $this->actingAs($otherAdmin)->post("/admin/queue/failed/{$uuid}/retry")->assertNotFound();
    $this->actingAs($otherAdmin)->delete("/admin/queue/failed/{$uuid}")->assertNotFound();

    expect(DB::table('failed_jobs')->count())->toBe(1);
});

test('a failed job can be retried and its notification is shown as queued again', function () {
    [$uuid, $log] = addFailedWelcomeJob('RuntimeException: boom');
    [$other] = addFailedWelcomeJob('RuntimeException: other');

    $this->actingAs($this->admin)
        ->from('/admin/queue')
        ->post("/admin/queue/failed/{$uuid}/retry")
        ->assertRedirect('/admin/queue')
        ->assertSessionHas('flash.banner', 'The job was put back in the queue.');

    expect(DB::table('failed_jobs')->pluck('uuid')->all())->toBe([$other])
        ->and(DB::table('jobs')->count())->toBe(1)
        ->and($log->refresh()->status)->toBe(NotificationStatus::Queued)
        ->and($log->failed_at)->toBeNull();
});

test('resending a notification from the log retries its failed job instead of sending a duplicate', function () {
    [$uuid, $log] = addFailedWelcomeJob('RuntimeException: boom', User::factory()->enabled()->create(['password' => null]));

    $this->actingAs($this->admin)
        ->post("/admin/notifications/{$log->id}/resend")
        ->assertSessionHas('flash.bannerStyle', 'success');

    expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse()
        ->and(DB::table('jobs')->count())->toBe(1)
        ->and(NotificationLog::query()->count())->toBe(1)
        ->and($log->refresh()->status)->toBe(NotificationStatus::Queued);
});

test('all failed jobs can be retried at once', function () {
    addFailedWelcomeJob('RuntimeException: one');
    addFailedWelcomeJob('RuntimeException: two');

    $this->actingAs($this->admin)
        ->post('/admin/queue/failed/all/retry')
        ->assertSessionHas('flash.banner', '2 failed jobs were put back in the queue.');

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(2);
});

test('a failed job can be deleted', function () {
    [$uuid] = addFailedWelcomeJob('RuntimeException: boom');

    $this->actingAs($this->admin)
        ->delete("/admin/queue/failed/{$uuid}")
        ->assertSessionHas('flash.banner', 'The failed job was deleted.');

    expect(DB::table('failed_jobs')->count())->toBe(0);
});

test('retrying or deleting an unknown job is not found', function () {
    $missing = (string) Str::uuid();

    $this->actingAs($this->admin)->post("/admin/queue/failed/{$missing}/retry")->assertNotFound();
    $this->actingAs($this->admin)->delete("/admin/queue/failed/{$missing}")->assertNotFound();
});

test('the dashboard shows the queue and notification panels only to allowed admins', function () {
    NotificationLog::factory()->failed()->create();
    $otherAdmin = User::factory()->enabled()->adminRoleUser()->create();

    $this->actingAs($this->admin)
        ->get('/admin/')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notificationStats.failed_last_7_days', 1)
            ->where('queueStatus.connection', 'database')
            ->where('queueStatus.healthy', false));

    $this->actingAs($otherAdmin)
        ->get('/admin/')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notificationStats', null)
            ->where('queueStatus', null));
});
