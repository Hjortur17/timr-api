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

    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
    $this->shift = Shift::factory()->create(['company_id' => $this->company->id]);
});

it('new assignment has null snapshot columns', function () {
    $this->postJson('/api/manager/shift-assignments', [
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-04-06',
    ])->assertCreated()
        ->assertJsonPath('data.published_date', null)
        ->assertJsonPath('data.published_employee_id', null)
        ->assertJsonPath('data.has_unpublished_changes', false);
});

it('published assignment has matching snapshot and draft columns', function () {
    EmployeeShift::factory()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-04-06',
        'published' => false,
    ]);

    $this->postJson('/api/manager/shifts/publish', [
        'from' => '2026-04-01',
        'to' => '2026-04-12',
    ])->assertOk()
        ->assertJsonPath('updated_count', 1);

    $this->getJson('/api/manager/shift-assignments?from=2026-04-01&to=2026-04-12')
        ->assertOk()
        ->assertJsonPath('data.0.date', '2026-04-06')
        ->assertJsonPath('data.0.published_date', '2026-04-06')
        ->assertJsonPath('data.0.published_employee_id', $this->employee->id)
        ->assertJsonPath('data.0.has_unpublished_changes', false);
});

it('has_unpublished_changes is true when draft date differs from published', function () {
    $assignment = EmployeeShift::factory()->create([
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

it('has_unpublished_changes is true when draft employee differs from published', function () {
    $employeeB = Employee::factory()->create(['company_id' => $this->company->id]);

    $assignment = EmployeeShift::factory()->create([
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
        ->assertJsonPath('data.published_employee_id', $this->employee->id)
        ->assertJsonPath('data.has_unpublished_changes', true);
});

it('has_unpublished_changes is false for never-published assignment', function () {
    EmployeeShift::factory()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-04-06',
        'published' => false,
    ]);

    $this->getJson('/api/manager/shift-assignments?from=2026-04-01&to=2026-04-12')
        ->assertOk()
        ->assertJsonPath('data.0.has_unpublished_changes', false);
});

it('publish all resolves has_unpublished_changes for all assignments', function () {
    $a1 = EmployeeShift::factory()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-04-06',
        'published' => false,
    ]);

    $a2 = EmployeeShift::factory()->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-04-07',
        'published' => false,
    ]);

    // Publish both
    $this->postJson('/api/manager/shifts/publish', [])->assertOk();

    // Move both
    $this->putJson("/api/manager/shift-assignments/{$a1->id}", ['date' => '2026-04-08'])->assertOk();
    $this->putJson("/api/manager/shift-assignments/{$a2->id}", ['date' => '2026-04-09'])->assertOk();

    // Verify both have unpublished changes
    expect($a1->refresh()->hasUnpublishedChanges())->toBeTrue();
    expect($a2->refresh()->hasUnpublishedChanges())->toBeTrue();

    // Publish all again
    $this->postJson('/api/manager/shifts/publish', [])->assertOk();

    // Both should now be resolved
    expect($a1->refresh()->hasUnpublishedChanges())->toBeFalse();
    expect($a2->refresh()->hasUnpublishedChanges())->toBeFalse();
    expect($a1->published_date->toDateString())->toBe('2026-04-08');
    expect($a2->published_date->toDateString())->toBe('2026-04-09');
});
