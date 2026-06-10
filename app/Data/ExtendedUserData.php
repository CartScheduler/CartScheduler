<?php

namespace App\Data;

use App\Enums\Appointment;
use App\Enums\MaritalStatus;
use App\Enums\ServingAs;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ExtendedUserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        #[LiteralTypeScriptType('male | female | undefined')]
        public string|Optional $gender,
        public string|Optional $mobile_phone,
        public string|Optional $email,
        public MaritalStatus|Optional $marital_status,
        public Appointment|Optional $appointment,
        public ServingAs|Optional $serving_as,
        public string|Optional $responsible_brother,
        public int|Optional $birth_year,
        public int|Optional $shift_id,
        public string|Optional $shift_date,
        public string|Optional $last_shift_date,
        public string|Optional $last_shift_start_time,
        public int|Optional $num_sundays,
        public int|Optional $num_mondays,
        public int|Optional $num_tuesdays,
        public int|Optional $num_wednesdays,
        public int|Optional $num_thursdays,
        public int|Optional $num_fridays,
        public int|Optional $num_saturdays,
        public int|Optional $filled_sundays,
        public int|Optional $filled_mondays,
        public int|Optional $filled_tuesdays,
        public int|Optional $filled_wednesdays,
        public int|Optional $filled_thursdays,
        public int|Optional $filled_fridays,
        public int|Optional $filled_saturdays,
        #[MapInputName('comments')]
        public string|Optional $availability_comments,
    ) {
    }
}
