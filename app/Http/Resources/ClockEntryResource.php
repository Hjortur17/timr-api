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
        return [
            'id' => $this->id,
            'shift_id' => $this->shift_id,
            'user_id' => $this->user_id,
            'clocked_in_at' => $this->clocked_in_at,
            'clocked_out_at' => $this->clocked_out_at,
            'clock_in_lat' => $this->clock_in_lat,
            'clock_in_lng' => $this->clock_in_lng,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
