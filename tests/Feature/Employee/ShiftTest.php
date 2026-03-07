<?php

use App\Models\Company;
use App\Models\Shift;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->employee = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->employee->assignRole('employee');
    $this->actingAs($this->employee);
});

it('allows an employee to view their own published shifts', function () {
    Shift::factory()->count(2)->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'published',
    ]);

    Shift::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'draft',
    ]);

    $this->getJson('/api/employee/shifts')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('does not show shifts assigned to other employees', function () {
    $otherEmployee = User::factory()->create([
        'company_id' => $this->company->id,
    ]);

    Shift::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $otherEmployee->id,
        'status' => 'published',
    ]);

    $this->getJson('/api/employee/shifts')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('prevents a manager from accessing employee shift routes', function () {
    $manager = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $manager->assignRole('manager');
    $this->actingAs($manager);

    $this->getJson('/api/employee/shifts')->assertForbidden();
});
