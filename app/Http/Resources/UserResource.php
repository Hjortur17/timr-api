<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'companies' => CompanyResource::collection($this->whenLoaded('companies')),
            'name' => $this->name,
            'email' => $this->email,
            'locale' => $this->locale,
            'is_active' => $this->is_active,
            'onboarding_step' => $this->onboarding_step,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
