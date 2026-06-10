<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Note, this depends on both a location with shifts and a user being created.
 */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        $shift  = Shift::inRandomOrder()->first();
        $userId = User::inRandomOrder()->first(['id'])->id;

        /** @noinspection JsonStandardCompliance */
        return [
            'shift_id'                 => $shift->id,
            'report_submitted_user_id' => $userId,
            'shift_date'               => $shift->available_from
                ? $shift->available_from->addDay()
                : date('Y-m-d', strtotime('tomorrow')),
            'placements_count'         => fake()->numberBetween(0, 10),
            'videos_count'             => fake()->numberBetween(0, 10),
            'requests_count'           => fake()->numberBetween(0, 10),
            'shift_was_cancelled'      => fake()->boolean,
            'comments'                 => fake()->sentence,
            'metadata'                 => [
                'shift_id'           => $shift->id,
                'associates'         => [
                    ['id' => 39, 'name' => 'Bro Ms. Marina Grimes'],
                    ['id' => 13, 'name' => 'Bro Virginia Schumm DDS']
                ],
                'shift_time'         => '12:00:00',
                'location_id'        => $shift->location_id,
                'location_name'      => 'Donald Land',
                'submitted_by_id'    => $userId,
                'submitted_by_name'  => 'Bro Admin',
                'submitted_by_email' => 'admin@example.com',
                'submitted_by_phone' => '15207560741',
            ],
        ];
    }
}
