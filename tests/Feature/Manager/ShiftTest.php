<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);
    $this->actingAs($this->manager);
});

it('allows a manager to list shifts', function () {
    Shift::factory()->count(3)->create([
        'company_id' => $this->company->id,
    ]);

    $this->getJson('/api/manager/shifts')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('allows a manager to create a shift', function () {
    $response = $this->postJson('/api/manager/shifts', [
        'title' => 'Morning Shift',
        'start_time' => '08:00',
        'end_time' => '16:00',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Morning Shift');

    expect(Shift::count())->toBe(1);
});

it('allows a manager to update a shift', function () {
    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $this->putJson("/api/manager/shifts/{$shift->id}", [
        'title' => 'Updated Shift',
    ])->assertOk()
        ->assertJsonPath('data.title', 'Updated Shift');
});

it('allows a manager to delete a shift', function () {
    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $this->deleteJson("/api/manager/shifts/{$shift->id}")
        ->assertOk();

    expect(Shift::count())->toBe(0);
});

it('prevents a manager from seeing another companys shifts', function () {
    $otherCompany = Company::factory()->create();
    $otherShift = Shift::factory()->create([
        'company_id' => $otherCompany->id,
    ]);

    $this->putJson("/api/manager/shifts/{$otherShift->id}", [
        'title' => 'Hack',
    ])->assertNotFound();
});

it('prevents a non-manager from creating a shift', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->companies()->attach($this->company, ['role' => 'accountant']);
    $this->actingAs($user);

    $this->postJson('/api/manager/shifts', [])->assertForbidden();
});

it('publishes assignments within a date range', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id]);

    $inRange = EmployeeShift::factory()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-01',
        'published' => false,
    ]);

    $outOfRange = EmployeeShift::factory()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-10',
        'published' => false,
    ]);

    $this->postJson('/api/manager/shifts/publish', [
        'from' => '2026-03-30',
        'to' => '2026-04-05',
    ])->assertOk()
        ->assertJsonPath('updated_count', 1);

    expect($inRange->refresh()->published)->toBeTrue();
    expect($outOfRange->refresh()->published)->toBeFalse();
});

it('publishes all unpublished assignments when no dates provided', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id]);

    $a1 = EmployeeShift::factory()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-01',
        'published' => false,
    ]);

    $a2 = EmployeeShift::factory()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-05-15',
        'published' => false,
    ]);

    $this->postJson('/api/manager/shifts/publish', [])
        ->assertOk()
        ->assertJsonPath('updated_count', 2);

    expect($a1->refresh()->published)->toBeTrue();
    expect($a2->refresh()->published)->toBeTrue();
});

it('unpublishes assignments by ids', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id]);

    $a1 = EmployeeShift::factory()->published()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-01',
    ]);

    $a2 = EmployeeShift::factory()->published()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-02',
    ]);

    $this->postJson('/api/manager/shifts/unpublish', [
        'ids' => [$a1->id],
    ])->assertOk()
        ->assertJsonPath('updated_count', 1);

    expect($a1->refresh()->published)->toBeFalse();
    expect($a2->refresh()->published)->toBeTrue();
});

it('rejects unpublish with empty ids array', function () {
    $this->postJson('/api/manager/shifts/unpublish', [
        'ids' => [],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['ids']);
});

it('publishing copies draft values to snapshot columns', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id]);

    $assignment = EmployeeShift::factory()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-06',
        'published' => false,
    ]);

    $this->postJson('/api/manager/shifts/publish', [
        'from' => '2026-04-01',
        'to' => '2026-04-12',
    ])->assertOk();

    $assignment->refresh();
    expect($assignment->published)->toBeTrue();
    expect($assignment->published_date->toDateString())->toBe('2026-04-06');
    expect($assignment->published_employee_id)->toBe($employee->id);
});

it('publishing a moved assignment updates snapshot to current draft', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id]);

    $assignment = EmployeeShift::factory()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-06',
        'published' => false,
    ]);

    // Publish initially
    $this->postJson('/api/manager/shifts/publish', [
        'from' => '2026-04-01',
        'to' => '2026-04-12',
    ])->assertOk();

    expect($assignment->refresh()->published_date->toDateString())->toBe('2026-04-06');

    // Move to Tuesday (draft only)
    $this->putJson("/api/manager/shift-assignments/{$assignment->id}", [
        'date' => '2026-04-07',
    ])->assertOk();

    expect($assignment->refresh()->published_date->toDateString())->toBe('2026-04-06');

    // Publish again
    $this->postJson('/api/manager/shifts/publish', [
        'from' => '2026-04-01',
        'to' => '2026-04-12',
    ])->assertOk();

    expect($assignment->refresh()->published_date->toDateString())->toBe('2026-04-07');
});

it('unpublishing clears snapshot columns', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id]);

    $assignment = EmployeeShift::factory()->published()->create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-06',
    ]);

    expect($assignment->published_date)->not->toBeNull();

    $this->postJson('/api/manager/shifts/unpublish', [
        'ids' => [$assignment->id],
    ])->assertOk();

    $assignment->refresh();
    expect($assignment->published)->toBeFalse();
    expect($assignment->published_date)->toBeNull();
    expect($assignment->published_employee_id)->toBeNull();
});
