<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UserData extends Data
{
    public function __construct(
        public string $name,
        public int|Optional $id,
        public string|Optional $uuid,
        public string $gender,
        public string $mobile_phone,
        public string|Optional $email,
        public int|Optional $shift_id,
        public string|Optional $shift_date,
        public bool|Optional $is_unrestricted,
        public string|Optional $last_shift_date,
        public string|Optional $last_shift_start_time,
    ) {
    }

    /** @noinspection PhpUnused */
//    public static function fromModel(User $user): UserData
//    {
//        return new self(
//            name: $user->name,
//            id: $user->id ?? Optional::create(),
//            uuid: $user->uuid ?? Optional::create(),
//            gender: $user->gender,
//            mobile_phone: $user->mobile_phone,
//            email: $user->email ?? Optional::create(),
//            shift_id: $user->pivot->shift_id ?? null,
//            shift_date: $user->pivot->shift_date ?? null,
//            is_unrestricted: $user->is_unrestricted ?? Optional::create(),
//            last_shift_date: $user->last_shift_date ?? Optional::create(),
//            last_shift_start_time: $user->last_shift_start ?? Optional::create(),
//        );
//    }
}
