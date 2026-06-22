<?php

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('creates a company for a user without one', function () {
    $user = User::withoutGlobalScope('company')->create([
        'name' => 'John',
        'email' => 'john@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->actingAs($user)->postJson('/api/auth/company', [
        'name' => 'Acme Corp',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Company created successfully.')
        ->assertJsonPath('data.name', 'John');

    $user->refresh();
    expect($user->company_id)->not->toBeNull();
    expect($user->isManager())->toBeTrue();
    expect($user->companyRole()->value)->toBe('owner');
    expect(Company::count())->toBe(1);
    expect(Company::first()->name)->toBe('Acme Corp');
});

it('starts a 30-day trial on the selected tier when creating a company', function () {
    $user = User::withoutGlobalScope('company')->create([
        'name' => 'John',
        'email' => 'john@example.com',
        'password' => bcrypt('password123'),
    ]);

    $this->actingAs($user)->postJson('/api/auth/company', [
        'name' => 'Acme Corp',
        'tier' => 'nettur',
        'billing_period' => 'yearly',
    ])->assertCreated();

    $subscription = Subscription::with('plan')->firstOrFail();

    expect($subscription->status)->toBe(SubscriptionStatus::Trialing);
    expect($subscription->plan->key)->toBe('nettur');
    expect($subscription->billing_period->value)->toBe('yearly');
    expect($subscription->trial_ends_at->isToday() || $subscription->trial_ends_at->isFuture())->toBeTrue();
    expect(
        $subscription->trial_ends_at->startOfDay()
            ->equalTo(now()->addDays(SubscriptionService::TRIAL_DAYS)->startOfDay())
    )->toBeTrue();
});

it('falls back to the default plan when no tier is selected', function () {
    $user = User::withoutGlobalScope('company')->create([
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'password' => bcrypt('password123'),
    ]);

    $this->actingAs($user)->postJson('/api/auth/company', [
        'name' => 'Beta Corp',
    ])->assertCreated();

    $subscription = Subscription::with('plan')->firstOrFail();

    expect($subscription->plan->key)->toBe(SubscriptionService::DEFAULT_PLAN_KEY);
    expect($subscription->billing_period->value)->toBe('monthly');
});

it('falls back to the default plan when an unknown tier is selected', function () {
    $user = User::withoutGlobalScope('company')->create([
        'name' => 'Joe',
        'email' => 'joe@example.com',
        'password' => bcrypt('password123'),
    ]);

    $this->actingAs($user)->postJson('/api/auth/company', [
        'name' => 'Gamma Corp',
        'tier' => 'does-not-exist',
    ])->assertCreated();

    expect(Subscription::with('plan')->firstOrFail()->plan->key)
        ->toBe(SubscriptionService::DEFAULT_PLAN_KEY);
});

it('rejects company creation when user already has a company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->companies()->attach($company, ['role' => 'owner']);

    $this->actingAs($user)->postJson('/api/auth/company', [
        'name' => 'Another Corp',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['company']);
});

it('fails company creation with missing name', function () {
    $user = User::withoutGlobalScope('company')->create([
        'name' => 'John',
        'email' => 'john@example.com',
        'password' => bcrypt('password123'),
    ]);

    $this->actingAs($user)->postJson('/api/auth/company', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('requires authentication to create a company', function () {
    $this->postJson('/api/auth/company', [
        'name' => 'Acme Corp',
    ])->assertUnauthorized();
});
