<?php

namespace App\Http\Requests\Vacation;

use App\Enums\VacationRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManagerVacationRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isManager();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('company_id', $this->user()->company_id),
            ],
            'type' => ['nullable', Rule::enum(VacationRequestType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
