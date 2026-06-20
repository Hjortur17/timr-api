<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-06-01');

    $this->company = Company::factory()->create();
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->employee = Employee::create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'name' => $this->user->name,
        'email' => 'emp@test.com',
    ]);
    $this->actingAs($this->user);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('allows an employee to list their own vacation requests', function () {
    VacationRequest::factory()->count(2)->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
    ]);

    $otherUser = User::factory()->create(['company_id' => $this->company->id]);
    $otherEmployee = Employee::create([
        'company_id' => $this->company->id,
        'user_id' => $otherUser->id,
        'name' => 'Other',
        'email' => 'other@x.com',
    ]);
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $otherEmployee->id,
    ]);

    $this->getJson('/api/employee/vacation-requests')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('allows an employee to create a vacation request', function () {
    $this->postJson('/api/employee/vacation-requests', [
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-10',
        'note' => 'Summer trip',
    ])
        ->assertCreated()
        ->assertJsonPath('data.start_date', '2026-07-06')
        ->assertJsonPath('data.end_date', '2026-07-10')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.employee_note', 'Summer trip')
        ->assertJsonPath('data.employee_id', $this->employee->id)
        ->assertJsonPath('data.working_days_count', 5);
});

it('correctly excludes weekends and Icelandic holidays from working days', function () {
    // Dec 22 (Tue), Dec 23 (Wed) = working days
    // Dec 24 (Aðfangadagur), Dec 25 (Jóladagur), Dec 26 (Annar í jólum) = holidays
    // Dec 26 (Sat), Dec 27 (Sun) = weekend
    $this->postJson('/api/employee/vacation-requests', [
        'start_date' => '2026-12-22',
        'end_date' => '2026-12-27',
    ])
        ->assertCreated()
        ->assertJsonPath('data.working_days_count', 2);
});

it('counts a Saturday when the company is open on Saturdays', function () {
    \App\Models\VacationPolicy::create([
        'company_id' => $this->company->id,
        'default_days_per_year' => 24,
        'vacation_year_start_month' => 5,
        'vacation_year_start_day' => 1,
        'working_days' => [1, 2, 3, 4, 5, 6],
        'allow_carry_over' => false,
    ]);

    // Mon 2026-07-06 .. Sat 2026-07-11 → 6 open days when Saturday is open (5 otherwise).
    $this->postJson('/api/employee/vacation-requests', [
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-11',
    ])
        ->assertCreated()
        ->assertJsonPath('data.working_days_count', 6);
});

it('excludes a day the company is closed on', function () {
    \App\Models\VacationPolicy::create([
        'company_id' => $this->company->id,
        'default_days_per_year' => 24,
        'vacation_year_start_month' => 5,
        'vacation_year_start_day' => 1,
        'working_days' => [2, 3, 4, 5], // closed Mondays
        'allow_carry_over' => false,
    ]);

    // Mon 2026-07-06 .. Fri 2026-07-10 → 4 (Monday excluded).
    $this->postJson('/api/employee/vacation-requests', [
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-10',
    ])
        ->assertCreated()
        ->assertJsonPath('data.working_days_count', 4);
});

it('rejects an overlapping pending or approved request', function () {
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'pending',
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-10',
    ]);

    $this->postJson('/api/employee/vacation-requests', [
        'start_date' => '2026-07-09',
        'end_date' => '2026-07-15',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('start_date');
});

it('allows a new request when previous is cancelled or denied', function () {
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'cancelled',
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-10',
    ]);

    $this->postJson('/api/employee/vacation-requests', [
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-10',
    ])->assertCreated();
});

it('rejects end_date before start_date', function () {
    $this->postJson('/api/employee/vacation-requests', [
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-06',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('end_date');
});

it('rejects a request that starts in the past', function () {
    $this->postJson('/api/employee/vacation-requests', [
        'start_date' => '2026-05-15',
        'end_date' => '2026-05-20',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('start_date');
});

it('rejects a request that exceeds the remaining balance', function () {
    // Default entitlement is 24 days/year; this range spans far more working days.
    $this->postJson('/api/employee/vacation-requests', [
        'start_date' => '2026-07-06',
        'end_date' => '2026-08-31',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('end_date');
});

it('allows a request within the remaining balance', function () {
    $this->postJson('/api/employee/vacation-requests', [
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-17',
    ])->assertCreated()
        ->assertJsonPath('data.working_days_count', 10)
        ->assertJsonPath('data.type', 'holiday')
        ->assertJsonPath('data.deductible', true);
});

it('allows a non-deductible leave type to be back-dated', function () {
    $this->postJson('/api/employee/vacation-requests', [
        'start_date' => '2026-05-11',
        'end_date' => '2026-05-15',
        'type' => 'sick_leave',
    ])->assertCreated()
        ->assertJsonPath('data.type', 'sick_leave')
        ->assertJsonPath('data.deductible', false);
});

it('does not count non-deductible leave against the balance', function () {
    // A long sick-leave span that would blow past the 24-day entitlement if it were deductible.
    $this->postJson('/api/employee/vacation-requests', [
        'start_date' => '2026-07-06',
        'end_date' => '2026-08-31',
        'type' => 'sick_leave',
    ])->assertCreated();

    $this->getJson('/api/employee/vacation-balance')
        ->assertOk()
        ->assertJsonPath('data.used', 0)
        ->assertJsonPath('data.pending', 0)
        ->assertJsonPath('data.remaining', 24);
});

it('rejects an invalid leave type', function () {
    $this->postJson('/api/employee/vacation-requests', [
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-10',
        'type' => 'bogus',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('type');
});

it('allows an employee to cancel their own pending request', function () {
    $request = VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    $this->postJson("/api/employee/vacation-requests/{$request->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $request->refresh();
    expect($request->status->value)->toBe('cancelled')
        ->and($request->cancelled_at)->not->toBeNull();
});

it('rejects cancelling an already-reviewed request', function () {
    $request = VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'approved',
    ]);

    $this->postJson("/api/employee/vacation-requests/{$request->id}/cancel")
        ->assertStatus(422);
});

it('rejects cancelling another employees request', function () {
    $otherUser = User::factory()->create(['company_id' => $this->company->id]);
    $otherEmployee = Employee::create([
        'company_id' => $this->company->id,
        'user_id' => $otherUser->id,
        'name' => 'Other',
        'email' => 'other@x.com',
    ]);
    $request = VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $otherEmployee->id,
        'status' => 'pending',
    ]);

    $this->postJson("/api/employee/vacation-requests/{$request->id}/cancel")
        ->assertForbidden();
});

it('prevents a non-employee user from accessing vacation endpoints', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $this->actingAs($user);

    $this->getJson('/api/employee/vacation-requests')->assertForbidden();
});

it('filters the employees own requests by date range for calendar overlay', function () {
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

    $this->getJson('/api/employee/vacation-requests?status=approved&from=2026-07-01&to=2026-07-31')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
