<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FailedJobData extends Data
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $queue,
        public string $failed_at,
        public string $error_summary,
        public string $error,
        public ?string $recipient,
        public ?int $notification_log_id,
    ) {}
}
