<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Shift;
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
            'shift_id' => Shift::factory(),
            'name' => fake()->randomElement(['2-2-3 Rotation', '5-2 Standard', '4-3 Rotation']),
            'description' => fake()->optional()->sentence(),
            'pattern' => '2-2-3',
            'blocks' => [2, 2, 3],
            'cycle_length_days' => 7,
        ];
    }

    public function twoTwoThree(): static
    {
        return $this->state(fn () => [
            'name' => '2-2-3 Rotation',
            'pattern' => '2-2-3',
            'blocks' => [2, 2, 3],
            'cycle_length_days' => 7,
        ]);
    }

    public function fiveFiveFour(): static
    {
        return $this->state(fn () => [
            'name' => '5-5-4 Rotation',
            'pattern' => '5-5-4',
            'blocks' => [5, 5, 4],
            'cycle_length_days' => 14,
        ]);
    }

    public function fiveTwo(): static
    {
        return $this->state(fn () => [
            'name' => '5-2 Standard',
            'pattern' => '5-2',
            'blocks' => [5, 2],
            'cycle_length_days' => 7,
        ]);
    }

    public function fourThree(): static
    {
        return $this->state(fn () => [
            'name' => '4-3 Rotation',
            'pattern' => '4-3',
            'blocks' => [4, 3],
            'cycle_length_days' => 7,
        ]);
    }
}
