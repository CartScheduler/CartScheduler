<?php

namespace Database\Factories;

use App\Models\UserVacation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class UserVacationFactory extends Factory
{
    protected $model = UserVacation::class;

    protected Collection $dayParts;

    /**
     * @throws \Exception
     */
    public function definition(): array
    {
        $startDate = CarbonImmutable::instance(fake()->dateTimeBetween('now', '+6 months'));

        return [
            'start_date' => $startDate,
            'end_date' => $startDate->add(fake()->numberBetween(1, 30), 'day'),
            'description' => fake()->sentence(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
