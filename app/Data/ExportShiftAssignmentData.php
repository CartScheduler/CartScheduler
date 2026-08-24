<?php

namespace App\Data;

use App\Models\ShiftUser;
use Spatie\LaravelData\Data;

class ExportShiftAssignmentData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $mobile_phone,
        public string $shift_date,
        public string $start_time,
        public string $end_time,
        public string $location,
    ) {
    }

    public static function fromShiftUser(ShiftUser $shiftUser): self
    {
        return new self(
            name: $shiftUser->user->name,
            email: $shiftUser->user->email,
            mobile_phone: $shiftUser->user->mobile_phone,
            shift_date: $shiftUser->shift_date,
            start_time: $shiftUser->shift->start_time,
            end_time: $shiftUser->shift->end_time,
            location: $shiftUser->shift->location->name,
        );
    }
}
