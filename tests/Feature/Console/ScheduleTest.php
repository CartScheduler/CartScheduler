<?php

use App\Enums\CacheKey;
use App\Jobs\RecordQueueHeartbeat;
use App\Models\User;
use App\Models\UserVacation;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;

uses(RefreshDatabase::class);

/**
 * Re-reads routes/console.php into a fresh schedule, so config set by a test is honoured.
 *
 * @return list<Event>
 */
function freshScheduleEvents(): array
{
    $schedule = new Schedule;
    app()->instance(Schedule::class, $schedule);
    ScheduleFacade::clearResolvedInstance(Schedule::class);

    require base_path('routes/console.php');

    return $schedule->events();
}

/**
 * @param  list<Event>  $events
 */
function scheduledCommand(array $events, string $needle): ?Event
{
    return collect($events)->first(fn (Event $event) => str_contains((string) $event->command, $needle)
        || str_contains((string) $event->description, $needle));
}

test('old notifications, failed jobs and vacations are pruned daily', function () {
    Config::set('cart-scheduler.failed_job_retention_days', 30);
    $events = freshScheduleEvents();

    expect(scheduledCommand($events, 'model:prune')?->expression)->toBe('0 0 * * *')
        ->and(scheduledCommand($events, 'queue:prune-failed')?->command)->toContain('--hours=720');
});

test('the scheduler records a heartbeat every minute', function () {
    $heartbeat = scheduledCommand(freshScheduleEvents(), 'scheduler-heartbeat');

    expect($heartbeat)->toBeInstanceOf(CallbackEvent::class)
        ->and($heartbeat->expression)->toBe('* * * * *');

    $heartbeat->run(app());

    expect(Cache::get(CacheKey::SchedulerHeartbeat))->toBeGreaterThanOrEqual(now()->subMinute()->getTimestamp());
});

test('a queue worker and queue heartbeat run every minute when a real queue is used', function () {
    Config::set('queue.default', 'database');
    Config::set('cart-scheduler.run_queue_worker_from_scheduler', true);
    $events = freshScheduleEvents();

    $worker = scheduledCommand($events, 'queue:work');
    expect($worker?->expression)->toBe('* * * * *')
        ->and($worker->command)->toContain('--stop-when-empty')
        ->and($worker->command)->toContain('--max-time=50')
        ->and($worker->withoutOverlapping)->toBeTrue()
        ->and(scheduledCommand($events, RecordQueueHeartbeat::class))->not->toBeNull();
});

test('the queue worker can be left to Supervisor', function () {
    Config::set('queue.default', 'database');
    Config::set('cart-scheduler.run_queue_worker_from_scheduler', false);

    expect(scheduledCommand(freshScheduleEvents(), 'queue:work'))->toBeNull();
});

test('no queue worker is scheduled when jobs run synchronously', function () {
    Config::set('queue.default', 'sync');
    $events = freshScheduleEvents();

    expect(scheduledCommand($events, 'queue:work'))->toBeNull()
        ->and(scheduledCommand($events, RecordQueueHeartbeat::class))->toBeNull();
});

test('vacations are pruned once they ended more than 30 days ago', function () {
    $user = User::factory()->create();
    $recent = UserVacation::factory()->for($user)->create([
        'start_date' => now()->subDays(40),
        'end_date' => now()->subDays(29),
    ]);
    $old = UserVacation::factory()->for($user)->create([
        'start_date' => now()->subDays(40),
        'end_date' => now()->subDays(31),
    ]);

    $this->artisan('model:prune', ['--model' => [UserVacation::class]])->assertSuccessful();

    expect(UserVacation::find($recent->id))->not->toBeNull()
        ->and(UserVacation::find($old->id))->toBeNull();
});

test('the queue heartbeat job records when a worker ran it', function () {
    (new RecordQueueHeartbeat)->handle();

    expect(Cache::get(CacheKey::QueueHeartbeat))->toBeGreaterThanOrEqual(now()->subMinute()->getTimestamp());
});
