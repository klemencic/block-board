<?php

namespace Database\Factories;

use App\Models\Apartment;
use App\Models\Building;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Apartment>
 */
class ApartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'label' => fake()->unique()->bothify('#?'),
            'floor' => fake()->numberBetween(0, 4),
            'area_m2' => fake()->randomElement([35, 48.5, 62, 75]),
            'rooms' => fake()->numberBetween(1, 4),
        ];
    }
}
