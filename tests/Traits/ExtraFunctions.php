<?php

/**
 * Project: CartApp
 * Owner: Pixelated
 * Copyright: 2023
 */

namespace Tests\Traits;

use App\Models\ShiftUser;
use App\Models\User;

trait ExtraFunctions
{
    public function attachUserToShift(int $shiftId, User $user, string $date): void
    {
        ShiftUser::factory()->create([
            'shift_id' => $shiftId,
            'user_id' => $user->getKey(),
            'shift_date' => $date,
        ]);
    }
}
