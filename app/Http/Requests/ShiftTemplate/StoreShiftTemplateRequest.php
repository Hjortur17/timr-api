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
            'shift_id' => ['required', 'exists:shifts,id,company_id,'.$companyId],
            'pattern' => ['required', 'string', 'in:2-2-3,5-5-4,5-2,4-3,custom'],
            'blocks' => ['required', 'array', 'min:1'],
            'blocks.*' => ['required', 'integer', 'min:1'],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'exists:employees,id,company_id,'.$companyId],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shift_id.exists' => 'The selected shift does not belong to your company.',
            'employee_ids.*.exists' => 'The selected employee does not belong to your company.',
        ];
    }
}
