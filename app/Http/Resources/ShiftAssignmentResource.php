<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\EmployeeShift */
class ShiftAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shift_id' => $this->shift_id,
            'employee_id' => $this->employee_id,
            'date' => $this->date->toDateString(),
            'published' => $this->published,
            'published_date' => $this->published_date?->toDateString(),
            'published_employee_id' => $this->published_employee_id,
            'has_unpublished_changes' => $this->hasUnpublishedChanges(),
            'shift' => new ShiftResource($this->whenLoaded('shift')),
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
