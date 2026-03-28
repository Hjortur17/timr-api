<?php

namespace App\Http\Requests\ShiftTemplate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShiftTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cycle_length_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'entries' => ['nullable', 'array'],
            'entries.*.shift_id' => ['required_with:entries', 'exists:shifts,id,company_id,'.$companyId],
            'entries.*.employee_id' => ['nullable', 'exists:employees,id,company_id,'.$companyId],
            'entries.*.day_offset' => ['required_with:entries', 'integer', 'min:0'],
        ];
    }
}
