<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function () {
    $this->plan = Plan::factory()->create();
    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create(['company_id' => $this->company->id]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);
});

function sub(int $companyId, int $planId): \Database\Factories\SubscriptionFactory
{
    return Subscription::factory()->state(['company_id' => $companyId, 'plan_id' => $planId]);
}

function makeEmployeeUser(Company $company): User
{
    $employeeUser = User::factory()->create(['company_id' => $company->id]);
    Employee::create([
        'company_id' => $company->id,
        'user_id' => $employeeUser->id,
        'name' => $employeeUser->name,
    ]);

    return $employeeUser;
}

// ─── Manager ────────────────────────────────────────────────────

it('lets a manager read and write while trialing', function () {
    sub($this->company->id, $this->plan->id)->trialing()->create();

    $this->actingAs($this->manager)->getJson('/api/manager/employees')->assertOk();
    $this->actingAs($this->manager)
        ->postJson('/api/manager/employees', ['name' => 'New Hire', 'email' => 'hire@acme.com'])
        ->assertCreated();
});

it('makes the manager read-only during grace (reads ok, writes 402)', function () {
    sub($this->company->id, $this->plan->id)->inGrace()->create();

    $this->actingAs($this->manager)->getJson('/api/manager/employees')->assertOk();

    $this->actingAs($this->manager)
        ->postJson('/api/manager/employees', ['name' => 'New Hire'])
        ->assertStatus(402)
        ->assertJsonPath('reason', 'subscription_inactive');
});

it('keeps the manager read-only once fully expired (reads ok, writes 402)', function () {
    sub($this->company->id, $this->plan->id)->expired()->create();

    $this->actingAs($this->manager)->getJson('/api/manager/employees')->assertOk();

    $this->actingAs($this->manager)
        ->postJson('/api/manager/employees', ['name' => 'New Hire'])
        ->assertStatus(402);
});

// ─── Employee ───────────────────────────────────────────────────

it('lets employees clock in during the grace window', function () {
    sub($this->company->id, $this->plan->id)->inGrace()->create();
    $employeeUser = makeEmployeeUser($this->company);

    $response = $this->actingAs($employeeUser)->postJson('/api/employee/clock-in', []);

    // Not asserting clock-in succeeds end-to-end (geofence/shift rules apply) —
    // only that the subscription gate does NOT block during grace.
    expect($response->status())->not->toBe(402);
});

it('blocks employees once fully expired', function () {
    sub($this->company->id, $this->plan->id)->expired()->create();
    $employeeUser = makeEmployeeUser($this->company);

    $this->actingAs($employeeUser)
        ->postJson('/api/employee/clock-in', [])
        ->assertStatus(402)
        ->assertJsonPath('reason', 'subscription_inactive');

    $this->actingAs($employeeUser)
        ->getJson('/api/employee/shifts')
        ->assertStatus(402);
});
