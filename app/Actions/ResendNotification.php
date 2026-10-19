<?php

namespace App\Actions;

use App\Enums\NotificationType;
use App\Mail\EmailPlaceholderRegistry;
use App\Models\NotificationLog;
use App\Models\ShiftUser;
use App\Models\User;
use App\Notifications\ShiftAssignmentsReleasedNotification;
use App\Notifications\UserAccountCreatedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sends a failed notification again when an administrator asks for it.
 *
 * If the failed job is still in the failed jobs table it is retried, which keeps the same log row.
 * Otherwise the notification is rebuilt from the log and sent as a new one.
 */
readonly class ResendNotification
{
    public function __construct(
        private RetryFailedJobs $retryFailedJobs,
        private DescribeQueuedJob $describeQueuedJob,
        private EmailPlaceholderRegistry $placeholderRegistry,
    ) {}

    /**
     * Whether the notification can still be rebuilt. Password resets and similar emails carry
     * one-off links, so the person has to ask for those again.
     */
    public function canResend(NotificationLog $log): bool
    {
        $user = $log->notifiable;

        if (! $user instanceof User) {
            return false;
        }

        return match (NotificationType::tryFrom($log->type)) {
            NotificationType::UserAccountCreated => ! $user->has_logged_in,
            NotificationType::ShiftAssignmentsReleased => $user->is_enabled && $this->upcomingAssignments($log)->isNotEmpty(),
            default => false,
        };
    }

    public function execute(NotificationLog $log): void
    {
        if ($this->retryFailedJob($log)) {
            return;
        }

        /** @var User $user */
        $user = $log->notifiable;

        match (NotificationType::from($log->type)) {
            NotificationType::UserAccountCreated => $user->notify(new UserAccountCreatedNotification),
            NotificationType::ShiftAssignmentsReleased => $this->resendShiftAssignments($user, $log),
        };
    }

    private function retryFailedJob(NotificationLog $log): bool
    {
        if ($log->notification_id === null) {
            return false;
        }

        $jobs = DB::table(config('queue.failed.table', 'failed_jobs'))
            ->where('payload', 'like', '%'.$log->notification_id.'%')
            ->get(['uuid', 'payload'])
            ->filter(fn (object $job) => $this->describeQueuedJob
                ->notificationId(json_decode($job->payload, true) ?: []) === $log->notification_id)
            ->values();

        return $this->retryFailedJobs->execute($jobs) > 0;
    }

    private function resendShiftAssignments(User $user, NotificationLog $log): void
    {
        $assignments = $this->upcomingAssignments($log);
        $dates = $assignments->map(fn (ShiftUser $assignment) => Carbon::parse($assignment->shift_date));

        $user->notify(new ShiftAssignmentsReleasedNotification(
            $assignments->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            $this->placeholderRegistry->periodVariables($dates->min(), $dates->max()),
        ));
    }

    /**
     * @return Collection<int, ShiftUser>
     */
    private function upcomingAssignments(NotificationLog $log): Collection
    {
        return $log->shiftUsers
            ->filter(fn (ShiftUser $assignment) => Carbon::parse($assignment->shift_date)->gte(today()))
            ->values();
    }
}
