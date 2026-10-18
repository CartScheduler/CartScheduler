<?php

namespace App\Actions;

use App\Data\QueueStatusData;
use App\Enums\CacheKey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Works out whether queued jobs (emails) are actually being processed.
 *
 * The scheduler records a heartbeat every minute, and queues a job that records another when a
 * worker runs it. If either is more than a few minutes old, something isn't running.
 */
class GetQueueStatus
{
    public const int STALE_AFTER_MINUTES = 5;

    public function execute(): QueueStatusData
    {
        $connection = (string) config('queue.default');
        $schedulerRanAt = $this->timestamp(CacheKey::SchedulerHeartbeat);
        $workerRanAt = $this->timestamp(CacheKey::QueueHeartbeat);
        [$waiting, $processing, $delayed, $oldestWaiting] = $this->jobCounts($connection);

        $problems = [];

        if ($connection === 'sync') {
            $problems[] = 'The queue is switched off (QUEUE_CONNECTION=sync), so emails are sent while people wait, and failed ones are not retried. Set QUEUE_CONNECTION=database.';
        } else {
            if ($this->isStale($schedulerRanAt)) {
                $problems[] = $schedulerRanAt
                    ? 'The scheduler last ran '.$schedulerRanAt->diffForHumans().'. Check the cron job that runs "php artisan schedule:run" every minute.'
                    : 'The scheduler has never run. Add a cron job that runs "php artisan schedule:run" every minute.';
            }

            if (! $this->isStale($schedulerRanAt) && $this->isStale($workerRanAt)) {
                $problems[] = $workerRanAt
                    ? 'The queue worker last processed a job '.$workerRanAt->diffForHumans().'. Waiting emails are not being sent.'
                    : 'The queue worker has not processed any jobs yet. Waiting emails are not being sent.';
            }
        }

        return new QueueStatusData(
            healthy: $problems === [],
            problems: $problems,
            connection: $connection,
            waiting: $waiting,
            processing: $processing,
            delayed: $delayed,
            failed: $this->failedCount(),
            oldest_waiting_since: $oldestWaiting?->toIso8601String(),
            worker_last_ran_at: $workerRanAt?->toIso8601String(),
            scheduler_last_ran_at: $schedulerRanAt?->toIso8601String(),
        );
    }

    private function timestamp(CacheKey $key): ?Carbon
    {
        $value = Cache::get($key->value);

        return is_int($value) ? Carbon::createFromTimestamp($value, config('app.timezone')) : null;
    }

    private function isStale(?Carbon $ranAt): bool
    {
        return $ranAt === null || $ranAt->lt(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: ?Carbon}
     */
    private function jobCounts(string $connection): array
    {
        if (config("queue.connections.$connection.driver") !== 'database') {
            return [$connection === 'sync' ? 0 : $this->genericSize(), 0, 0, null];
        }

        $now = now()->getTimestamp();
        $jobs = DB::table(config("queue.connections.$connection.table", 'jobs'));

        $counts = (clone $jobs)
            ->selectRaw('SUM(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN 1 ELSE 0 END) AS waiting_count', [$now])
            ->selectRaw('SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END) AS processing_count')
            ->selectRaw('SUM(CASE WHEN reserved_at IS NULL AND available_at > ? THEN 1 ELSE 0 END) AS delayed_count', [$now])
            ->selectRaw('MIN(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN available_at END) AS oldest_available_at', [$now])
            ->first();

        return [
            (int) ($counts->waiting_count ?? 0),
            (int) ($counts->processing_count ?? 0),
            (int) ($counts->delayed_count ?? 0),
            $counts->oldest_available_at ? Carbon::createFromTimestamp((int) $counts->oldest_available_at, config('app.timezone')) : null,
        ];
    }

    private function genericSize(): int
    {
        try {
            return Queue::size();
        } catch (Throwable) {
            return 0;
        }
    }

    private function failedCount(): int
    {
        try {
            return DB::table(config('queue.failed.table', 'failed_jobs'))->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
