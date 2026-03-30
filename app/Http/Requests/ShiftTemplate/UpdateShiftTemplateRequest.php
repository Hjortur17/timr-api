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
            'shift_id' => ['sometimes', 'exists:shifts,id,company_id,'.$companyId],
            'pattern' => ['sometimes', 'string', 'in:2-2-3,5-5-4,5-2,4-3,custom'],
            'blocks' => ['sometimes', 'array', 'min:1'],
            'blocks.*' => ['required', 'integer', 'min:1'],
            'employee_ids' => ['sometimes', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'exists:employees,id,company_id,'.$companyId],
        ];
    }
}
