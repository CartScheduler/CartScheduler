<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Note, this depends on both a location with shifts and a user being created.
 */
class ShiftUserFactory extends Factory
{
    public function definition(): array
    {
        $shift = Shift::inRandomOrder()->first();

        return [
            'shift_id' => $shift->id,
            'user_id' => User::inRandomOrder()->first(['id'])->id,
            'shift_date' => $shift->available_from
                ? $shift->available_from->addDay()
                : date('Y-m-d', strtotime('tomorrow')),
        ];
    }
}
