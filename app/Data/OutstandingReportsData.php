<?php

namespace App\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class OutstandingReportsData extends Data
{
    public function __construct(
        public int $shift_id,
        #[LiteralTypeScriptType('IsoDate')]
        public string $shift_date,
        #[LiteralTypeScriptType('TwentyFourHourTime')]
        public string $start_time,
        #[LiteralTypeScriptType('TwentyFourHourTime')]
        public string $end_time,
        public ?int $requires_brother = 0,
        public ?string $location_name,
    ) {
    }
}
