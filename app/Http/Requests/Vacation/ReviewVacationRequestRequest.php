<?php

namespace App\Http\Requests\Vacation;

use Illuminate\Foundation\Http\FormRequest;

class ReviewVacationRequestRequest extends FormRequest
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
            'status' => ['required', 'string', 'in:approved,denied'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
