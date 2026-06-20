<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\VacationPolicy */
class VacationPolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'default_days_per_year' => $this->default_days_per_year,
            'vacation_year_start_month' => $this->vacation_year_start_month,
            'vacation_year_start_day' => $this->vacation_year_start_day,
            'working_days' => $this->working_days ?? [1, 2, 3, 4, 5],
            'opening_hours' => $this->opening_hours,
            'allow_carry_over' => $this->allow_carry_over,
            'max_carry_over_days' => $this->max_carry_over_days,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
