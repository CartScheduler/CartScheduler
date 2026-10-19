<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            LocationSeeder::class,
            ShiftSeeder::class,
            ShiftUserSeeder::class,
            UserAvailabilitySeeder::class,
            UserVacationSeeder::class,
        ]);
    }
}
