<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class NotificationStatsData extends Data
{
    public function __construct(
        public int $sent_last_10_minutes,
        public int $sent_last_hour,
        public int $sent_last_24_hours,
        public int $failed_last_7_days,
        public int $queued,
    ) {}
}
