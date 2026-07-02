<?php

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function () {
    $this->plan = Plan::updateOrCreate(
        ['key' => 'nettur'],
        ['name' => 'Nettur', 'price_monthly' => 2490, 'price_yearly' => 2075, 'max_employees' => 15, 'is_active' => true, 'sort_order' => 1],
    );
    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create(['company_id' => $this->company->id]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);
});

it('cancels a paid subscription at period end, keeping access until the period ends', function () {
    $sub = Subscription::factory()->paid()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'current_period_ends_at' => now()->addWeek(),
        'verifone_stored_credential_ref' => 'scref-1',
    ]);

    $this->actingAs($this->manager)
        ->postJson('/api/manager/billing/cancel')
        ->assertOk()
        ->assertJsonPath('data.status', 'active'); // still active until period end

    $sub->refresh();
    expect($sub->status)->toBe(SubscriptionStatus::Active);
    expect($sub->canceled_at)->not->toBeNull();
    expect($sub->pendingCancellation())->toBeTrue();
    expect($sub->managerCanWrite())->toBeTrue();
});

it('cancels a trialing subscription immediately', function () {
    $sub = Subscription::factory()->trialing()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
    ]);

    $this->actingAs($this->manager)
        ->postJson('/api/manager/billing/cancel')
        ->assertOk()
        ->assertJsonPath('data.status', 'canceled');

    expect($sub->refresh()->status)->toBe(SubscriptionStatus::Canceled);
});

it('finalises a canceled subscription once its period ends', function () {
    $sub = Subscription::factory()->paid()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'current_period_ends_at' => now()->subDay(),
        'canceled_at' => now()->subDays(2),
    ]);

    $this->artisan('subscriptions:tick')->assertSuccessful();

    expect($sub->refresh()->status)->toBe(SubscriptionStatus::Canceled);
});

it('expires a past-due subscription after the dunning window', function () {
    $sub = Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => SubscriptionStatus::PastDue,
        'current_period_ends_at' => now()->subDays(8),
    ]);

    $this->artisan('subscriptions:tick')->assertSuccessful();

    expect($sub->refresh()->status)->toBe(SubscriptionStatus::Expired);
});

it('keeps a recently past-due subscription within the dunning window', function () {
    $sub = Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => SubscriptionStatus::PastDue,
        'current_period_ends_at' => now()->subDay(),
    ]);

    $this->artisan('subscriptions:tick')->assertSuccessful();

    expect($sub->refresh()->status)->toBe(SubscriptionStatus::PastDue);
});
