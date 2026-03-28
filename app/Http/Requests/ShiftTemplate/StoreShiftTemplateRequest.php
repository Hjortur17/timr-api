<?php

namespace App\Http\Requests\ShiftTemplate;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cycle_length_days' => ['required', 'integer', 'min:1', 'max:365'],
            'entries' => ['nullable', 'array'],
            'entries.*.shift_id' => ['required_with:entries', 'exists:shifts,id,company_id,'.$companyId],
            'entries.*.employee_id' => ['nullable', 'exists:employees,id,company_id,'.$companyId],
            'entries.*.day_offset' => ['required_with:entries', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entries.*.day_offset.min' => 'Day offset must be 0 or greater.',
            'entries.*.shift_id.exists' => 'The selected shift does not belong to your company.',
            'entries.*.employee_id.exists' => 'The selected employee does not belong to your company.',
        ];
    }
}
