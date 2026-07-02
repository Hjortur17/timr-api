<?php

namespace App\Http\Requests\Manager;

use Illuminate\Foundation\Http\FormRequest;

class CreateCheckoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `charge_now` lets the caller charge the first period immediately instead of
     * the default trial tokenization (card capture, no charge).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'charge_now' => ['nullable', 'boolean'],
        ];
    }
}
