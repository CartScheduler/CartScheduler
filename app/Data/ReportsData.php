<?php

namespace App\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ReportsData extends Data
{
    public function __construct(
        public int $id,
        public ?string $shift_date = null,
        public ?int $placements_count = null,
        public ?int $videos_count = null,
        public ?int $requests_count = null,
        public ?string $comments = null,
        public bool $shift_was_cancelled = false,
        #[LiteralTypeScriptType('Array<{id: number, name: {[lang: string]: string}, slug: {[lang: string]: string}}>')]
        public Collection $tags = new Collection,
        public ?ReportMetadataData $metadata = null,
    ) {}
}
