<?php

use App\Models\Company;
use App\Models\Employee;
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

it('updates the billing email and round-trips it in the overview', function () {
    $sub = Subscription::factory()->state(['company_id' => $this->company->id, 'plan_id' => $this->plan->id])->trialing()->create();

    $this->actingAs($this->manager)
        ->postJson('/api/manager/billing/billing-email', ['billing_email' => 'bokhald@gledipinni.is'])
        ->assertOk()
        ->assertJsonPath('data.billing_email', 'bokhald@gledipinni.is');

    expect($sub->refresh()->billing_email)->toBe('bokhald@gledipinni.is');

    $this->actingAs($this->manager)
        ->getJson('/api/manager/billing')
        ->assertOk()
        ->assertJsonPath('data.subscription.billing_email', 'bokhald@gledipinni.is');
});

it('defaults the stored billing email to null until one is set', function () {
    Subscription::factory()->state(['company_id' => $this->company->id, 'plan_id' => $this->plan->id])->trialing()->create();

    $this->actingAs($this->manager)
        ->getJson('/api/manager/billing')
        ->assertOk()
        ->assertJsonPath('data.subscription.billing_email', null);
});

it('rejects an invalid billing email', function () {
    Subscription::factory()->state(['company_id' => $this->company->id, 'plan_id' => $this->plan->id])->trialing()->create();

    $this->actingAs($this->manager)
        ->postJson('/api/manager/billing/billing-email', ['billing_email' => 'not-an-email'])
        ->assertStatus(422);
});

it('forbids non-managers from updating the billing email', function () {
    Subscription::factory()->state(['company_id' => $this->company->id, 'plan_id' => $this->plan->id])->trialing()->create();
    $employeeUser = User::factory()->create(['company_id' => $this->company->id]);
    Employee::create([
        'company_id' => $this->company->id,
        'user_id' => $employeeUser->id,
        'name' => $employeeUser->name,
    ]);

    $this->actingAs($employeeUser)
        ->postJson('/api/manager/billing/billing-email', ['billing_email' => 'x@y.is'])
        ->assertStatus(403);
});
