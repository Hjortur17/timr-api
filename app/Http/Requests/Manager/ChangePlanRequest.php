<?php

namespace App\Http\Requests\Manager;

use App\Enums\BillingPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ChangePlanRequest extends FormRequest
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
            'plan_key' => ['required', 'string', Rule::exists('plans', 'key')->where('is_active', true)],
            'billing_period' => ['required', new Enum(BillingPeriod::class)],
        ];
    }
}
