<?php

use App\Models\Company;
use App\Models\Plan;
use App\Services\SubscriptionService;

it('defaults a new company to the Nettur plan when no tier is chosen', function () {
    $company = Company::factory()->create();

    $subscription = app(SubscriptionService::class)->startTrial($company, null, null);

    expect($subscription->plan->key)->toBe('nettur');
    expect($subscription->plan->max_employees)->toBe(15);
});

it('honours an explicitly chosen tier', function () {
    Plan::factory()->create(['key' => 'thettur', 'max_employees' => 40]);
    $company = Company::factory()->create();

    $subscription = app(SubscriptionService::class)->startTrial($company, 'thettur', 'yearly');

    expect($subscription->plan->key)->toBe('thettur');
});
