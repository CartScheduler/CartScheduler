<?php

namespace App\Actions;

use App\Enums\NotificationType;
use App\Jobs\RecordQueueHeartbeat;
use Illuminate\Support\Str;

/**
 * Reads a queue payload to give a job a readable name, and finds the notification it sends.
 * The payload is never unserialised here, so this is safe and cheap to run over many rows.
 */
class DescribeQueuedJob
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function name(array $payload): string
    {
        $class = (string) ($payload['displayName'] ?? 'Unknown job');

        if ($class === RecordQueueHeartbeat::class) {
            return 'Queue health check';
        }

        if (($payload['data']['commandName'] ?? null) === 'Illuminate\Notifications\SendQueuedNotifications') {
            return NotificationType::labelFor(NotificationType::keyFor($class)).' email';
        }

        return Str::headline(class_basename($class));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function notificationId(array $payload): ?string
    {
        $command = (string) ($payload['data']['command'] ?? '');

        return preg_match('/s:2:"id";s:36:"([0-9a-f-]{36})"/', $command, $matches) ? $matches[1] : null;
    }
}
