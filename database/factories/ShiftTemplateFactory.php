<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ShiftTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShiftTemplate> */
class ShiftTemplateFactory extends Factory
{
    protected $model = ShiftTemplate::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->randomElement(['2-2-3 Rotation', '5-5-4 Rotation', 'Standard Week', 'Weekend Only']),
            'description' => fake()->optional()->sentence(),
            'cycle_length_days' => fake()->randomElement([7, 14, 28]),
        ];
    }

    public function twoTwoThree(): static
    {
        return $this->state(fn () => [
            'name' => '2-2-3 Rotation',
            'description' => 'Work 2, off 2, work 3 — then inverse. Every other weekend off.',
            'cycle_length_days' => 14,
        ]);
    }

    public function fiveFiveFour(): static
    {
        return $this->state(fn () => [
            'name' => '5-5-4 Rotation',
            'description' => '5 days on, 5 off, 4 on. Common in 12-hour continuous operations.',
            'cycle_length_days' => 14,
        ]);
    }

    public function standardWeek(): static
    {
        return $this->state(fn () => [
            'name' => 'Standard Week',
            'description' => 'Monday to Friday fixed shifts.',
            'cycle_length_days' => 7,
        ]);
    }
}
