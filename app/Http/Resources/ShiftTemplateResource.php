<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ShiftTemplate */
class ShiftTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'description' => $this->description,
            'shift_id' => $this->shift_id,
            'pattern' => $this->pattern,
            'blocks' => $this->blocks,
            'cycle_length_days' => $this->cycle_length_days,
            'shift' => new ShiftResource($this->whenLoaded('shift')),
            'employees' => EmployeeResource::collection($this->whenLoaded('employees')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
