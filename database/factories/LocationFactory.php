<?php

/** @noinspection PhpUnusedParameterInspection */

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->city(),
            'description' => fake()->sentences(asText: true),
            'min_volunteers' => fake()->randomElement(['1', '2', '3', '4', '5']),
            'max_volunteers' => 5,
            'requires_brother' => fake()->boolean(),
            'latitude' => (string) fake()->randomFloat(6, -36, -37),
            'longitude' => (string) fake()->randomFloat(6, 143, 144),
            'is_enabled' => true,
        ];
    }

    public function threeVolunteers(): self
    {
        return $this->state(fn (array $attributes) => [
            'max_volunteers' => 3,
        ]);
    }

    public function requiresBrother(): self
    {
        return $this->state(fn (array $attributes) => [
            'requires_brother' => true,
        ]);
    }

    public function allPublishers(): self
    {
        return $this->state(fn (array $attributes) => [
            'requires_brother' => false,
        ]);
    }
}
