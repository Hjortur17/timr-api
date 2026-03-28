<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use App\Models\ShiftTemplate;
use App\Models\ShiftTemplateEntry;
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
    ShiftTemplate::factory()->count(3)->create([
        'company_id' => $this->company->id,
    ]);

    $this->getJson('/api/manager/shift-templates')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('allows a manager to create a shift template', function () {
    $response = $this->postJson('/api/manager/shift-templates', [
        'name' => '2-2-3 Rotation',
        'description' => 'Icelandic 2-2-3 pattern',
        'cycle_length_days' => 14,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', '2-2-3 Rotation')
        ->assertJsonPath('data.cycle_length_days', 14);

    expect(ShiftTemplate::count())->toBe(1);
});

it('allows a manager to create a template with entries', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id]);

    $response = $this->postJson('/api/manager/shift-templates', [
        'name' => '2-2-3 Rotation',
        'cycle_length_days' => 14,
        'entries' => [
            ['shift_id' => $shift->id, 'day_offset' => 0, 'employee_id' => $employee->id],
            ['shift_id' => $shift->id, 'day_offset' => 1, 'employee_id' => $employee->id],
            ['shift_id' => $shift->id, 'day_offset' => 4],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonCount(3, 'data.entries');

    expect(ShiftTemplateEntry::count())->toBe(3);
});

it('allows a manager to update a shift template', function () {
    $template = ShiftTemplate::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $this->putJson("/api/manager/shift-templates/{$template->id}", [
        'name' => 'Updated Template',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Updated Template');
});

it('replaces entries when updating a template with entries', function () {
    $template = ShiftTemplate::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);

    ShiftTemplateEntry::factory()->count(3)->create([
        'shift_template_id' => $template->id,
        'shift_id' => $shift->id,
    ]);

    $this->putJson("/api/manager/shift-templates/{$template->id}", [
        'entries' => [
            ['shift_id' => $shift->id, 'day_offset' => 0],
        ],
    ])->assertOk()
        ->assertJsonCount(1, 'data.entries');

    expect(ShiftTemplateEntry::where('shift_template_id', $template->id)->count())->toBe(1);
});

it('allows a manager to delete a shift template', function () {
    $template = ShiftTemplate::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $this->deleteJson("/api/manager/shift-templates/{$template->id}")
        ->assertOk();

    expect(ShiftTemplate::count())->toBe(0);
});

// ── Validation ──────────────────────────────────────────────────────

it('validates required fields when creating a template', function () {
    $this->postJson('/api/manager/shift-templates', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'cycle_length_days']);
});

it('validates cycle_length_days is a positive integer', function () {
    $this->postJson('/api/manager/shift-templates', [
        'name' => 'Bad Template',
        'cycle_length_days' => 0,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['cycle_length_days']);
});

it('validates entry shift_id belongs to the company', function () {
    $otherCompany = Company::factory()->create();
    $otherShift = Shift::factory()->create(['company_id' => $otherCompany->id]);

    $this->postJson('/api/manager/shift-templates', [
        'name' => 'Template',
        'cycle_length_days' => 7,
        'entries' => [
            ['shift_id' => $otherShift->id, 'day_offset' => 0],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['entries.0.shift_id']);
});

// ── Multi-tenant isolation ──────────────────────────────────────────

it('prevents a manager from seeing another companys templates', function () {
    $otherCompany = Company::factory()->create();
    $otherTemplate = ShiftTemplate::factory()->create([
        'company_id' => $otherCompany->id,
    ]);

    $this->putJson("/api/manager/shift-templates/{$otherTemplate->id}", [
        'name' => 'Hack',
    ])->assertNotFound();
});

it('only lists templates belonging to the managers company', function () {
    ShiftTemplate::factory()->count(2)->create(['company_id' => $this->company->id]);

    $otherCompany = Company::factory()->create();
    ShiftTemplate::factory()->count(3)->create(['company_id' => $otherCompany->id]);

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

it('generates shift assignments from a template for a date range', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);

    $template = ShiftTemplate::factory()->create([
        'company_id' => $this->company->id,
        'cycle_length_days' => 7,
    ]);

    // Assign shift on day 0 (Monday) and day 2 (Wednesday) for this employee
    ShiftTemplateEntry::factory()->create([
        'shift_template_id' => $template->id,
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'day_offset' => 0,
    ]);
    ShiftTemplateEntry::factory()->create([
        'shift_template_id' => $template->id,
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'day_offset' => 2,
    ]);

    $response = $this->postJson("/api/manager/shift-templates/{$template->id}/generate", [
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-14',
    ]);

    $response->assertCreated()
        ->assertJsonPath('assignments_created', 4); // 2 days per week × 2 weeks

    expect(EmployeeShift::count())->toBe(4);
    expect(EmployeeShift::where('published', false)->count())->toBe(4);
});

it('generates assignments for all employees when entry has no employee_id', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    Employee::factory()->count(3)->create(['company_id' => $this->company->id, 'is_active' => true]);

    $template = ShiftTemplate::factory()->create([
        'company_id' => $this->company->id,
        'cycle_length_days' => 7,
    ]);

    ShiftTemplateEntry::factory()->create([
        'shift_template_id' => $template->id,
        'shift_id' => $shift->id,
        'employee_id' => null,
        'day_offset' => 0,
    ]);

    $this->postJson("/api/manager/shift-templates/{$template->id}/generate", [
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-07',
    ])->assertCreated()
        ->assertJsonPath('assignments_created', 3); // 3 employees × 1 day in cycle

    expect(EmployeeShift::count())->toBe(3);
});

it('skips duplicate assignments when generating', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);

    $template = ShiftTemplate::factory()->create([
        'company_id' => $this->company->id,
        'cycle_length_days' => 7,
    ]);

    ShiftTemplateEntry::factory()->create([
        'shift_template_id' => $template->id,
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'day_offset' => 0,
    ]);

    // Pre-create an assignment for the first day
    EmployeeShift::create([
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'date' => '2026-04-01',
        'published' => false,
    ]);

    $this->postJson("/api/manager/shift-templates/{$template->id}/generate", [
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-14',
    ])->assertCreated()
        ->assertJsonPath('assignments_created', 1); // Only the second week's assignment

    expect(EmployeeShift::count())->toBe(2);
});

it('generates correct 2-2-3 rotation pattern', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $employee = Employee::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);

    $template = ShiftTemplate::factory()->twoTwoThree()->create([
        'company_id' => $this->company->id,
    ]);

    // 2-2-3 pattern: Week A: work 0,1 off 2,3 work 4,5,6 | Week B: off 7,8 work 9,10 off 11,12,13
    $workDays = [0, 1, 4, 5, 6, 9, 10];
    foreach ($workDays as $day) {
        ShiftTemplateEntry::factory()->create([
            'shift_template_id' => $template->id,
            'shift_id' => $shift->id,
            'employee_id' => $employee->id,
            'day_offset' => $day,
        ]);
    }

    $this->postJson("/api/manager/shift-templates/{$template->id}/generate", [
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-14',
    ])->assertCreated()
        ->assertJsonPath('assignments_created', 7);

    expect(EmployeeShift::count())->toBe(7);
});

it('validates generate request requires start_date and end_date', function () {
    $template = ShiftTemplate::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $this->postJson("/api/manager/shift-templates/{$template->id}/generate", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date', 'end_date']);
});

it('validates end_date must be after or equal to start_date', function () {
    $template = ShiftTemplate::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $this->postJson("/api/manager/shift-templates/{$template->id}/generate", [
        'start_date' => '2026-04-15',
        'end_date' => '2026-04-01',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);
});

it('prevents generating schedule for another companys template', function () {
    $otherCompany = Company::factory()->create();
    $otherTemplate = ShiftTemplate::factory()->create([
        'company_id' => $otherCompany->id,
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
        'cycle_length_days' => 1, // Every day
    ]);

    ShiftTemplateEntry::factory()->create([
        'shift_template_id' => $template->id,
        'shift_id' => $shift->id,
        'employee_id' => $employee->id,
        'day_offset' => 0,
    ]);

    $this->postJson("/api/manager/shift-templates/{$template->id}/generate", [
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-03',
    ])->assertCreated();

    expect(EmployeeShift::where('published', true)->count())->toBe(0);
    expect(EmployeeShift::where('published', false)->count())->toBe(3);
});
