<?php

namespace App\Actions;

use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

/**
 * Puts failed jobs back in the queue and marks their notification log rows as queued again.
 */
readonly class RetryFailedJobs
{
    public function __construct(private DescribeQueuedJob $describeQueuedJob) {}

    /**
     * @param  Collection<int, object{uuid: string, payload: string}>  $jobs  Rows from the failed jobs table.
     */
    public function execute(Collection $jobs): int
    {
        if ($jobs->isEmpty()) {
            return 0;
        }

        $notificationIds = $jobs
            ->map(fn (object $job) => $this->describeQueuedJob->notificationId(json_decode($job->payload, true) ?: []))
            ->filter()
            ->values();

        Artisan::call('queue:retry', ['id' => $jobs->pluck('uuid')->all()]);

        // Retried jobs are pushed back without the usual "queued" event, so update the log here.
        NotificationLog::query()
            ->whereIn('notification_id', $notificationIds)
            ->where('status', '!=', NotificationStatus::Sent)
            ->update([
                'status' => NotificationStatus::Queued,
                'queued_at' => now(),
                'failed_at' => null,
                'updated_at' => now(),
            ]);

        return $jobs->count();
    }
}
