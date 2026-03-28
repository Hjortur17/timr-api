<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ShiftTemplateEntry */
class ShiftTemplateEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shift_template_id' => $this->shift_template_id,
            'shift_id' => $this->shift_id,
            'employee_id' => $this->employee_id,
            'day_offset' => $this->day_offset,
            'shift' => new ShiftResource($this->whenLoaded('shift')),
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
