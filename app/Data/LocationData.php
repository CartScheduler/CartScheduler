<?php

namespace App\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class LocationData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description = null,
        public int $min_volunteers = 0,
        public int $max_volunteers = 5,
        public bool $requires_brother = false,
        /** @var Collection<\App\Data\ShiftData> */
        public ?Collection $shifts = null,
    ) {
    }
}
