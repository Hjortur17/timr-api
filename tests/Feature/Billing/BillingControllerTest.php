<?php

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function () {
    // The 06_21 backfill migration already seeds a default `nettur` plan, so
    // update-or-create rather than create to avoid a unique-key clash.
    $this->plan = Plan::updateOrCreate(
        ['key' => 'nettur'],
        ['name' => 'Nettur', 'price_monthly' => 2490, 'price_yearly' => 2075, 'max_employees' => 15, 'is_active' => true, 'sort_order' => 1],
    );
    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create(['company_id' => $this->company->id]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);
});

function billingSub(int $companyId, int $planId): \Database\Factories\SubscriptionFactory
{
    return Subscription::factory()->state(['company_id' => $companyId, 'plan_id' => $planId]);
}

it('returns the billing overview with subscription, plans and active employee count', function () {
    billingSub($this->company->id, $this->plan->id)->trialing()->create();
    Employee::factory()->count(3)->create(['company_id' => $this->company->id]);
    Employee::factory()->inactive()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->manager)
        ->getJson('/api/manager/billing')
        ->assertOk()
        ->assertJsonPath('data.active_employees', 3)
        ->assertJsonPath('data.subscription.plan.key', 'nettur')
        ->assertJsonPath('data.subscription.plan.max_employees', 15)
        ->assertJsonPath('data.payment_method', null)
        ->assertJsonCount(0, 'data.invoices')
        ->assertJsonFragment(['key' => 'nettur', 'max_employees' => 15]);
});

it('reaches billing even when the subscription is fully expired', function () {
    billingSub($this->company->id, $this->plan->id)->expired()->create();

    $this->actingAs($this->manager)
        ->getJson('/api/manager/billing')
        ->assertOk();
});

it('records a chosen plan locally during the trial without charging', function () {
    $sub = billingSub($this->company->id, $this->plan->id)->trialing()->create();
    $allur = Plan::factory()->create(['key' => 'allur-pakkinn', 'name' => 'Allur pakkinn']);

    $this->actingAs($this->manager)
        ->postJson('/api/manager/billing/plan', ['plan_key' => 'allur-pakkinn', 'billing_period' => 'yearly'])
        ->assertOk()
        ->assertJsonPath('data.plan.key', 'allur-pakkinn')
        ->assertJsonPath('data.billing_period', 'yearly');

    expect($sub->refresh()->plan_id)->toBe($allur->id);
    expect($sub->status)->toBe(SubscriptionStatus::Trialing); // still trialing, no charge
});

it('rejects an unknown or inactive plan', function () {
    billingSub($this->company->id, $this->plan->id)->trialing()->create();
    Plan::factory()->create(['key' => 'old', 'is_active' => false]);

    $this->actingAs($this->manager)
        ->postJson('/api/manager/billing/plan', ['plan_key' => 'old', 'billing_period' => 'monthly'])
        ->assertStatus(422);
});

it('cancels the subscription', function () {
    $sub = billingSub($this->company->id, $this->plan->id)->trialing()->create();

    $this->actingAs($this->manager)
        ->postJson('/api/manager/billing/cancel')
        ->assertOk()
        ->assertJsonPath('data.status', 'canceled');

    expect($sub->refresh()->status)->toBe(SubscriptionStatus::Canceled);
    expect($sub->canceled_at)->not->toBeNull();
});

it('returns a graceful not-configured response when starting checkout before Verifone is wired', function () {
    billingSub($this->company->id, $this->plan->id)->trialing()->create();

    $this->actingAs($this->manager)
        ->postJson('/api/manager/billing/checkout-session', [])
        ->assertStatus(503)
        ->assertJsonPath('reason', 'billing_not_configured');
});

it('forbids non-managers from reaching billing', function () {
    billingSub($this->company->id, $this->plan->id)->trialing()->create();
    $employeeUser = User::factory()->create(['company_id' => $this->company->id]);
    Employee::create([
        'company_id' => $this->company->id,
        'user_id' => $employeeUser->id,
        'name' => $employeeUser->name,
    ]);

    $this->actingAs($employeeUser)
        ->getJson('/api/manager/billing')
        ->assertStatus(403);
});
