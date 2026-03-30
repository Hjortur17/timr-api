<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->employee = Employee::create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'name' => $this->user->name,
    ]);
    $this->actingAs($this->user);
});

it('allows an employee to view their own published shifts', function () {
    $shifts = Shift::factory()->count(2)->create([
        'company_id' => $this->company->id,
    ]);
    $shifts->each(fn ($s) => $s->employees()->attach($this->employee, [
        'date' => today()->toDateString(),
        'published' => true,
        'published_date' => today()->toDateString(),
        'published_employee_id' => $this->employee->id,
    ]));

    $unpublishedShift = Shift::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $unpublishedShift->employees()->attach($this->employee, ['date' => today()->toDateString(), 'published' => false]);

    $this->getJson('/api/employee/shifts')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('does not show shifts assigned to other employees', function () {
    $otherUser = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $otherEmployee = Employee::create([
        'company_id' => $this->company->id,
        'user_id' => $otherUser->id,
        'name' => $otherUser->name,
    ]);

    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $shift->employees()->attach($otherEmployee, [
        'date' => today()->toDateString(),
        'published' => true,
        'published_date' => today()->toDateString(),
        'published_employee_id' => $otherEmployee->id,
    ]);

    $this->getJson('/api/employee/shifts')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('prevents a manager from accessing employee shift routes', function () {
    $manager = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $manager->companies()->attach($this->company, ['role' => 'owner']);
    $this->actingAs($manager);

    $this->getJson('/api/employee/shifts')->assertForbidden();
});

it('employee sees published date not draft date after manager moves shift', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);

    $assignment = EmployeeShift::create([
        'shift_id' => $shift->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-04-06',
        'published' => true,
        'published_date' => '2026-04-06',
        'published_employee_id' => $this->employee->id,
    ]);

    // Manager moves draft to Tuesday, but published_date stays Monday
    $assignment->update(['date' => '2026-04-07']);

    // Employee should still see Monday
    $this->getJson('/api/employee/shifts?from=2026-04-06&to=2026-04-06')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // Employee should NOT see Tuesday
    $this->getJson('/api/employee/shifts?from=2026-04-07&to=2026-04-07')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('employee sees updated date after manager republishes moved shift', function () {
    $shift = Shift::factory()->create(['company_id' => $this->company->id]);

    $assignment = EmployeeShift::create([
        'shift_id' => $shift->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-04-06',
        'published' => true,
        'published_date' => '2026-04-06',
        'published_employee_id' => $this->employee->id,
    ]);

    // Manager moves to Tuesday and republishes
    $assignment->update([
        'date' => '2026-04-07',
        'published_date' => '2026-04-07',
    ]);

    // Employee should now see Tuesday
    $this->getJson('/api/employee/shifts?from=2026-04-07&to=2026-04-07')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // Employee should NOT see Monday anymore
    $this->getJson('/api/employee/shifts?from=2026-04-06&to=2026-04-06')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
