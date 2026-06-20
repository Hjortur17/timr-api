<?php

namespace App\Http\Requests\Vacation;

use App\Enums\VacationRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVacationRequestRequest extends FormRequest
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
        $type = $this->input('type', VacationRequestType::Holiday->value);
        $deductible = $type === VacationRequestType::Holiday->value;

        return [
            'type' => ['nullable', Rule::enum(VacationRequestType::class)],
            // Deductible holiday cannot start in the past; other leave types (e.g. sick leave) may be back-dated.
            'start_date' => array_filter(['required', 'date', $deductible ? 'after_or_equal:today' : null]),
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
