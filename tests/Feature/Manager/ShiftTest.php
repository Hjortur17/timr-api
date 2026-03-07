<?php

use App\Models\Company;
use App\Models\Shift;
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

it('allows a manager to list shifts', function () {
    $employee = User::factory()->create(['company_id' => $this->company->id]);

    Shift::factory()->count(3)->create([
        'company_id' => $this->company->id,
        'employee_id' => $employee->id,
    ]);

    $this->getJson('/api/manager/shifts')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('allows a manager to create a shift', function () {
    $employee = User::factory()->create(['company_id' => $this->company->id]);
    $employee->assignRole('employee');

    $response = $this->postJson('/api/manager/shifts', [
        'employee_id' => $employee->id,
        'title' => 'Morning Shift',
        'start_time' => '2027-06-01 08:00:00',
        'end_time' => '2027-06-01 16:00:00',
        'status' => 'published',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Morning Shift');

    expect(Shift::count())->toBe(1);
});

it('allows a manager to update a shift', function () {
    $employee = User::factory()->create(['company_id' => $this->company->id]);
    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $employee->id,
    ]);

    $this->putJson("/api/manager/shifts/{$shift->id}", [
        'title' => 'Updated Shift',
    ])->assertOk()
        ->assertJsonPath('data.title', 'Updated Shift');
});

it('allows a manager to delete a shift', function () {
    $employee = User::factory()->create(['company_id' => $this->company->id]);
    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $employee->id,
    ]);

    $this->deleteJson("/api/manager/shifts/{$shift->id}")
        ->assertOk();

    expect(Shift::count())->toBe(0);
});

it('prevents a manager from seeing another companys shifts', function () {
    $otherCompany = Company::factory()->create();
    $otherEmployee = User::factory()->create(['company_id' => $otherCompany->id]);
    $otherShift = Shift::factory()->create([
        'company_id' => $otherCompany->id,
        'employee_id' => $otherEmployee->id,
    ]);

    $this->putJson("/api/manager/shifts/{$otherShift->id}", [
        'title' => 'Hack',
    ])->assertNotFound();
});

it('prevents an employee from creating a shift', function () {
    $employee = User::factory()->create(['company_id' => $this->company->id]);
    $employee->assignRole('employee');
    $this->actingAs($employee);

    $this->postJson('/api/manager/shifts', [])->assertForbidden();
});
