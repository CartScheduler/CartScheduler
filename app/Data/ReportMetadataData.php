<?php

namespace App\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Spatie\TypeScriptTransformer\Attributes\TypeScriptType;

#[TypeScript]
class ReportMetadataData extends Data
{
    public function __construct(
        public int $shift_id,
        public string $shift_time,
        public int $location_id,
        public string $location_name,
        public int $submitted_by_id,
        public string $submitted_by_name,
        public string $submitted_by_email,
        public string $submitted_by_phone,
        #[TypeScriptType(['id' => 'int', 'name' => 'string'])]
        public Collection $associates,
    )
    {
    }
}
