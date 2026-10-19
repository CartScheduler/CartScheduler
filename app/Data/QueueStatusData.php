<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class QueueStatusData extends Data
{
    /**
     * @param  ?string  $summary  A short, non-technical sentence for when the queue isn't working.
     * @param  list<string>  $problems  Technical reasons the queue isn't working, for an administrator. Empty when healthy.
     */
    public function __construct(
        public bool $healthy,
        public ?string $summary,
        #[LiteralTypeScriptType('Array<string>')]
        public array $problems,
        public string $connection,
        public int $waiting,
        public int $processing,
        public int $delayed,
        public int $failed,
        public ?string $oldest_waiting_since,
        public ?string $worker_last_ran_at,
        public ?string $scheduler_last_ran_at,
    ) {}
}
