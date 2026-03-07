<?php

use App\Models\Company;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->manager->assignRole('manager');
    $this->actingAs($this->manager);
});

it('allows a manager to list employees', function () {
    $employees = User::factory()->count(3)->create([
        'company_id' => $this->company->id,
    ]);
    $employees->each(fn ($e) => $e->assignRole('employee'));

    $this->getJson('/api/manager/employees')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('allows a manager to create an employee', function () {
    $response = $this->postJson('/api/manager/employees', [
        'name' => 'Jane Employee',
        'email' => 'jane@acme.com',
        'password' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Jane Employee')
        ->assertJsonPath('data.email', 'jane@acme.com');

    $employee = User::withoutGlobalScope('company')->where('email', 'jane@acme.com')->first();
    expect($employee->hasRole('employee'))->toBeTrue();
    expect($employee->company_id)->toBe($this->company->id);
});

it('prevents creating an employee with duplicate email', function () {
    User::factory()->create([
        'company_id' => $this->company->id,
        'email' => 'existing@acme.com',
    ]);

    $this->postJson('/api/manager/employees', [
        'name' => 'Duplicate',
        'email' => 'existing@acme.com',
        'password' => 'password123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('does not list employees from another company', function () {
    $otherCompany = Company::factory()->create();
    $otherEmployee = User::factory()->create(['company_id' => $otherCompany->id]);
    $otherEmployee->assignRole('employee');

    $this->getJson('/api/manager/employees')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('prevents an employee from accessing manager employee routes', function () {
    $employee = User::factory()->create(['company_id' => $this->company->id]);
    $employee->assignRole('employee');
    $this->actingAs($employee);

    $this->getJson('/api/manager/employees')->assertForbidden();
});
