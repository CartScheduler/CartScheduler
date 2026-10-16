<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserAvailability;
use Illuminate\Database\Seeder;

class UserAvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        User::all()->each(
            fn (User $user) => UserAvailability::factory()
                ->for($user)
                ->create()
        );
    }
}
