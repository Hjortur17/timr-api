<?php

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Subscription;
use App\Services\SubscriptionService;

it('gives legacy companies a default-plan 30-day trial', function () {
    $legacy = Company::factory()->count(3)->create();

    $this->artisan('subscriptions:backfill')->assertSuccessful();

    foreach ($legacy as $company) {
        $subscription = Subscription::with('plan')->where('company_id', $company->id)->firstOrFail();
        expect($subscription->status)->toBe(SubscriptionStatus::Trialing);
        expect($subscription->plan->key)->toBe(SubscriptionService::DEFAULT_PLAN_KEY);
        expect($subscription->trial_ends_at->startOfDay()->equalTo(
            now()->addDays(SubscriptionService::TRIAL_DAYS)->startOfDay()
        ))->toBeTrue();
    }
});

it('does not touch companies that already have a subscription', function () {
    $company = Company::factory()->create();
    $existing = Subscription::factory()->paid()->create(['company_id' => $company->id]);

    $this->artisan('subscriptions:backfill')->assertSuccessful();

    expect(Subscription::where('company_id', $company->id)->count())->toBe(1);
    expect($existing->refresh()->status)->toBe(SubscriptionStatus::Active);
});
