<?php

namespace App\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AvailableShiftsData extends Data
{
    public function __construct(
        #[LiteralTypeScriptType('{[date: string]: {[shift_id: number]: App.Data.UserShiftData[]}}')]
        public Collection $shifts,
        #[LiteralTypeScriptType('{[date: string]: App.Data.AvailableShiftMetaData}')]
        public Collection $freeShifts,
        /** @var Collection<\App\Data\LocationData> */
        public Collection $locations,
        public string $maxDateReservation,
    ) {
    }
}
