<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shift> */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+30 days');
        $end = (clone $start)->modify('+8 hours');

        return [
            'company_id' => Company::factory(),
            'title' => fake()->randomElement(['Morning Shift', 'Afternoon Shift', 'Night Shift', 'Weekend Shift']),
            'start_time' => $start,
            'end_time' => $end,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
