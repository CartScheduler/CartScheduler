<?php

namespace App\Data;

use App\Actions\DescribeJobFailure;
use App\Actions\ResendNotification;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class NotificationLogData extends Data
{
    public function __construct(
        public int $id,
        public string $type,
        public string $type_label,
        public string $channel,
        public ?string $recipient,
        public ?string $recipient_name,
        public ?int $user_id,
        public ?string $subject,
        public NotificationStatus $status,
        public string $status_label,
        public bool $is_stalled,
        public int $attempts,
        public ?string $error_summary,
        public ?string $error,
        public string $created_at,
        public ?string $queued_at,
        public ?string $sent_at,
        public ?string $failed_at,
        public int $later_attempts,
        public ?string $later_sent_at,
        public bool $later_in_progress,
        public bool $can_resend,
        public ?string $cannot_resend_reason,
    ) {}

    /**
     * @param  array{count: int, sent_at: ?Carbon, in_progress: bool}|null  $laterAttempts  From GetLaterNotificationAttempts.
     */
    public static function fromModel(NotificationLog $log, ?array $laterAttempts = null): self
    {
        $isStalled = $log->isStalled();
        $isFailed = $isStalled || $log->status === NotificationStatus::Failed;
        $canBeRetried = $isFailed
            && ($laterAttempts['sent_at'] ?? null) === null
            && ! ($laterAttempts['in_progress'] ?? false);
        $cannotResendReason = $canBeRetried ? app(ResendNotification::class)->reasonItCannotBeResent($log) : null;
        $describer = app(DescribeJobFailure::class);
        $user = $log->notifiable instanceof User ? $log->notifiable : null;

        return new self(
            id: $log->id,
            type: $log->type,
            type_label: NotificationType::labelFor($log->type),
            channel: $log->channel,
            recipient: $log->recipient,
            recipient_name: $user?->name,
            user_id: $user?->id,
            subject: $log->subject,
            status: $isStalled ? NotificationStatus::Failed : $log->status,
            status_label: $isStalled ? 'Stalled' : $log->status->label(),
            is_stalled: $isStalled,
            attempts: $log->attempts,
            error_summary: match (true) {
                $log->error !== null => $describer->execute($log->error),
                $isStalled => 'Sending started but never finished, so this email may not have been delivered.',
                default => null,
            },
            error: $log->error ? $describer->message($log->error) : null,
            created_at: $log->created_at->toIso8601String(),
            queued_at: $log->queued_at?->toIso8601String(),
            sent_at: $log->sent_at?->toIso8601String(),
            failed_at: $log->failed_at?->toIso8601String(),
            later_attempts: $laterAttempts['count'] ?? 0,
            later_sent_at: ($laterAttempts['sent_at'] ?? null)?->toIso8601String(),
            later_in_progress: $laterAttempts['in_progress'] ?? false,
            can_resend: $canBeRetried && $cannotResendReason === null,
            cannot_resend_reason: $canBeRetried ? $cannotResendReason : null,
        );
    }
}
