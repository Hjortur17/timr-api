<?php

namespace App\Http\Requests\Shift;

use App\Enums\VacationRequestStatus;
use App\Models\VacationRequest;
use Illuminate\Contracts\Validation\Validator;
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $employeeId = $this->input('employee_id');
            $date = $this->input('date');

            if (! $employeeId || ! $date) {
                return;
            }

            if (VacationRequest::query()
                ->where('employee_id', $employeeId)
                ->where('status', VacationRequestStatus::Approved->value)
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->exists()
            ) {
                $validator->errors()->add(
                    'date',
                    'This employee is on approved vacation on this date.',
                );
            }
        });
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
