<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesOpeningHours
{
    /**
     * Validation rules for an "opening time" object, optionally nested under a key.
     *
     * @return array<string, mixed>
     */
    protected function openingHoursRules(string $prefix = ''): array
    {
        $p = $prefix === '' ? '' : $prefix.'.';

        return [
            "{$p}days" => ['required', 'array', 'size:7'],
            "{$p}days.*" => ['boolean'],
            "{$p}time_mode" => ['required', Rule::in(['same', 'perday'])],
            "{$p}open" => ['nullable', 'date_format:H:i'],
            "{$p}close" => ['nullable', 'date_format:H:i'],
            "{$p}times" => ['nullable', 'array', 'size:7'],
            "{$p}times.*.open" => ['nullable', 'date_format:H:i'],
            "{$p}times.*.close" => ['nullable', 'date_format:H:i'],
            "{$p}exc" => ['nullable', 'array'],
            "{$p}exc.*.date" => ['required', 'date_format:Y-m-d'],
            "{$p}exc.*.label" => ['nullable', 'string', 'max:255'],
            "{$p}exc.*.mode" => ['required', Rule::in(['closed', 'hours'])],
            "{$p}exc.*.open" => ['nullable', 'date_format:H:i'],
            "{$p}exc.*.close" => ['nullable', 'date_format:H:i'],
        ];
    }
}
