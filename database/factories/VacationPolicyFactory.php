<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\VacationPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VacationPolicy> */
class VacationPolicyFactory extends Factory
{
    protected $model = VacationPolicy::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'default_days_per_year' => 24,
            'vacation_year_start_month' => 5,
            'vacation_year_start_day' => 1,
            'allow_carry_over' => false,
            'max_carry_over_days' => null,
        ];
    }
}
