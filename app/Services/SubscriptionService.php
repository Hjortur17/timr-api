<?php

namespace App\Services;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;

class SubscriptionService
{
    /** Length of the free trial granted to every new company. */
    public const TRIAL_DAYS = 30;

    /**
     * Grace period after the trial ends. During grace the manager is read-only
     * but employees can still clock in/out; once grace ends, everyone is locked.
     */
    public const GRACE_DAYS = 7;

    /** Plan assigned when the user did not pick a tier on the marketing site. */
    public const DEFAULT_PLAN_KEY = 'free';

    /**
     * Start a 30-day trial for a freshly created company, recording the tier the
     * user selected on the marketing site (falling back to the Free plan).
     */
    public function startTrial(Company $company, ?string $tier = null, ?string $billingPeriod = null): Subscription
    {
        $plan = $this->resolvePlan($tier);

        $period = BillingPeriod::tryFrom((string) $billingPeriod) ?? BillingPeriod::Monthly;

        $trialEndsAt = now()->addDays(self::TRIAL_DAYS);

        return Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trialing,
            'billing_period' => $period,
            'trial_ends_at' => $trialEndsAt,
            'grace_ends_at' => $trialEndsAt->copy()->addDays(self::GRACE_DAYS),
        ]);
    }

    /**
     * Ensure the Free plan exists (used as the default / backfill plan).
     */
    public function ensureFreePlan(): Plan
    {
        return Plan::firstOrCreate(
            ['key' => self::DEFAULT_PLAN_KEY],
            [
                'name' => 'Frír',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'is_active' => true,
                'sort_order' => 0,
            ],
        );
    }

    /**
     * Backfill a Free-plan trial for every company that predates the
     * subscription system (i.e. has no subscription yet). Idempotent.
     */
    public function backfillMissing(): int
    {
        $this->ensureFreePlan();

        $count = 0;

        Company::query()
            ->doesntHave('subscription')
            ->chunkById(200, function ($companies) use (&$count) {
                foreach ($companies as $company) {
                    $this->startTrial($company, self::DEFAULT_PLAN_KEY);
                    $count++;
                }
            });

        return $count;
    }

    private function resolvePlan(?string $tier): Plan
    {
        if ($tier !== null) {
            $plan = Plan::where('key', $tier)->where('is_active', true)->first();

            if ($plan !== null) {
                return $plan;
            }
        }

        return $this->ensureFreePlan();
    }
}
