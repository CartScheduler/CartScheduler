<?php

namespace App\Notifications\Concerns;

use DateTimeInterface;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Shared queue behaviour for notifications sent by the app.
 *
 * A notification keeps retrying for 12 hours, but gives up after 5 real errors. Waiting for the
 * mail rate limit (e.g. during a bulk user import) does not count as an error.
 */
trait QueuesWithRetries
{
    public int $maxExceptions = 5;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60, 120, 240];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(12);
    }

    /**
     * @return list<object>
     */
    public function middleware(object $notifiable, string $channel): array
    {
        return $channel === 'mail' ? [new RateLimited('mail-notifications')] : [];
    }
}
