<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UserVacationData extends Data
{
    public function __construct(
        public ?int $id,
        public string $start_date,
        public string $end_date,
        public ?string $description = null,
    ) {
    }
}
