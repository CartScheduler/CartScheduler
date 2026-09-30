<?php

namespace App\Data;

use App\Enums\EmailTemplateTriggerType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class EmailTemplateTriggerData extends Data
{
    public function __construct(
        public EmailTemplateTriggerType $type,
        public bool $enabled,
        public string $summary,
    ) {}
}
