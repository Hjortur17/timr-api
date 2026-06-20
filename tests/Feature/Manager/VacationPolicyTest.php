<?php

use App\Models\Company;
use App\Models\User;
use App\Models\VacationPolicy;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);
    $this->actingAs($this->manager);
});

it('auto-creates a policy with sensible defaults on first read', function () {
    expect(VacationPolicy::withoutGlobalScope('company')->count())->toBe(0);

    $this->getJson('/api/manager/vacation-policy')
        ->assertOk()
        ->assertJsonPath('data.default_days_per_year', 24)
        ->assertJsonPath('data.vacation_year_start_month', 5)
        ->assertJsonPath('data.vacation_year_start_day', 1)
        ->assertJsonPath('data.allow_carry_over', false)
        ->assertJsonPath('data.max_carry_over_days', null);

    expect(VacationPolicy::withoutGlobalScope('company')->count())->toBe(1);
});

it('reuses the existing policy on subsequent reads', function () {
    $this->getJson('/api/manager/vacation-policy')->assertOk();
    $this->getJson('/api/manager/vacation-policy')->assertOk();

    expect(VacationPolicy::withoutGlobalScope('company')->count())->toBe(1);
});

it('allows a manager to update the vacation policy', function () {
    $this->putJson('/api/manager/vacation-policy', [
        'default_days_per_year' => 28,
        'vacation_year_start_month' => 5,
        'vacation_year_start_day' => 1,
        'working_days' => [1, 2, 3, 4, 5],
        'allow_carry_over' => true,
        'max_carry_over_days' => 5,
    ])
        ->assertOk()
        ->assertJsonPath('data.default_days_per_year', 28)
        ->assertJsonPath('data.allow_carry_over', true)
        ->assertJsonPath('data.max_carry_over_days', 5);
});

it('allows a manager to configure open days and opening hours', function () {
    $this->putJson('/api/manager/vacation-policy', [
        'default_days_per_year' => 24,
        'vacation_year_start_month' => 5,
        'vacation_year_start_day' => 1,
        'working_days' => [1, 2, 3, 4, 5, 6],
        'opening_hours' => ['uniform' => true, 'from' => '09:00', 'to' => '17:00', 'days' => []],
        'allow_carry_over' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.working_days', [1, 2, 3, 4, 5, 6])
        ->assertJsonPath('data.opening_hours.uniform', true)
        ->assertJsonPath('data.opening_hours.from', '09:00');
});

it('validates open days and opening hours', function () {
    $this->putJson('/api/manager/vacation-policy', [
        'default_days_per_year' => 24,
        'vacation_year_start_month' => 5,
        'vacation_year_start_day' => 1,
        'working_days' => [1, 8],
        'allow_carry_over' => false,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('working_days.1');

    $this->putJson('/api/manager/vacation-policy', [
        'default_days_per_year' => 24,
        'vacation_year_start_month' => 5,
        'vacation_year_start_day' => 1,
        'working_days' => [1, 2, 3, 4, 5],
        'opening_hours' => ['uniform' => true, 'from' => 'nope', 'to' => '17:00'],
        'allow_carry_over' => false,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('opening_hours.from');
});

it('validates the vacation policy update', function () {
    $this->putJson('/api/manager/vacation-policy', [
        'default_days_per_year' => 28,
        'vacation_year_start_month' => 13,
        'vacation_year_start_day' => 1,
        'working_days' => [1, 2, 3, 4, 5],
        'allow_carry_over' => true,
        'max_carry_over_days' => 5,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('vacation_year_start_month');
});

it('isolates vacation policies between companies', function () {
    $this->putJson('/api/manager/vacation-policy', [
        'default_days_per_year' => 30,
        'vacation_year_start_month' => 5,
        'vacation_year_start_day' => 1,
        'working_days' => [1, 2, 3, 4, 5],
        'allow_carry_over' => false,
    ])->assertOk();

    $otherCompany = Company::factory()->create();
    $otherManager = User::factory()->create(['company_id' => $otherCompany->id]);
    $otherManager->companies()->attach($otherCompany, ['role' => 'owner']);
    $this->actingAs($otherManager);

    $this->getJson('/api/manager/vacation-policy')
        ->assertOk()
        ->assertJsonPath('data.default_days_per_year', 24);
});

it('prevents a non-manager from accessing the vacation policy', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->companies()->attach($this->company, ['role' => 'accountant']);
    $this->actingAs($user);

    $this->getJson('/api/manager/vacation-policy')->assertForbidden();
    $this->putJson('/api/manager/vacation-policy', [])->assertForbidden();
});
