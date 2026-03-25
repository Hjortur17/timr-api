<?php

namespace App\Http\Requests\Shift;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShiftAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $shiftId = $this->input('shift_id');
        $employeeId = $this->input('employee_id');

        return [
            'shift_id' => [
                'required',
                'integer',
                "exists:shifts,id,company_id,{$companyId}",
            ],
            'employee_id' => [
                'required',
                'integer',
                "exists:employees,id,company_id,{$companyId}",
            ],
            'date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('employee_shift', 'date')
                    ->where('shift_id', $shiftId)
                    ->where('employee_id', $employeeId),
            ],
            'published' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shift_id.exists' => 'The selected shift does not belong to your company.',
            'employee_id.exists' => 'The selected employee does not belong to your company.',
            'date.unique' => 'This shift is already assigned to this employee on this date.',
        ];
    }
}
