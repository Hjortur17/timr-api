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
        'verifone_checkout_id',
        'verifone_reuse_token',
        'verifone_token_scope',
        'verifone_stored_credential_ref',
        'verifone_scheme_reference',
        'verifone_payment_contract_id',
        'payment_sequence',
        'last_charge_at',
        'billing_email',
        'canceled_at',
    ];

    /**
     * Opaque Verifone credential/reference columns must never leak to the client.
     *
     * @var list<string>
     */
    protected $hidden = [
        'verifone_reference',
        'verifone_checkout_id',
        'verifone_reuse_token',
        'verifone_token_scope',
        'verifone_stored_credential_ref',
        'verifone_scheme_reference',
        'verifone_payment_contract_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'billing_period' => BillingPeriod::class,
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'last_charge_at' => 'datetime',
            'payment_sequence' => 'integer',
            'canceled_at' => 'datetime',
            // Encrypted at rest — this is the handle that authorizes recurring charges.
            'verifone_reuse_token' => 'encrypted',
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
     * Number of active employees in the company — the seats actually in use.
     * Bypasses the Employee company scope so it works outside an auth context
     * (e.g. webhooks).
     */
    public function activeEmployeeCount(): int
    {
        return Employee::withoutGlobalScopes()
            ->where('company_id', $this->company_id)
            ->where('is_active', true)
            ->count();
    }

    /**
     * Whether the company has reached the employee cap of its current plan.
     * Every plan carries an explicit cap; the null guard only covers a missing plan relation.
     */
    public function atEmployeeLimit(): bool
    {
        $max = $this->plan?->max_employees;

        return $max !== null && $this->activeEmployeeCount() >= $max;
    }

    /**
     * Paid subscription (driven by Verifone later).
     */
    public function isPaid(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }

    /**
     * Whether we hold a Verifone reuse token we can charge against for
     * merchant-initiated recurring payments. The stored-credential reference is
     * established on the first charge; the token is the handle we always need.
     */
    public function canChargeRecurring(): bool
    {
        return $this->verifone_reuse_token !== null;
    }

    /**
     * Cancellation was requested but access continues until the current period
     * ends (cancel-at-period-end).
     */
    public function pendingCancellation(): bool
    {
        return $this->canceled_at !== null && $this->status === SubscriptionStatus::Active;
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
