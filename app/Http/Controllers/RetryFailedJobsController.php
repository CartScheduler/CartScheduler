<?php

namespace App\Http\Controllers;

use App\Actions\RetryFailedJobs;
use App\Http\Controllers\Concerns\EnsuresAllowedSettingsUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RetryFailedJobsController extends Controller
{
    use EnsuresAllowedSettingsUser;

    /**
     * Retries one failed job, or all of them when `$uuid` is "all".
     */
    public function __invoke(Request $request, string $uuid, RetryFailedJobs $retryFailedJobs): RedirectResponse
    {
        $this->ensureAllowedSettingsUser($request);

        $jobs = DB::table(config('queue.failed.table', 'failed_jobs'))
            ->when($uuid !== 'all', fn ($query) => $query->where('uuid', $uuid))
            ->get(['uuid', 'payload']);

        abort_if($uuid !== 'all' && $jobs->isEmpty(), 404);

        $count = $retryFailedJobs->execute($jobs);
        session()->flash('flash.banner', match (true) {
            $count === 0 => 'There were no failed jobs to retry.',
            $count === 1 => 'The job was put back in the queue.',
            default => "$count failed jobs were put back in the queue.",
        });
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }
}
