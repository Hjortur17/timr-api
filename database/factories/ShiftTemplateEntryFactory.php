<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftTemplate;
use App\Models\ShiftTemplateEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShiftTemplateEntry> */
class ShiftTemplateEntryFactory extends Factory
{
    protected $model = ShiftTemplateEntry::class;

    public function definition(): array
    {
        return [
            'shift_template_id' => ShiftTemplate::factory(),
            'shift_id' => Shift::factory(),
            'employee_id' => null,
            'day_offset' => fake()->numberBetween(0, 13),
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn () => [
            'employee_id' => $employee->id,
        ]);
    }
}
