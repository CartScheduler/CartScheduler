<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $locations = Location::all();
        $temporaryShift = false;

        foreach ($locations as $location) {
            Shift::factory()->create([
                'location_id' => $location->id,
                'start_time' => '09:00:00',
                'end_time' => '12:00:00',
                'available_from' => null,
                'available_to' => null,
            ]);
            Shift::factory()->create([
                'location_id' => $location->id,
                'start_time' => '12:00:00',
                'end_time' => '15:00:00',
                'available_from' => null,
                'available_to' => null,
            ]);
            Shift::factory()->create([
                'location_id' => $location->id,
                'start_time' => '15:00:00',
                'end_time' => '18:00:00',
                'available_from' => null,
                'available_to' => null,
            ]);
            if (! $temporaryShift) {
                $temporaryShift = true;
                Shift::factory()->create([
                    'location_id' => $location->id,
                    'start_time' => '18:00:00',
                    'end_time' => '21:00:00',
                    'available_from' => '2020-07-21 18:00:00',
                    'available_to' => '2020-07-21 21:00:00',
                ]);

            }
        }
    }
}
