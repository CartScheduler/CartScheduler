<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ReportTagData extends Data
{
    public function __construct(
        public int|Optional $id,
        public string $name,
        public ?int $order_column = null,
    ) {
    }
}
