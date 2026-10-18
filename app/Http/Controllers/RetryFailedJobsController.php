<?php

namespace App\Http\Controllers;

use App\Actions\DescribeQueuedJob;
use App\Enums\NotificationStatus;
use App\Http\Controllers\Concerns\EnsuresAllowedSettingsUser;
use App\Models\NotificationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class RetryFailedJobsController extends Controller
{
    use EnsuresAllowedSettingsUser;

    /**
     * Retries one failed job, or all of them when `$uuid` is "all".
     */
    public function __invoke(Request $request, string $uuid, DescribeQueuedJob $describeQueuedJob): RedirectResponse
    {
        $this->ensureAllowedSettingsUser($request);

        $jobs = DB::table(config('queue.failed.table', 'failed_jobs'))
            ->when($uuid !== 'all', fn ($query) => $query->where('uuid', $uuid))
            ->get(['uuid', 'payload']);

        abort_if($uuid !== 'all' && $jobs->isEmpty(), 404);

        $notificationIds = $jobs
            ->map(fn (object $job) => $describeQueuedJob->notificationId(json_decode($job->payload, true) ?: []))
            ->filter()
            ->values();

        if ($jobs->isNotEmpty()) {
            Artisan::call('queue:retry', ['id' => $jobs->pluck('uuid')->all()]);
        }

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

        $count = $jobs->count();
        session()->flash('flash.banner', match (true) {
            $count === 0 => 'There were no failed jobs to retry.',
            $count === 1 => 'The job was put back in the queue.',
            default => "$count failed jobs were put back in the queue.",
        });
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }
}
