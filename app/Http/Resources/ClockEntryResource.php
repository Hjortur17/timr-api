<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ClockEntry */
class ClockEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'shift_id' => $this->shift_id,
            'employee_id' => $this->employee_id,
            'is_extra' => $this->shift_id === null,
            'clocked_in_at' => $this->clocked_in_at,
            'clocked_out_at' => $this->clocked_out_at,
            'clock_in_lat' => $this->clock_in_lat,
            'clock_in_lng' => $this->clock_in_lng,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->clocked_in_at && $this->clocked_out_at) {
            $data['total_hours'] = round($this->clocked_in_at->diffInMinutes($this->clocked_out_at) / 60, 2);
        } else {
            $data['total_hours'] = null;
        }

        if ($this->relationLoaded('employee')) {
            $data['employee'] = [
                'id' => $this->employee->id,
                'name' => $this->employee->name,
                'email' => $this->employee->email,
            ];
        }

        if ($this->relationLoaded('shift') && $this->shift) {
            $data['shift'] = [
                'id' => $this->shift->id,
                'title' => $this->shift->title,
            ];
        }

        return $data;
    }
}
