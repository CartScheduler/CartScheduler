<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PendingJobData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $queue,
        #[LiteralTypeScriptType('"waiting" | "processing" | "delayed"')]
        public string $state,
        public int $attempts,
        public string $queued_at,
        public string $available_at,
        public ?string $recipient,
    ) {}
}
