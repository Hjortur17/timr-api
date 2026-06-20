<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-06-01');

    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);

    $this->employeeUser = User::factory()->create(['company_id' => $this->company->id]);
    $this->employee = Employee::create([
        'company_id' => $this->company->id,
        'user_id' => $this->employeeUser->id,
        'name' => 'Test Employee',
        'email' => 'test@employee.com',
    ]);

    $this->actingAs($this->manager);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('allows a manager to list all vacation requests for the company', function () {
    VacationRequest::factory()->count(3)->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
    ]);

    $this->getJson('/api/manager/vacation-requests')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.employee.id', $this->employee->id);
});

it('allows a manager to filter vacation requests by status', function () {
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'approved',
    ]);

    $this->getJson('/api/manager/vacation-requests?status=pending')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'pending');
});

it('allows a manager to filter vacation requests by employee', function () {
    $otherUser = User::factory()->create(['company_id' => $this->company->id]);
    $otherEmployee = Employee::create([
        'company_id' => $this->company->id,
        'user_id' => $otherUser->id,
        'name' => 'Other Employee',
        'email' => 'other@x.com',
    ]);

    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
    ]);
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $otherEmployee->id,
    ]);

    $this->getJson("/api/manager/vacation-requests?employee_id={$this->employee->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.employee_id', $this->employee->id);
});

it('allows a manager to view a single vacation request', function () {
    $request = VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
    ]);

    $this->getJson("/api/manager/vacation-requests/{$request->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $request->id)
        ->assertJsonPath('data.employee.id', $this->employee->id);
});

it('allows a manager to approve a pending request', function () {
    $request = VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    $this->postJson("/api/manager/vacation-requests/{$request->id}/review", [
        'status' => 'approved',
        'note' => 'Looks good!',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.reviewer_note', 'Looks good!')
        ->assertJsonPath('data.reviewed_by', $this->manager->id)
        ->assertJsonPath('data.reviewer.id', $this->manager->id);

    $request->refresh();
    expect($request->status->value)->toBe('approved')
        ->and($request->reviewed_by)->toBe($this->manager->id)
        ->and($request->reviewed_at)->not->toBeNull();
});

it('allows a manager to deny a pending request', function () {
    $request = VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    $this->postJson("/api/manager/vacation-requests/{$request->id}/review", [
        'status' => 'denied',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'denied');
});

it('rejects reviewing a request that is not pending', function () {
    $request = VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'approved',
    ]);

    $this->postJson("/api/manager/vacation-requests/{$request->id}/review", [
        'status' => 'denied',
    ])->assertStatus(422);
});

it('validates the review status value', function () {
    $request = VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    $this->postJson("/api/manager/vacation-requests/{$request->id}/review", [
        'status' => 'bogus',
    ])->assertStatus(422);
});

it('prevents a manager from seeing another companys vacation requests', function () {
    $otherCompany = Company::factory()->create();
    $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
    $otherEmployee = Employee::create([
        'company_id' => $otherCompany->id,
        'user_id' => $otherUser->id,
        'name' => 'Other',
        'email' => 'other@c.com',
    ]);
    $foreignRequest = VacationRequest::factory()->create([
        'company_id' => $otherCompany->id,
        'employee_id' => $otherEmployee->id,
    ]);

    $this->getJson("/api/manager/vacation-requests/{$foreignRequest->id}")
        ->assertNotFound();

    $this->postJson("/api/manager/vacation-requests/{$foreignRequest->id}/review", [
        'status' => 'approved',
    ])->assertNotFound();
});

it('prevents a non-manager from managing vacation requests', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->companies()->attach($this->company, ['role' => 'accountant']);
    $this->actingAs($user);

    $this->getJson('/api/manager/vacation-requests')->assertForbidden();
});

it('filters vacation requests by date range for calendar overlay', function () {
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'approved',
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-10',
    ]);
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'approved',
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-07',
    ]);

    $this->getJson('/api/manager/vacation-requests?status=approved&from=2026-07-01&to=2026-07-31')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.start_date', '2026-07-06');
});

it('includes vacation requests whose range overlaps the window', function () {
    // Starts before window, extends into it.
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'approved',
        'start_date' => '2026-06-29',
        'end_date' => '2026-07-03',
    ]);

    $this->getJson('/api/manager/vacation-requests?status=approved&from=2026-07-01&to=2026-07-07')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('allows a manager to add a vacation for an employee', function () {
    $this->postJson('/api/manager/vacation-requests', [
        'employee_id' => $this->employee->id,
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-10',
        'note' => 'Granted',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.type', 'holiday')
        ->assertJsonPath('data.working_days_count', 5)
        ->assertJsonPath('data.reviewed_by', $this->manager->id)
        ->assertJsonPath('data.employee_id', $this->employee->id);
});

it('allows a manager to add a non-deductible leave type without touching the balance', function () {
    $this->postJson('/api/manager/vacation-requests', [
        'employee_id' => $this->employee->id,
        'start_date' => '2026-07-06',
        'end_date' => '2026-08-31',
        'type' => 'sick_leave',
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'sick_leave')
        ->assertJsonPath('data.status', 'approved');
});

it('allows a manager to push an employee balance negative', function () {
    // 24-day entitlement; this Holiday range exceeds it but the manager path bypasses the cap.
    $this->postJson('/api/manager/vacation-requests', [
        'employee_id' => $this->employee->id,
        'start_date' => '2026-07-06',
        'end_date' => '2026-08-31',
    ])->assertCreated();

    $balance = app(App\Services\VacationService::class)->balanceFor($this->employee->fresh());
    expect($balance['remaining'])->toBeLessThan(0);
});

it('validates that the employee belongs to the managers company', function () {
    $otherCompany = Company::factory()->create();
    $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
    $otherEmployee = Employee::create([
        'company_id' => $otherCompany->id,
        'user_id' => $otherUser->id,
        'name' => 'Foreign',
        'email' => 'foreign@x.com',
    ]);

    $this->postJson('/api/manager/vacation-requests', [
        'employee_id' => $otherEmployee->id,
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-10',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('employee_id');
});

it('allows a manager to edit a vacation and recomputes working days', function () {
    $request = VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'approved',
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-10',
        'working_days_count' => 5,
        'type' => 'holiday',
    ]);

    $this->putJson("/api/manager/vacation-requests/{$request->id}", [
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-17',
        'type' => 'sick_leave',
        'note' => 'Updated',
    ])
        ->assertOk()
        ->assertJsonPath('data.working_days_count', 10)
        ->assertJsonPath('data.type', 'sick_leave')
        ->assertJsonPath('data.employee_note', 'Updated')
        ->assertJsonPath('data.status', 'approved');
});

it('allows editing a request within its own date range without an overlap error', function () {
    $request = VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'pending',
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-17',
    ]);

    $this->putJson("/api/manager/vacation-requests/{$request->id}", [
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-10',
    ])->assertOk();
});

it('allows a manager to restore a denied request', function () {
    $request = VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'denied',
        'reviewer_note' => 'No',
        'reviewed_by' => $this->manager->id,
        'reviewed_at' => now(),
    ]);

    $this->postJson("/api/manager/vacation-requests/{$request->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.reviewer_note', null)
        ->assertJsonPath('data.reviewed_by', null);

    $request->refresh();
    expect($request->status->value)->toBe('pending')
        ->and($request->reviewed_at)->toBeNull();
});

it('rejects restoring a request that is not denied', function () {
    $request = VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'approved',
    ]);

    $this->postJson("/api/manager/vacation-requests/{$request->id}/restore")
        ->assertStatus(422);
});
