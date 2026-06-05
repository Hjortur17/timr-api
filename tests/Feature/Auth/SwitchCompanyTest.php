<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;

it('switches the active company for a multi-company user', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $user = User::factory()->create(['company_id' => $companyA->id]);
    $user->companies()->attach($companyA, ['role' => 'owner']);
    $user->companies()->attach($companyB, ['role' => 'admin']);

    $response = $this->actingAs($user)->patchJson('/api/auth/active-company', [
        'company_id' => $companyB->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Active company switched successfully.')
        ->assertJsonPath('data.company_id', $companyB->id);

    expect($user->fresh()->company_id)->toBe($companyB->id);
});

it('re-scopes company data after switching', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $user = User::factory()->create(['company_id' => $companyA->id]);
    $user->companies()->attach($companyA, ['role' => 'owner']);
    $user->companies()->attach($companyB, ['role' => 'owner']);

    Employee::factory()->create(['company_id' => $companyA->id]);
    Employee::factory()->count(2)->create(['company_id' => $companyB->id]);

    $this->actingAs($user)->getJson('/api/manager/employees')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($user)->patchJson('/api/auth/active-company', [
        'company_id' => $companyB->id,
    ])->assertOk();

    $this->actingAs($user->fresh())->getJson('/api/manager/employees')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('rejects switching to a company the user does not belong to', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $user = User::factory()->create(['company_id' => $companyA->id]);
    $user->companies()->attach($companyA, ['role' => 'owner']);

    $this->actingAs($user)->patchJson('/api/auth/active-company', [
        'company_id' => $companyB->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['company_id']);

    expect($user->fresh()->company_id)->toBe($companyA->id);
});

it('requires authentication to switch company', function () {
    $company = Company::factory()->create();

    $this->patchJson('/api/auth/active-company', [
        'company_id' => $company->id,
    ])->assertUnauthorized();
});
