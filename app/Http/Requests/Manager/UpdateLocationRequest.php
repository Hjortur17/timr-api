<?php

namespace App\Http\Requests\Manager;

use App\Http\Requests\Concerns\ValidatesOpeningHours;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocationRequest extends FormRequest
{
    use ValidatesOpeningHours;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $custom = $this->input('opening_hours_mode') === 'custom';

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geo_fence_radius' => ['nullable', 'integer', 'min:1'],
            'opening_hours_mode' => ['nullable', Rule::in(['global', 'custom'])],
            'opening_hours' => [$custom ? 'required' : 'nullable', 'array'],
        ];

        return $custom ? array_merge($rules, $this->openingHoursRules('opening_hours')) : $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        if (($data['opening_hours_mode'] ?? 'global') !== 'custom') {
            $data['opening_hours'] = null;
        }

        return $data;
    }
}
