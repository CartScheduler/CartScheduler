<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class EmailPlaceholderData extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public string $type,
        public string $category,
        public ?string $value = null,
        public ?string $parent = null,
        public ?string $collection = null,
        public ?string $item = null,
    ) {}
}
