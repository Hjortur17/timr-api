<?php

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscription> */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Trialing,
            'billing_period' => BillingPeriod::Monthly,
            'trial_ends_at' => now()->addDays(30),
            'current_period_ends_at' => null,
            'grace_ends_at' => now()->addDays(37),
            'verifone_reference' => null,
            'canceled_at' => null,
        ];
    }

    public function trialing(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays(30),
            'grace_ends_at' => now()->addDays(37),
        ]);
    }

    /**
     * Trial has ended but the grace window is still open: manager read-only,
     * employees can still clock in/out.
     */
    public function inGrace(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->subDay(),
            'grace_ends_at' => now()->addDays(6),
        ]);
    }

    /**
     * Fully expired: past the grace window — everyone locked out.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Expired,
            'trial_ends_at' => now()->subDays(8),
            'grace_ends_at' => now()->subDay(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => now()->subDays(8),
            'grace_ends_at' => now()->subDay(),
            'current_period_ends_at' => now()->addMonth(),
        ]);
    }
}
