<?php

namespace Database\Factories;

use App\Models\Building;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Building>
 */
class BuildingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Stavba '.fake()->lastName(),
            'address' => fake()->streetAddress().', Ljubljana',
            'rent_due_day' => 8,
        ];
    }
}
