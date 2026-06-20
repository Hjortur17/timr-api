<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\VacationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VacationRequest> */
class VacationRequestFactory extends Factory
{
    protected $model = VacationRequest::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 week', '+4 weeks');
        $end = (clone $start)->modify('+4 days');

        return [
            'company_id' => Company::factory(),
            'employee_id' => Employee::factory(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'working_days_count' => 5,
            'status' => 'pending',
            'type' => 'holiday',
            'employee_note' => null,
            'reviewer_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'cancelled_at' => null,
        ];
    }
}
