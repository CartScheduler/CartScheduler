<?php

namespace App\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class LocationAdminData extends Data
{
    public function __construct(
        public int|Optional $id,
        public string $name,
        public ?string $description = null,
        public ?string $clean_description = null,
        public ?int $min_volunteers = null,
        public ?int $max_volunteers = null,
        public bool $requires_brother = false,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public bool $is_enabled = true,
        public ?int $sort_order = null,
        /** @var Collection<\App\Data\ShiftAdminData> */
        public Collection $shifts = new Collection,
    ) {
        if ($this->description !== null) {
            $this->clean_description = strip_tags($this->description);
        }
    }
}
