<?php

use App\Actions\MapDayOfWeek;
use App\Enums\CacheKey;
use App\Enums\DBPeriod;
use App\Jobs\RecordQueueHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Schedule::command('activitylog:clean --force')->daily();
Schedule::command('cart-scheduler:has-update')->daily();
Schedule::command('model:prune')->daily();
Schedule::command('queue:prune-failed', ['--hours' => config('cart-scheduler.failed_job_retention_days') * 24])->daily();

// Heartbeats let the admin dashboard show whether cron and the queue worker are running.
Schedule::call(static fn () => Cache::forever(CacheKey::SchedulerHeartbeat, now()->getTimestamp()))
    ->name('scheduler-heartbeat')
    ->everyMinute();

if (config('queue.default') !== 'sync') {
    Schedule::job(new RecordQueueHeartbeat)->everyMinute();

    // Hosts without Supervisor (e.g. cPanel) run the worker from cron: each minute it works through
    // the queue, then stops when the queue is empty or after 50 seconds.
    if (config('cart-scheduler.run_queue_worker_from_scheduler')) {
        Schedule::command('queue:work', ['--stop-when-empty', '--max-time' => 50, '--sleep' => 3])
            ->everyMinute()
            ->withoutOverlapping(10)
            ->runInBackground();
    }
}

if (config('cart-scheduler.shift_assignment_notifications_enabled')) {
    $releaseTime = config('cart-scheduler.release_new_shifts_at_time');
    $releaseTime = strlen($releaseTime) === 5 ? $releaseTime : substr($releaseTime, 0, 5);

    if (config('cart-scheduler.do_release_shifts_daily')) {
        Schedule::command('cart-scheduler:notify-released-shifts')->dailyAt($releaseTime);
    } elseif (DBPeriod::getConfigPeriod() === DBPeriod::Week) {
        Schedule::command('cart-scheduler:notify-released-shifts')
            ->weeklyOn(
                app(MapDayOfWeek::class)->toInteger(config('cart-scheduler.release_weekly_shifts_on_day')),
                $releaseTime,
            );
    } else {
        Schedule::command('cart-scheduler:notify-released-shifts')->monthlyOn(1, $releaseTime);
    }
}
