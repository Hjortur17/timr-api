<?php

namespace App\Http\Requests\ClockEntry;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClockEntryRequest extends FormRequest
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
            'clocked_in_at' => ['sometimes', 'date'],
            'clocked_out_at' => ['sometimes', 'nullable', 'date', 'after:clocked_in_at'],
        ];
    }
}
