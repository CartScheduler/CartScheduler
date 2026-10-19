<?php

namespace App\Http\Controllers;

use App\Actions\DescribeJobFailure;
use App\Actions\DescribeQueuedJob;
use App\Actions\GetQueueStatus;
use App\Data\FailedJobData;
use App\Data\PendingJobData;
use App\Http\Controllers\Concerns\EnsuresAllowedSettingsUser;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ShowQueueController extends Controller
{
    use EnsuresAllowedSettingsUser;

    private const int PENDING_LIMIT = 100;

    public function __construct(
        private readonly DescribeQueuedJob $describeQueuedJob,
        private readonly DescribeJobFailure $describeJobFailure,
    ) {}

    public function __invoke(Request $request, GetQueueStatus $getQueueStatus): Response
    {
        $this->ensureAllowedSettingsUser($request);

        $failed = DB::table(config('queue.failed.table', 'failed_jobs'))
            ->orderByDesc('failed_at')
            ->paginate(25, ['uuid', 'queue', 'payload', 'exception', 'failed_at'], 'failed_page')
            ->withQueryString();

        $failedRows = collect($failed->items());
        $pendingRows = $this->pendingRows();
        $logs = $this->logsByNotificationId($failedRows->merge($pendingRows));

        return Inertia::render('Admin/Queue/Show', [
            'status' => $getQueueStatus->execute(),
            'failedJobs' => $failed->setCollection($failedRows->map(fn (object $row) => $this->failedJob($row, $logs))),
            'pendingJobs' => PendingJobData::collect($pendingRows->map(fn (object $row) => $this->pendingJob($row, $logs))->all()),
            'pendingLimit' => self::PENDING_LIMIT,
        ]);
    }

    /**
     * @return Collection<int, object>
     */
    private function pendingRows(): Collection
    {
        $connection = config('queue.default');

        if (config("queue.connections.$connection.driver") !== 'database') {
            return collect();
        }

        return DB::table(config("queue.connections.$connection.table", 'jobs'))
            ->orderBy('available_at')
            ->orderBy('id')
            ->limit(self::PENDING_LIMIT)
            ->get(['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at']);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<string, NotificationLog>
     */
    private function logsByNotificationId(Collection $rows): Collection
    {
        $ids = $rows
            ->map(fn (object $row) => $this->describeQueuedJob->notificationId($this->payload($row)))
            ->filter()
            ->unique()
            ->values();

        return $ids->isEmpty()
            ? collect()
            : NotificationLog::query()->whereIn('notification_id', $ids)->get()->keyBy('notification_id');
    }

    /**
     * @param  Collection<string, NotificationLog>  $logs
     */
    private function failedJob(object $row, Collection $logs): FailedJobData
    {
        $payload = $this->payload($row);
        $log = $logs->get((string) $this->describeQueuedJob->notificationId($payload));

        return new FailedJobData(
            uuid: $row->uuid,
            name: $this->describeQueuedJob->name($payload),
            queue: $row->queue,
            failed_at: Carbon::parse($row->failed_at)->toIso8601String(),
            error_summary: $this->describeJobFailure->execute($row->exception),
            error: $this->describeJobFailure->message($row->exception),
            recipient: $log?->recipient,
            notification_log_id: $log?->id,
        );
    }

    /**
     * @param  Collection<string, NotificationLog>  $logs
     */
    private function pendingJob(object $row, Collection $logs): PendingJobData
    {
        $payload = $this->payload($row);
        $log = $logs->get((string) $this->describeQueuedJob->notificationId($payload));

        return new PendingJobData(
            id: $row->id,
            name: $this->describeQueuedJob->name($payload),
            queue: $row->queue,
            state: match (true) {
                $row->reserved_at !== null => 'processing',
                $row->available_at > now()->getTimestamp() => 'delayed',
                default => 'waiting',
            },
            attempts: (int) $row->attempts,
            queued_at: Carbon::createFromTimestamp($row->created_at, config('app.timezone'))->toIso8601String(),
            available_at: Carbon::createFromTimestamp($row->available_at, config('app.timezone'))->toIso8601String(),
            recipient: $log?->recipient,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(object $row): array
    {
        return json_decode($row->payload, true) ?: [];
    }
}
