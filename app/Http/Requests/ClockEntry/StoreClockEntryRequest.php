<?php

namespace App\Http\Requests\ClockEntry;

use Illuminate\Foundation\Http\FormRequest;

class StoreClockEntryRequest extends FormRequest
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
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'clocked_in_at' => ['required', 'date'],
            'clocked_out_at' => ['sometimes', 'nullable', 'date', 'after:clocked_in_at'],
        ];
    }
}
