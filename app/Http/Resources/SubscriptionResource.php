<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Subscription */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status,
            'billing_period' => $this->billing_period,
            'trial_ends_at' => $this->trial_ends_at,
            'current_period_ends_at' => $this->current_period_ends_at,
            'grace_ends_at' => $this->grace_ends_at,
            'billing_email' => $this->billing_email,
            'trial_active' => $this->trialActive(),
            'in_grace' => $this->inGrace(),
            'has_expired' => $this->hasExpired(),
            'manager_can_write' => $this->managerCanWrite(),
            'employee_can_work' => $this->employeeCanWork(),
            'plan' => $this->whenLoaded('plan', fn () => new PlanResource($this->plan)),
        ];
    }
}
