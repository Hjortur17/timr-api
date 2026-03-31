<?php

use App\Models\ClockEntry;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);
    $this->actingAs($this->manager);

    $this->shift = Shift::factory()->create(['company_id' => $this->company->id]);
    $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
});

it('exports clock entries as xlsx', function () {
    ClockEntry::factory()->clockedOut()->count(3)->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
    ]);

    $this->get('/api/manager/clock-entries/export?from=2026-01-01&to=2026-12-31')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('exports clock entries filtered by employee', function () {
    ClockEntry::factory()->clockedOut()->count(2)->create([
        'shift_id' => $this->shift->id,
        'employee_id' => $this->employee->id,
    ]);

    $this->get("/api/manager/clock-entries/export?employee_id={$this->employee->id}")
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('prevents a non-manager from exporting', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->companies()->attach($this->company, ['role' => 'accountant']);
    $this->actingAs($user);

    $this->get('/api/manager/clock-entries/export')->assertForbidden();
});
