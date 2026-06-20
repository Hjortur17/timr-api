<?php

namespace App\Http\Requests\Vacation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVacationPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'default_days_per_year' => ['required', 'integer', 'min:0', 'max:366'],
            'vacation_year_start_month' => ['required', 'integer', 'min:1', 'max:12'],
            'vacation_year_start_day' => ['required', 'integer', 'min:1', 'max:31'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'min:1', 'max:7', 'distinct'],
            'opening_hours' => ['nullable', 'array'],
            'opening_hours.uniform' => ['nullable', 'boolean'],
            'opening_hours.from' => ['nullable', 'date_format:H:i'],
            'opening_hours.to' => ['nullable', 'date_format:H:i'],
            'opening_hours.days' => ['nullable', 'array'],
            'opening_hours.days.*.from' => ['nullable', 'date_format:H:i'],
            'opening_hours.days.*.to' => ['nullable', 'date_format:H:i'],
            'allow_carry_over' => ['required', 'boolean'],
            'max_carry_over_days' => ['nullable', 'integer', 'min:0', 'max:366'],
        ];
    }
}
