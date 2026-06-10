<?php

namespace App\Data;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\Hidden;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use stdClass;

#[TypeScript]
class UserShiftData extends Data
{
    public function __construct(
        #[Hidden]
        public Carbon $shift_date,
        #[Hidden]
        public int $shift_id,
        public int $volunteer_id,
        public int $location_id,
        public string $location_name,
        public string $start_time,
        public int $max_volunteers,
        public ?string $available_from = null,
        public ?string $available_to = null,
    ) {
    }

    /** @noinspection PhpUnused */
    public static function fromDb(stdClass $data): self
    {
        return new self(
            shift_date: Carbon::parse($data->shift_date),
            shift_id: $data->shift_id,
            volunteer_id: $data->volunteer_id,
            location_id: $data->location_id,
            location_name: $data->location_name,
            start_time: $data->start_time,
            max_volunteers: $data->max_volunteers,
            available_from: $data->available_from ? Carbon::parse($data->available_from)->toDateString() : null,
            available_to: $data->available_to ? Carbon::parse($data->available_to)->toDateString() : null,
        );
    }
}
