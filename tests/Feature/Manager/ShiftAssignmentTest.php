<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);
    $this->actingAs($this->manager);

    $this->employee = Employee::create(['company_id' => $this->company->id, 'name' => 'Test Employee']);
    $this->shift = Shift::factory()->create(['company_id' => $this->company->id]);
});

it('allows a manager to list assignments for a week', function () {
    EmployeeShift::create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-03-10',
        'published' => false,
    ]);

    $this->getJson('/api/manager/shift-assignments?from=2026-03-09&to=2026-03-15')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('excludes assignments outside the requested date range', function () {
    EmployeeShift::create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-03-01',
        'published' => false,
    ]);

    $this->getJson('/api/manager/shift-assignments?from=2026-03-09&to=2026-03-15')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('allows a manager to create an assignment', function () {
    $this->postJson('/api/manager/shift-assignments', [
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-03-10',
        'published' => false,
    ])->assertCreated()
        ->assertJsonPath('data.date', '2026-03-10')
        ->assertJsonPath('data.published', false);

    expect(EmployeeShift::count())->toBe(1);
});

it('prevents duplicate assignment for the same shift, employee and date', function () {
    EmployeeShift::create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-03-10',
        'published' => false,
    ]);

    $this->postJson('/api/manager/shift-assignments', [
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-03-10',
    ])->assertUnprocessable();
});

it('allows a manager to update an assignment date', function () {
    $assignment = EmployeeShift::create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-03-10',
        'published' => false,
    ]);

    $this->putJson("/api/manager/shift-assignments/{$assignment->id}", [
        'date' => '2026-03-11',
    ])->assertOk()
        ->assertJsonPath('data.date', '2026-03-11');
});

it('allows a manager to delete an assignment', function () {
    $assignment = EmployeeShift::create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-03-10',
        'published' => false,
    ]);

    $this->deleteJson("/api/manager/shift-assignments/{$assignment->id}")
        ->assertOk();

    expect(EmployeeShift::count())->toBe(0);
});

it('prevents a manager from accessing another companys assignments', function () {
    $otherCompany = Company::factory()->create();
    $otherEmployee = Employee::create(['company_id' => $otherCompany->id, 'name' => 'Other Employee']);
    $otherShift = Shift::factory()->create(['company_id' => $otherCompany->id]);

    $assignment = EmployeeShift::withoutGlobalScopes()->create([
        'shift_id' => $otherShift->id,
        'employee_id' => $otherEmployee->id,
        'date' => '2026-03-10',
        'published' => false,
    ]);

    $this->putJson("/api/manager/shift-assignments/{$assignment->id}", [
        'date' => '2026-03-11',
    ])->assertNotFound();
});

it('moving a published assignment only updates draft columns', function () {
    $assignment = EmployeeShift::create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-04-06',
        'published' => false,
    ]);

    $this->postJson('/api/manager/shifts/publish', [
        'from' => '2026-04-01',
        'to' => '2026-04-12',
    ])->assertOk();

    $this->putJson("/api/manager/shift-assignments/{$assignment->id}", [
        'date' => '2026-04-07',
    ])->assertOk()
        ->assertJsonPath('data.date', '2026-04-07')
        ->assertJsonPath('data.published_date', '2026-04-06')
        ->assertJsonPath('data.has_unpublished_changes', true);
});

it('moving a published assignment to a different employee preserves published_employee_id', function () {
    $employeeB = Employee::create(['company_id' => $this->company->id, 'name' => 'Employee B']);

    $assignment = EmployeeShift::create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-04-06',
        'published' => false,
    ]);

    $this->postJson('/api/manager/shifts/publish', [
        'from' => '2026-04-01',
        'to' => '2026-04-12',
    ])->assertOk();

    $this->putJson("/api/manager/shift-assignments/{$assignment->id}", [
        'employee_id' => $employeeB->id,
    ])->assertOk()
        ->assertJsonPath('data.employee_id', $employeeB->id)
        ->assertJsonPath('data.published_employee_id', $this->employee->id);
});
