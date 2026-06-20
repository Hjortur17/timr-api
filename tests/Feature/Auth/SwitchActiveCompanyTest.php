<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;

it('switches the active company for a member of multiple companies', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $companyA->id]);
    $user->companies()->attach($companyA, ['role' => 'owner']);
    $user->companies()->attach($companyB, ['role' => 'owner']);

    $response = $this->actingAs($user)->patchJson('/api/auth/active-company', [
        'company_id' => $companyB->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.company_id', $companyB->id);

    $user->refresh();
    expect($user->company_id)->toBe($companyB->id);
});

it('flips data scoping to the new company after switching', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $companyA->id]);
    $user->companies()->attach($companyA, ['role' => 'owner']);
    $user->companies()->attach($companyB, ['role' => 'owner']);

    $employeeA = Employee::factory()->create(['company_id' => $companyA->id]);
    $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);

    $this->actingAs($user)->getJson('/api/manager/employees')
        ->assertOk()
        ->assertJsonPath('data.0.id', $employeeA->id)
        ->assertJsonCount(1, 'data');

    $this->actingAs($user)->patchJson('/api/auth/active-company', [
        'company_id' => $companyB->id,
    ])->assertOk();

    $this->actingAs($user->fresh())->getJson('/api/manager/employees')
        ->assertOk()
        ->assertJsonPath('data.0.id', $employeeB->id)
        ->assertJsonCount(1, 'data');
});

it('rejects switching to a company the user does not belong to', function () {
    $companyA = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $companyA->id]);
    $user->companies()->attach($companyA, ['role' => 'owner']);

    $this->actingAs($user)->patchJson('/api/auth/active-company', [
        'company_id' => $otherCompany->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['company_id']);

    expect($user->fresh()->company_id)->toBe($companyA->id);
});

it('requires a company_id', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->companies()->attach($company, ['role' => 'owner']);

    $this->actingAs($user)->patchJson('/api/auth/active-company', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_id']);
});

it('requires authentication to switch the active company', function () {
    $company = Company::factory()->create();

    $this->patchJson('/api/auth/active-company', [
        'company_id' => $company->id,
    ])->assertUnauthorized();
});
