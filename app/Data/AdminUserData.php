<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AdminUserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }
}
