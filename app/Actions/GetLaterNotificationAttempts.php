<?php

namespace App\Actions;

use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * For failed notifications, finds the same notification sent again later to the same person (and,
 * for shift emails, about the same assignments), whether by a resend or the Users page.
 */
class GetLaterNotificationAttempts
{
    /**
     * @param  Collection<int, NotificationLog>  $logs
     * @return array<int, array{count: int, sent_at: ?Carbon, in_progress: bool}> Keyed by the failed log's ID.
     */
    public function execute(Collection $logs): array
    {
        $failed = $logs
            ->filter(fn (NotificationLog $log) => $log->notifiable_id !== null
                && ($log->status === NotificationStatus::Failed || $log->isStalled()))
            ->values();

        if ($failed->isEmpty()) {
            return [];
        }

        $candidates = NotificationLog::query()
            ->with('shiftUsers')
            ->whereIn('type', $failed->pluck('type')->unique())
            ->where('id', '>', $failed->min('id'))
            ->where(function (Builder $query) use ($failed) {
                $failed->groupBy('notifiable_type')->each(fn (Collection $group, string $type) => $query
                    ->orWhere(fn (Builder $query) => $query
                        ->where('notifiable_type', $type)
                        ->whereIn('notifiable_id', $group->pluck('notifiable_id')->unique())));
            })
            ->orderBy('id')
            ->get();

        return $failed->mapWithKeys(function (NotificationLog $log) use ($candidates) {
            $later = $candidates->filter(fn (NotificationLog $candidate) => $candidate->id > $log->id
                && $candidate->type === $log->type
                && $candidate->notifiable_type === $log->notifiable_type
                && (int) $candidate->notifiable_id === (int) $log->notifiable_id
                && $this->sharesSubjects($log, $candidate));

            return [$log->id => [
                'count' => $later->count(),
                'sent_at' => $later->firstWhere('status', NotificationStatus::Sent)?->sent_at,
                'in_progress' => $later->contains(fn (NotificationLog $candidate) => ! $candidate->isStalled()
                    && in_array($candidate->status, [NotificationStatus::Queued, NotificationStatus::Sending, NotificationStatus::Retrying], true)),
            ]];
        })->all();
    }

    private function sharesSubjects(NotificationLog $log, NotificationLog $candidate): bool
    {
        if ($log->shiftUsers->isEmpty()) {
            return true;
        }

        return $log->shiftUsers->pluck('id')->intersect($candidate->shiftUsers->pluck('id'))->isNotEmpty();
    }
}
