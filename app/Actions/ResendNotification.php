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

    public function canResend(NotificationLog $log): bool
    {
        return $this->reasonItCannotBeResent($log) === null;
    }

    /**
     * Why the notification can't be rebuilt and sent again, in words an administrator can act on,
     * or null when it can. Password resets and similar emails carry one-off links, so the person
     * has to ask for those again.
     */
    public function reasonItCannotBeResent(NotificationLog $log): ?string
    {
        $user = $log->notifiable;

        if (! $user instanceof User) {
            return $log->notifiable_id === null
                ? "This email wasn't linked to anyone's account, so there's nothing to rebuild it from."
                : 'The account this email was for has been deleted.';
        }

        return match (NotificationType::tryFrom($log->type)) {
            NotificationType::UserAccountCreated => $user->has_logged_in
                ? "{$user->name} already has a password, so they don't need a welcome email (it's only for setting one up). If they've forgotten their password, use \"Send Password Reset Email\" on their user page."
                : null,
            NotificationType::ShiftAssignmentsReleased => match (true) {
                ! $user->is_enabled => "{$user->name}'s account is disabled. Enable it on the Users page first.",
                $this->upcomingAssignments($log)->isEmpty() => 'The shifts in this email have already happened or have been removed, so there is nothing left to tell them about.',
                default => null,
            },
            NotificationType::PasswordReset => "Password reset links expire, so a new link is needed. Use \"Send Password Reset Email\" on {$user->name}'s user page, or they can use \"Forgot your password?\" on the login page.",
            NotificationType::VerifyEmail => "Verification links expire, so a new one is needed. {$user->name} can ask for another after logging in.",
            NotificationType::TeamInvitation => 'Invitation links are one-off, so send a new invitation instead.',
            default => "This kind of email can't be rebuilt from the log.",
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
