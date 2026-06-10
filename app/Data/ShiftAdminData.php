<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ShiftAdminData extends Data
{
    public function __construct(
        public ?int $id,
        public int $location_id,
        public string $start_time,
        public string $end_time,
        public bool $day_monday = false,
        public bool $day_tuesday = false,
        public bool $day_wednesday = false,
        public bool $day_thursday = false,
        public bool $day_friday = false,
        public bool $day_saturday = false,
        public bool $day_sunday = false,
        public ?string $available_from = null,
        public ?string $available_to = null,
        public bool $is_enabled = true,
    ) {
    }
}
