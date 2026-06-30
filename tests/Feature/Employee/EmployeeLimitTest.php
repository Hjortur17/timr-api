<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function () {
    // The 06_21 backfill migration already seeds a default `nettur` plan; override
    // its cap to 2 for this test rather than creating a duplicate key.
    $this->plan = Plan::updateOrCreate(
        ['key' => 'nettur'],
        ['name' => 'Nettur', 'price_monthly' => 2490, 'price_yearly' => 2075, 'max_employees' => 2, 'is_active' => true, 'sort_order' => 1],
    );
    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create(['company_id' => $this->company->id]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);
    Subscription::factory()->trialing()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
    ]);
});

it('blocks adding an employee once the plan cap is reached', function () {
    Employee::factory()->count(2)->create(['company_id' => $this->company->id]);

    $this->actingAs($this->manager)
        ->postJson('/api/manager/employees', ['name' => 'Over Cap', 'email' => 'over@acme.com'])
        ->assertStatus(422)
        ->assertJsonPath('reason', 'employee_limit_reached');
});

it('allows adding an employee while under the plan cap', function () {
    Employee::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->manager)
        ->postJson('/api/manager/employees', ['name' => 'Under Cap', 'email' => 'under@acme.com'])
        ->assertCreated();
});

it('does not count inactive employees against the cap', function () {
    Employee::factory()->create(['company_id' => $this->company->id]);
    Employee::factory()->inactive()->count(3)->create(['company_id' => $this->company->id]);

    $this->actingAs($this->manager)
        ->postJson('/api/manager/employees', ['name' => 'Still Allowed', 'email' => 'still@acme.com'])
        ->assertCreated();
});
