<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Company */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'kennitala' => $this->kennitala,
            'phone' => $this->phone,
            'address' => $this->address,
            'email' => $this->email,
            'locale' => $this->locale,
            'logo_url' => $this->logo_url,
            'role' => $this->whenPivotLoaded('company_user', fn () => $this->pivot->role),
        ];
    }
}
