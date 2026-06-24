<?php

namespace App\Http\Requests\Manager;

use App\Http\Requests\Concerns\ValidatesOpeningHours;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOpeningHoursRequest extends FormRequest
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
        return $this->openingHoursRules();
    }
}
