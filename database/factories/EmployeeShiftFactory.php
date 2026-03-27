<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployeeShift> */
class EmployeeShiftFactory extends Factory
{
    protected $model = EmployeeShift::class;

    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'employee_id' => Employee::factory(),
            'date' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'published' => false,
            'reminder_sent_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['published' => true]);
    }
}
