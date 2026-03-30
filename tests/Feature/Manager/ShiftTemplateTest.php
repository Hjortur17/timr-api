<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use App\Models\ShiftTemplate;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);
    $this->actingAs($this->manager);
});

// ── CRUD ────────────────────────────────────────────────────────────

it('allows a manager to list shift templates', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);

    ShiftTemplate::factory()->count(3)->create([
        'company_id' => $this->company->id,
        'shift_id' => $shift->id,
    ]);

    $this->getJson('/api/manager/shift-templates')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('allows a manager to create a shift template', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employees = Employee::factory()->count(2)->create(['company_id' => $this->company->id]);

    $response = $this->postJson('/api/manager/shift-templates', [
        'name' => '2-2-3 Rotation',
        'description' => 'Icelandic 2-2-3 pattern',
        'shift_id' => $shift->id,
        'pattern' => '2-2-3',
        'blocks' => [2, 2, 3],
        'employee_ids' => $employees->pluck('id')->toArray(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', '2-2-3 Rotation')
        ->assertJsonPath('data.pattern', '2-2-3')
        ->assertJsonPath('data.blocks', [2, 2, 3])
        ->assertJsonPath('data.cycle_length_days', 7)
        ->assertJsonPath('data.shift_id', $shift->id)
        ->assertJsonCount(2, 'data.employees');

    expect(ShiftTemplate::count())->toBe(1);
});

it('allows a manager to update a shift template', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);

    $template = ShiftTemplate::factory()->create([
        'company_id' => $this->company->id,
        'shift_id' => $shift->id,
    ]);

    $this->putJson("/api/manager/shift-templates/{$template->id}", [
        'name' => 'Updated Template',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Updated Template');
});

it('updates employees when updating a template with employee_ids', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employees = Employee::factory()->count(3)->create(['company_id' => $this->company->id]);

    $template = ShiftTemplate::factory()->create([
        'company_id' => $this->company->id,
        'shift_id' => $shift->id,
    ]);
    $template->employees()->attach($employees[0], ['sort_order' => 0]);

    $this->putJson("/api/manager/shift-templates/{$template->id}", [
        'employee_ids' => [$employees[1]->id, $employees[2]->id],
    ])->assertOk()
        ->assertJsonCount(2, 'data.employees');

    expect($template->fresh()->employees()->count())->toBe(2);
});

it('allows a manager to delete a shift template', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);

    $template = ShiftTemplate::factory()->create([
        'company_id' => $this->company->id,
        'shift_id' => $shift->id,
    ]);

    $this->deleteJson("/api/manager/shift-templates/{$template->id}")
        ->assertOk();

    expect(ShiftTemplate::count())->toBe(0);
});

// ── Validation ──────────────────────────────────────────────────────

it('validates required fields when creating a template', function () {
    $this->postJson('/api/manager/shift-templates', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'shift_id', 'pattern', 'blocks', 'employee_ids']);
});

it('validates pattern must be a valid preset or custom', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id]);

    $this->postJson('/api/manager/shift-templates', [
        'name' => 'Bad Template',
        'shift_id' => $shift->id,
        'pattern' => 'invalid-pattern',
        'blocks' => [2, 2, 3],
        'employee_ids' => [$employee->id],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['pattern']);
});

it('validates shift_id belongs to the company', function () {
    $otherCompany = Company::factory()->create();
    $otherShift = Shift::factory()->create(['company_id' => $otherCompany->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id]);

    $this->postJson('/api/manager/shift-templates', [
        'name' => 'Template',
        'shift_id' => $otherShift->id,
        'pattern' => '2-2-3',
        'blocks' => [2, 2, 3],
        'employee_ids' => [$employee->id],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['shift_id']);
});

it('validates employee_ids belong to the company', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $otherCompany = Company::factory()->create();
    $otherEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);

    $this->postJson('/api/manager/shift-templates', [
        'name' => 'Template',
        'shift_id' => $shift->id,
        'pattern' => '2-2-3',
        'blocks' => [2, 2, 3],
        'employee_ids' => [$otherEmployee->id],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_ids.0']);
});

// ── Multi-tenant isolation ──────────────────────────────────────────

it('prevents a manager from seeing another companys templates', function () {
    $otherCompany = Company::factory()->create();
    $otherShift = Shift::factory()->create(['company_id' => $otherCompany->id]);

    $otherTemplate = ShiftTemplate::factory()->create([
        'company_id' => $otherCompany->id,
        'shift_id' => $otherShift->id,
    ]);

    $this->putJson("/api/manager/shift-templates/{$otherTemplate->id}", [
        'name' => 'Hack',
    ])->assertNotFound();
});

it('only lists templates belonging to the managers company', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    ShiftTemplate::factory()->count(2)->create([
        'company_id' => $this->company->id,
        'shift_id' => $shift->id,
    ]);

    $otherCompany = Company::factory()->create();
    $otherShift = Shift::factory()->create(['company_id' => $otherCompany->id]);
    ShiftTemplate::factory()->count(3)->create([
        'company_id' => $otherCompany->id,
        'shift_id' => $otherShift->id,
    ]);

    $this->getJson('/api/manager/shift-templates')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

// ── RBAC ────────────────────────────────────────────────────────────

it('prevents a non-manager from creating a template', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->companies()->attach($this->company, ['role' => 'accountant']);
    $this->actingAs($user);

    $this->postJson('/api/manager/shift-templates', [])->assertForbidden();
});

// ── Schedule Generation ─────────────────────────────────────────────

it('generates shift assignments with 2-2-3 rotation for 2 employees over 2 weeks', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employeeA = Employee::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);
    $employeeB = Employee::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);

    $template = ShiftTemplate::factory()->twoTwoThree()->create([
        'company_id' => $this->company->id,
        'shift_id' => $shift->id,
    ]);
    $template->employees()->attach([
        $employeeA->id => ['sort_order' => 0],
        $employeeB->id => ['sort_order' => 1],
    ]);

    // Generate for 2 weeks (2 full cycles)
    $response = $this->postJson("/api/manager/shift-templates/{$template->id}/generate", [
        'start_date' => '2026-04-06', // Monday
        'end_date' => '2026-04-19',   // Sunday (14 days)
    ]);

    $response->assertCreated()
        ->assertJsonPath('assignments_created', 14); // All 14 days covered

    // Verify week 1 rotation: A(2), B(2), A(3)
    // Days 0-1: Employee A
    expect(EmployeeShift::where('employee_id', $employeeA->id)->whereDate('date', '2026-04-06')->exists())->toBeTrue();
    expect(EmployeeShift::where('employee_id', $employeeA->id)->whereDate('date', '2026-04-07')->exists())->toBeTrue();
    // Days 2-3: Employee B
    expect(EmployeeShift::where('employee_id', $employeeB->id)->whereDate('date', '2026-04-08')->exists())->toBeTrue();
    expect(EmployeeShift::where('employee_id', $employeeB->id)->whereDate('date', '2026-04-09')->exists())->toBeTrue();
    // Days 4-6: Employee A
    expect(EmployeeShift::where('employee_id', $employeeA->id)->whereDate('date', '2026-04-10')->exists())->toBeTrue();
    expect(EmployeeShift::where('employee_id', $employeeA->id)->whereDate('date', '2026-04-11')->exists())->toBeTrue();
    expect(EmployeeShift::where('employee_id', $employeeA->id)->whereDate('date', '2026-04-12')->exists())->toBeTrue();

    // Verify week 2 rotation: B(2), A(2), B(3) — rotated
    // Days 7-8: Employee B
    expect(EmployeeShift::where('employee_id', $employeeB->id)->whereDate('date', '2026-04-13')->exists())->toBeTrue();
    expect(EmployeeShift::where('employee_id', $employeeB->id)->whereDate('date', '2026-04-14')->exists())->toBeTrue();
    // Days 9-10: Employee A
    expect(EmployeeShift::where('employee_id', $employeeA->id)->whereDate('date', '2026-04-15')->exists())->toBeTrue();
    expect(EmployeeShift::where('employee_id', $employeeA->id)->whereDate('date', '2026-04-16')->exists())->toBeTrue();
    // Days 11-13: Employee B
    expect(EmployeeShift::where('employee_id', $employeeB->id)->whereDate('date', '2026-04-17')->exists())->toBeTrue();
    expect(EmployeeShift::where('employee_id', $employeeB->id)->whereDate('date', '2026-04-18')->exists())->toBeTrue();
    expect(EmployeeShift::where('employee_id', $employeeB->id)->whereDate('date', '2026-04-19')->exists())->toBeTrue();
});

it('generates 5-2 rotation with 3 employees', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employees = Employee::factory()->count(3)->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);

    $template = ShiftTemplate::factory()->fiveTwo()->create([
        'company_id' => $this->company->id,
        'shift_id' => $shift->id,
    ]);
    $template->employees()->attach([
        $employees[0]->id => ['sort_order' => 0],
        $employees[1]->id => ['sort_order' => 1],
        $employees[2]->id => ['sort_order' => 2],
    ]);

    // Generate for 1 week (1 cycle of 7 days)
    $response = $this->postJson("/api/manager/shift-templates/{$template->id}/generate", [
        'start_date' => '2026-04-06',
        'end_date' => '2026-04-12',
    ]);

    $response->assertCreated()
        ->assertJsonPath('assignments_created', 7);

    // Cycle 0: Block 0 (5 days) → employee 0, Block 1 (2 days) → employee 1
    expect(EmployeeShift::where('employee_id', $employees[0]->id)->count())->toBe(5);
    expect(EmployeeShift::where('employee_id', $employees[1]->id)->count())->toBe(2);
});

it('skips duplicate assignments when generating', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);

    $template = ShiftTemplate::factory()->create([
        'company_id' => $this->company->id,
        'shift_id' => $shift->id,
        'pattern' => '2-2-3',
        'blocks' => [2, 2, 3],
        'cycle_length_days' => 7,
    ]);
    $template->employees()->attach($employee, ['sort_order' => 0]);

    // Pre-create an assignment for the first day
    EmployeeShift::create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-06',
        'published' => false,
    ]);

    $this->postJson("/api/manager/shift-templates/{$template->id}/generate", [
        'start_date' => '2026-04-06',
        'end_date' => '2026-04-12',
    ])->assertCreated()
        ->assertJsonPath('assignments_created', 6); // 7 days minus 1 duplicate

    expect(EmployeeShift::count())->toBe(7);
});

it('validates generate request requires start_date and end_date', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);

    $template = ShiftTemplate::factory()->create([
        'company_id' => $this->company->id,
        'shift_id' => $shift->id,
    ]);

    $this->postJson("/api/manager/shift-templates/{$template->id}/generate", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date', 'end_date']);
});

it('validates end_date must be after or equal to start_date', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);

    $template = ShiftTemplate::factory()->create([
        'company_id' => $this->company->id,
        'shift_id' => $shift->id,
    ]);

    $this->postJson("/api/manager/shift-templates/{$template->id}/generate", [
        'start_date' => '2026-04-15',
        'end_date' => '2026-04-01',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);
});

it('prevents generating schedule for another companys template', function () {
    $otherCompany = Company::factory()->create();
    $otherShift = Shift::factory()->create(['company_id' => $otherCompany->id]);

    $otherTemplate = ShiftTemplate::factory()->create([
        'company_id' => $otherCompany->id,
        'shift_id' => $otherShift->id,
    ]);

    $this->postJson("/api/manager/shift-templates/{$otherTemplate->id}/generate", [
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-14',
    ])->assertNotFound();
});

it('creates assignments as unpublished drafts', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);

    $template = ShiftTemplate::factory()->create([
        'company_id' => $this->company->id,
        'shift_id' => $shift->id,
        'pattern' => 'custom',
        'blocks' => [1],
        'cycle_length_days' => 1,
    ]);
    $template->employees()->attach($employee, ['sort_order' => 0]);

    $this->postJson("/api/manager/shift-templates/{$template->id}/generate", [
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-03',
    ])->assertCreated();

    expect(EmployeeShift::where('published', true)->count())->toBe(0);
    expect(EmployeeShift::where('published', false)->count())->toBe(3);
});
