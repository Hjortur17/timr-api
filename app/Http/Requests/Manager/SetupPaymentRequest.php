<?php

namespace App\Http\Requests\Manager;

use Illuminate\Foundation\Http\FormRequest;

class SetupPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The exact shape is defined by Verifone's hosted payment flow; these fields
     * are optional placeholders for the card metadata the gateway returns.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'brand' => ['nullable', 'string', 'max:50'],
            'last4' => ['nullable', 'string', 'size:4'],
            'exp_month' => ['nullable', 'integer', 'between:1,12'],
            'exp_year' => ['nullable', 'integer', 'min:2024'],
        ];
    }
}
