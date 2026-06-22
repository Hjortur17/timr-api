<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'plan_id',
        'status',
        'billing_period',
        'trial_ends_at',
        'current_period_ends_at',
        'grace_ends_at',
        'verifone_reference',
        'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'billing_period' => BillingPeriod::class,
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Paid subscription (driven by Verifone later).
     */
    public function isPaid(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }

    /**
     * Inside the free trial window.
     */
    public function trialActive(): bool
    {
        return $this->status === SubscriptionStatus::Trialing
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    /**
     * Trial has ended but we're still inside the grace window. During grace the
     * manager is read-only while employees can still clock in/out.
     */
    public function inGrace(): bool
    {
        return ! $this->isPaid()
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isPast()
            && $this->grace_ends_at !== null
            && $this->grace_ends_at->isFuture();
    }

    /**
     * Fully expired — past the grace window with no paid plan. Everyone locked.
     */
    public function hasExpired(): bool
    {
        return ! $this->isPaid() && ! $this->trialActive() && ! $this->inGrace();
    }

    /**
     * Whether managers may perform write actions. Read access is always allowed
     * until fully expired (so they can reach the billing page).
     */
    public function managerCanWrite(): bool
    {
        return $this->isPaid() || $this->trialActive();
    }

    /**
     * Whether employees can still use the app (clock in/out). Allowed through
     * the grace window, blocked only once fully expired.
     */
    public function employeeCanWork(): bool
    {
        return $this->isPaid() || $this->trialActive() || $this->inGrace();
    }
}
