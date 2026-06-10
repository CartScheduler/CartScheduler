<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class FilledShiftData extends Data
{
    public function __construct(
        #[LiteralTypeScriptType('IsoDate')]
        public string $date,
        public int $shifts_filled,
        public int $shifts_available,
    ) {
    }
}
