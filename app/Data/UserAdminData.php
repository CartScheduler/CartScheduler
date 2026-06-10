<?php

namespace App\Data;

use App\Enums\Appointment;
use App\Enums\MaritalStatus;
use App\Enums\ServingAs;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UserAdminData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $role,
        #[LiteralTypeScriptType('male | female | undefined')]
        public string|Optional $gender,
        public string|Optional $mobile_phone,
        public int|Optional $year_of_birth,
        public Appointment|Optional $appointment,
        public ServingAs|Optional $serving_as,
        public MaritalStatus|Optional $marital_status,
        public string|Optional $responsible_brother,
        /** @var Collection<\App\Data\UserVacationData> */
        public Collection|Optional $vacations,
        public AvailabilityData|Optional $availability,
        public SpouseAdminData|Optional $spouse,
        /** @var Collection<int>|Optional */
        public Collection|Optional $selectedLocations,
        public bool $is_enabled = true,
        public bool $is_unrestricted = false,
        public bool $has_logged_in = false,
    ) {
    }

    /** @noinspection PhpUnused */
    public static function fromModel(User $user): self
    {
        $selectedLocations = $user->relationLoaded('rosterLocations')
            ? $user->rosterLocations->map(fn($location) => $location->id)
            : Optional::create();

        $vacations = $user->relationLoaded('vacations')
            ? UserVacationData::collect($user->vacations)
            : Optional::create();

        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $user->role,
            gender: $user->gender ?? Optional::create(),
            mobile_phone: $user->mobile_phone ?? Optional::create(),
            year_of_birth: $user->year_of_birth ?? Optional::create(),
            appointment: $user->appointment ? Appointment::from($user->appointment) : Optional::create(),
            serving_as: $user->serving_as ? ServingAs::from($user->serving_as) : Optional::create(),
            marital_status: $user->marital_status ? MaritalStatus::from($user->marital_status) : Optional::create(),
            responsible_brother: $user->responsible_brother ?? Optional::create(),
            vacations: $vacations,
            availability: $user->relationLoaded('availability') ? AvailabilityData::from($user->availability) : Optional::create(),
            spouse: $user->relationLoaded('spouse') && $user->spouse ? SpouseAdminData::from($user->spouse) : Optional::create(),
            selectedLocations: $selectedLocations,
            is_enabled: $user->is_enabled,
            is_unrestricted: $user->is_unrestricted,
            has_logged_in: $user->has_logged_in,
        );
    }
}
