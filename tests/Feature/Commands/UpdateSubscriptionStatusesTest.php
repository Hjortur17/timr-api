<?php

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;

beforeEach(function () {
    $this->plan = Plan::factory()->create();
});

function trial(int $planId, $trialEndsAt, $graceEndsAt): Subscription
{
    return Subscription::factory()->create([
        'company_id' => Company::factory(),
        'plan_id' => $planId,
        'status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => $trialEndsAt,
        'grace_ends_at' => $graceEndsAt,
    ]);
}

it('expires a trial once the grace window has elapsed', function () {
    $subscription = trial($this->plan->id, now()->subDays(8), now()->subDay());

    $this->artisan('subscriptions:tick')->assertSuccessful();

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Expired);
});

it('leaves a subscription trialing while still inside the grace window', function () {
    $subscription = trial($this->plan->id, now()->subDay(), now()->addDays(6));

    $this->artisan('subscriptions:tick')->assertSuccessful();

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Trialing);
});

it('leaves active trials untouched', function () {
    $subscription = trial($this->plan->id, now()->addDays(20), now()->addDays(27));

    $this->artisan('subscriptions:tick')->assertSuccessful();

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Trialing);
});
