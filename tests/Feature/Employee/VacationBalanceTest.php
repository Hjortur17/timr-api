<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\VacationPolicy;
use App\Models\VacationRequest;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Fix time inside the current vacation year (May 1 2026 – Apr 30 2027).
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

it('returns the default balance structure for a new employee', function () {
    $this->getJson('/api/employee/vacation-balance')
        ->assertOk()
        ->assertJsonPath('data.entitled', 24)
        ->assertJsonPath('data.used', 0)
        ->assertJsonPath('data.pending', 0)
        ->assertJsonPath('data.remaining', 24)
        ->assertJsonPath('data.vacation_year_start', '2026-05-01')
        ->assertJsonPath('data.vacation_year_end', '2027-04-30');
});

it('counts approved requests against used', function () {
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'approved',
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-10',
        'working_days_count' => 5,
    ]);

    $this->getJson('/api/employee/vacation-balance')
        ->assertOk()
        ->assertJsonPath('data.used', 5)
        ->assertJsonPath('data.pending', 0)
        ->assertJsonPath('data.remaining', 19);
});

it('counts pending requests against pending', function () {
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'pending',
        'start_date' => '2026-08-03',
        'end_date' => '2026-08-07',
        'working_days_count' => 5,
    ]);

    $this->getJson('/api/employee/vacation-balance')
        ->assertOk()
        ->assertJsonPath('data.used', 0)
        ->assertJsonPath('data.pending', 5)
        ->assertJsonPath('data.remaining', 19);
});

it('ignores cancelled and denied requests', function () {
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'cancelled',
        'working_days_count' => 5,
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-10',
    ]);
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'denied',
        'working_days_count' => 5,
        'start_date' => '2026-08-03',
        'end_date' => '2026-08-07',
    ]);

    $this->getJson('/api/employee/vacation-balance')
        ->assertOk()
        ->assertJsonPath('data.used', 0)
        ->assertJsonPath('data.pending', 0)
        ->assertJsonPath('data.remaining', 24);
});

it('ignores requests from previous vacation years', function () {
    // Previous year: May 1 2025 – Apr 30 2026
    VacationRequest::factory()->create([
        'company_id' => $this->company->id,
        'employee_id' => $this->employee->id,
        'status' => 'approved',
        'start_date' => '2025-07-07',
        'end_date' => '2025-07-11',
        'working_days_count' => 5,
    ]);

    $this->getJson('/api/employee/vacation-balance')
        ->assertOk()
        ->assertJsonPath('data.used', 0)
        ->assertJsonPath('data.remaining', 24);
});

it('reflects policy overrides to entitled days', function () {
    VacationPolicy::create([
        'company_id' => $this->company->id,
        'default_days_per_year' => 30,
        'vacation_year_start_month' => 5,
        'vacation_year_start_day' => 1,
        'allow_carry_over' => false,
    ]);

    $this->getJson('/api/employee/vacation-balance')
        ->assertOk()
        ->assertJsonPath('data.entitled', 30)
        ->assertJsonPath('data.remaining', 30);
});
