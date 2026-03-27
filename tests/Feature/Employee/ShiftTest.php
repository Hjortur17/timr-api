<?php

use App\Models\Company;
use App\Models\Employee;
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
    $shifts->each(fn ($s) => $s->employees()->attach($this->employee, ['date' => today()->toDateString(), 'published' => true]));

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
    $shift->employees()->attach($otherEmployee, ['date' => today()->toDateString(), 'published' => true]);

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
