<?php

namespace App\Jobs;

use App\Enums\CacheKey;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Queued by the scheduler every minute. When a worker runs it, the time is recorded, which is how
 * the admin dashboard knows the queue is being processed.
 */
class RecordQueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        Cache::forever(CacheKey::QueueHeartbeat, now()->getTimestamp());
    }
}
