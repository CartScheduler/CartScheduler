<?php

use App\Actions\GetQueueStatus;
use App\Enums\CacheKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => Config::set('queue.default', 'database'));

function addQueuedJob(array $attributes = []): void
{
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\Jobs\Example', 'data' => []]),
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->getTimestamp(),
        'created_at' => now()->getTimestamp(),
        ...$attributes,
    ]);
}

test('the queue is healthy when the scheduler and worker have run recently', function () {
    Cache::forever(CacheKey::SchedulerHeartbeat, now()->subMinute()->getTimestamp());
    Cache::forever(CacheKey::QueueHeartbeat, now()->subMinutes(2)->getTimestamp());

    $status = app(GetQueueStatus::class)->execute();

    expect($status->healthy)->toBeTrue()
        ->and($status->problems)->toBe([]);
});

test('a sync queue is reported as switched off', function () {
    Config::set('queue.default', 'sync');

    $status = app(GetQueueStatus::class)->execute();

    expect($status->healthy)->toBeFalse()
        ->and($status->problems[0])->toContain('QUEUE_CONNECTION=sync');
});

test('a scheduler that has never run is reported with the cron to add', function () {
    $status = app(GetQueueStatus::class)->execute();

    expect($status->healthy)->toBeFalse()
        ->and($status->problems)->toBe(['The scheduler has never run. Add a cron job that runs "php artisan schedule:run" every minute.']);
});

test('a stale scheduler is reported, not the worker it starts', function () {
    Cache::forever(CacheKey::SchedulerHeartbeat, now()->subMinutes(20)->getTimestamp());

    $problems = app(GetQueueStatus::class)->execute()->problems;

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toStartWith('The scheduler last ran 20 minutes ago.');
});

test('a stale worker is reported when the scheduler is running', function () {
    Cache::forever(CacheKey::SchedulerHeartbeat, now()->getTimestamp());
    Cache::forever(CacheKey::QueueHeartbeat, now()->subMinutes(10)->getTimestamp());

    expect(app(GetQueueStatus::class)->execute()->problems)
        ->toBe(['The queue worker last processed a job 10 minutes ago. Waiting emails are not being sent.']);
});

test('waiting, processing, delayed and failed jobs are counted', function () {
    addQueuedJob(['available_at' => now()->subMinutes(3)->getTimestamp()]);
    addQueuedJob();
    addQueuedJob(['reserved_at' => now()->getTimestamp(), 'attempts' => 1]);
    addQueuedJob(['available_at' => now()->addMinutes(5)->getTimestamp()]);
    DB::table('failed_jobs')->insert([
        'uuid' => 'b3a1f2c4-0000-4000-8000-000000000001',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'RuntimeException: boom',
        'failed_at' => now(),
    ]);

    $status = app(GetQueueStatus::class)->execute();

    expect($status->waiting)->toBe(2)
        ->and($status->processing)->toBe(1)
        ->and($status->delayed)->toBe(1)
        ->and($status->failed)->toBe(1)
        ->and($status->oldest_waiting_since)->toBe(now()->subMinutes(3)->startOfSecond()->toIso8601String());
});
