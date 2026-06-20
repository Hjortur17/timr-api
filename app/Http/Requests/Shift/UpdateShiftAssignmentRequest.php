<?php

namespace App\Http\Requests\Shift;

use App\Enums\VacationRequestStatus;
use App\Models\EmployeeShift;
use App\Models\VacationRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShiftAssignmentRequest extends FormRequest
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
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'employee_id' => ['sometimes', 'integer', 'exists:employees,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $assignment = $this->route('shiftAssignment');

            if (! $assignment instanceof EmployeeShift) {
                return;
            }

            $employeeId = $this->input('employee_id', $assignment->employee_id);
            $date = $this->input('date', $assignment->date?->format('Y-m-d'));

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
}
