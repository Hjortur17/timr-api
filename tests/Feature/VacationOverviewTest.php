<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-06-20');

    $this->company = Company::factory()->create();

    // Current user is an employee (non-manager).
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->employee = Employee::create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'name' => 'Me',
        'email' => 'me@test.com',
    ]);

    $this->colleague = Employee::create([
        'company_id' => $this->company->id,
        'name' => 'Colleague',
        'email' => 'colleague@test.com',
    ]);

    $this->actingAs($this->user);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('lets any authenticated company member see the company vacation overview', function () {
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->colleague->id,
        'start_date' => '2026-07-13',
        'end_date' => '2026-07-20',
        'status' => 'approved',
        'type' => 'holiday',
    ]);

    $this->getJson('/api/vacation-overview?month=2026-07')
        ->assertOk()
        ->assertJsonPath('data.month', '2026-07')
        ->assertJsonPath('data.current_employee_id', $this->employee->id)
        ->assertJsonCount(2, 'data.employees')
        ->assertJsonCount(1, 'data.requests')
        ->assertJsonPath('data.requests.0.employee_id', $this->colleague->id)
        ->assertJsonPath('data.requests.0.start_date', '2026-07-13')
        ->assertJsonPath('data.requests.0.status', 'approved');
});

it('hides leave type of colleagues from a non-manager employee but reveals their own', function () {
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->colleague->id,
        'start_date' => '2026-07-13',
        'end_date' => '2026-07-20',
        'status' => 'approved',
        'type' => 'sick_leave',
    ]);
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-10',
        'status' => 'approved',
        'type' => 'holiday',
    ]);

    $res = $this->getJson('/api/vacation-overview?month=2026-07')->assertOk();

    $byEmployee = collect($res->json('data.requests'))->keyBy('employee_id');
    expect($byEmployee[$this->colleague->id]['type'])->toBeNull();
    expect($byEmployee[$this->employee->id]['type'])->toBe('holiday');
});

it('reveals leave types of everyone to a manager', function () {
    $manager = User::factory()->create(['company_id' => $this->company->id]);
    $manager->companies()->attach($this->company, ['role' => 'owner']);
    $this->actingAs($manager);

    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->colleague->id,
        'start_date' => '2026-07-13',
        'end_date' => '2026-07-20',
        'status' => 'approved',
        'type' => 'sick_leave',
    ]);

    $this->getJson('/api/vacation-overview?month=2026-07')
        ->assertOk()
        ->assertJsonPath('data.requests.0.type', 'sick_leave');
});

it('only returns approved and pending requests overlapping the month', function () {
    // Overlaps July, approved → included
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->colleague->id,
        'start_date' => '2026-06-28',
        'end_date' => '2026-07-03',
        'status' => 'approved',
    ]);
    // Pending overlapping July → included
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->colleague->id,
        'start_date' => '2026-07-15',
        'end_date' => '2026-07-18',
        'status' => 'pending',
    ]);
    // Cancelled → excluded
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->colleague->id,
        'start_date' => '2026-07-20',
        'end_date' => '2026-07-22',
        'status' => 'cancelled',
    ]);
    // Outside the month → excluded
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->colleague->id,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-05',
        'status' => 'approved',
    ]);

    $this->getJson('/api/vacation-overview?month=2026-07')
        ->assertOk()
        ->assertJsonCount(2, 'data.requests');
});

it('does not leak vacation data from other companies', function () {
    $otherCompany = Company::factory()->create();
    $otherEmployee = Employee::create([
        'company_id' => $otherCompany->id,
        'name' => 'Outsider',
        'email' => 'out@test.com',
    ]);
    VacationRequest::factory()->create([
        'company_id' => $otherCompany->id,
        'employee_id' => $otherEmployee->id,
        'start_date' => '2026-07-13',
        'end_date' => '2026-07-20',
        'status' => 'approved',
    ]);

    $this->getJson('/api/vacation-overview?month=2026-07')
        ->assertOk()
        ->assertJsonCount(2, 'data.employees')
        ->assertJsonCount(0, 'data.requests');
});

it('defaults to the current month when no month is given', function () {
    $this->getJson('/api/vacation-overview')
        ->assertOk()
        ->assertJsonPath('data.month', '2026-06');
});

it('requires authentication', function () {
    auth()->forgetGuards();

    $this->withHeader('Accept', 'application/json')
        ->get('/api/vacation-overview')
        ->assertUnauthorized();
});
