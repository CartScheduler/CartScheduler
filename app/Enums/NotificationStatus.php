<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Retrying = 'retrying';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Sending => 'Sending',
            self::Retrying => 'Retrying',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
        };
    }

    /**
     * Statuses that mean the notification has been, or is about to be, delivered.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Queued, self::Sending, self::Retrying, self::Sent];
    }
}
