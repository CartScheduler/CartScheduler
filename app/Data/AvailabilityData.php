<?php

namespace App\Data;

use App\Data\Casts\AvailabilityHoursCast;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AvailabilityData extends Data
{
    public function __construct(
        /** @var Collection<\App\Enums\AvailabilityHours> */
        public ?Collection $day_monday = null,
        /** @var Collection<\App\Enums\AvailabilityHours> */
        public ?Collection $day_tuesday = null,
        /** @var Collection<\App\Enums\AvailabilityHours> */
        public ?Collection $day_wednesday = null,
        /** @var Collection<\App\Enums\AvailabilityHours> */
        public ?Collection $day_thursday = null,
        /** @var Collection<\App\Enums\AvailabilityHours> */
        public ?Collection $day_friday = null,
        /** @var Collection<\App\Enums\AvailabilityHours> */
        public ?Collection $day_saturday = null,
        /** @var Collection<\App\Enums\AvailabilityHours> */
        public ?Collection $day_sunday = null,
        public int $num_mondays = 0,
        public int $num_tuesdays = 0,
        public int $num_wednesdays = 0,
        public int $num_thursdays = 0,
        public int $num_fridays = 0,
        public int $num_saturdays = 0,
        public int $num_sundays = 0,
        public ?string $comments = null,
    ) {
    }
}
