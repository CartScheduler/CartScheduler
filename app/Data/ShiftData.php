<?php

namespace App\Data;

use App\Models\Shift;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ShiftData extends Data
{
    public function __construct(
        public int $id,
        public string $start_time,
        public string $end_time,
        public ?string $available_from,
        public ?string $available_to,
        /** @var Collection<\App\Data\UserData> */
        public Collection|Optional $volunteers,
        public ?LocationData $location,
        #[LiteralTypeScriptType('[boolean, boolean, boolean, boolean, boolean, boolean, boolean]')]
        public array $js_days = [],
    ) {
    }

    /** @noinspection PhpUnused */
    public static function fromModel(Shift $shift): self
    {
        return new self(
            id: $shift->id,
            start_time: $shift->start_time,
            end_time: $shift->end_time,
            available_from: $shift->available_from,
            available_to: $shift->available_to,
            volunteers: $shift->relationLoaded('users')
                ? UserData::collect($shift->users)
                : Optional::create(),
            location: $shift->relationLoaded('location') && $shift->location !== null
                ? LocationData::from($shift->location)
                : null,
            js_days: [ // These will map to JavaScript date() days
                       0 => $shift->day_sunday,
                       1 => $shift->day_monday,
                       2 => $shift->day_tuesday,
                       3 => $shift->day_wednesday,
                       4 => $shift->day_thursday,
                       5 => $shift->day_friday,
                       6 => $shift->day_saturday,
            ],
        );
    }
}
