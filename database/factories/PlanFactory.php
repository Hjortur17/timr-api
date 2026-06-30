<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'key' => Str::slug($name),
            'name' => ucfirst($name),
            'price_monthly' => fake()->numberBetween(2000, 11000),
            'price_yearly' => fake()->numberBetween(2000, 11000),
            'max_employees' => fake()->randomElement([15, 40, 100]),
            'features' => null,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 3),
        ];
    }
}
