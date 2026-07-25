<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;

class ExportUserShiftCountData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public int $shift_count,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            shift_count: (int) $user->shift_count,
        );
    }
}
