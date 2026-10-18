<?php

namespace App\Data;

use App\Actions\DescribeJobFailure;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\NotificationLog;
use App\Models\User;
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
    ) {}

    public static function fromModel(NotificationLog $log): self
    {
        $isStalled = $log->isStalled();
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
        );
    }
}
