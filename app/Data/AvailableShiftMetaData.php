<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use stdClass;

#[TypeScript]
class AvailableShiftMetaData extends Data
{
    public function __construct(
        public int $volunteer_count,
        public int $max_allowed,
        public bool $has_availability,
    ) {
    }

    /** @noinspection PhpUnused */
    public static function fromDb(stdClass $data): self
    {
        return new self(
            volunteer_count: $data->volunteer_count,
            max_allowed: $data->max_allowed,
            has_availability: $data->has_availability,
        );
    }
}
