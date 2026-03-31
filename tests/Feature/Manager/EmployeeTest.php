<?php

use App\Mail\EmployeeInvite;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);
    $this->actingAs($this->manager);
});

it('allows a manager to list employees', function () {
    Employee::insert([
        ['company_id' => $this->company->id, 'name' => 'Emp 1', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['company_id' => $this->company->id, 'name' => 'Emp 2', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['company_id' => $this->company->id, 'name' => 'Emp 3', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->getJson('/api/manager/employees')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('allows a manager to create an employee', function () {
    $response = $this->postJson('/api/manager/employees', [
        'name' => 'Jane Employee',
        'email' => 'jane@acme.com',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Jane Employee')
        ->assertJsonPath('data.email', 'jane@acme.com');

    expect(Employee::count())->toBe(1);
    $employee = Employee::first();
    expect($employee->company_id)->toBe($this->company->id);
});

it('validates employee creation requires name', function () {
    $this->postJson('/api/manager/employees', [
        'email' => 'test@acme.com',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('does not list employees from another company', function () {
    $otherCompany = Company::factory()->create();
    Employee::create([
        'company_id' => $otherCompany->id,
        'name' => 'Other Employee',
    ]);

    $this->getJson('/api/manager/employees')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('prevents a non-manager from accessing manager employee routes', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->companies()->attach($this->company, ['role' => 'accountant']);
    $this->actingAs($user);

    $this->getJson('/api/manager/employees')->assertForbidden();
});

it('allows a manager to send an invite to an employee', function () {
    Mail::fake();

    $employee = Employee::create([
        'company_id' => $this->company->id,
        'name' => 'Test Employee',
        'email' => 'emp@test.com',
    ]);

    $this->postJson("/api/manager/employees/{$employee->id}/invite")
        ->assertOk()
        ->assertJsonPath('message', 'Hlekkur sendur.');

    $employee->refresh();
    expect($employee->invite_token)->not->toBeNull();
    expect($employee->invite_sent_at)->not->toBeNull();

    Mail::assertSent(EmployeeInvite::class, function (EmployeeInvite $mail) use ($employee) {
        return $mail->employee->id === $employee->id;
    });
});

it('allows creating an employee with ssn', function () {
    $this->postJson('/api/manager/employees', [
        'name' => 'Jón Jónsson',
        'ssn' => '1234567890',
    ])->assertCreated()
        ->assertJsonPath('data.ssn', '1234567890');

    expect(Employee::first()->ssn)->toBe('1234567890');
});

it('allows creating an employee without ssn', function () {
    $this->postJson('/api/manager/employees', [
        'name' => 'Guðrún',
    ])->assertCreated()
        ->assertJsonPath('data.ssn', null);
});

it('allows updating employee ssn', function () {
    $employee = Employee::create([
        'company_id' => $this->company->id,
        'name' => 'Test',
    ]);

    $this->putJson("/api/manager/employees/{$employee->id}", [
        'name' => 'Test',
        'ssn' => '0987654321',
    ])->assertOk()
        ->assertJsonPath('data.ssn', '0987654321');
});
