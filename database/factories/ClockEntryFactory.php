<?php

namespace Database\Factories;

use App\Models\ClockEntry;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClockEntry> */
class ClockEntryFactory extends Factory
{
    protected $model = ClockEntry::class;

    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'user_id' => User::factory(),
            'clocked_in_at' => now(),
            'clocked_out_at' => null,
            'clock_in_lat' => fake()->latitude(),
            'clock_in_lng' => fake()->longitude(),
        ];
    }

    public function clockedOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'clocked_out_at' => now()->addHours(8),
        ]);
    }
}
