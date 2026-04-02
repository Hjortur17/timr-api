<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Str;

// --- Public calendar endpoint tests (no auth) ---

it('returns valid ical for a valid calendar token', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $token = Str::uuid()->toString();
    $employee = Employee::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'name' => $user->name,
        'calendar_token' => $token,
    ]);

    $shift = Shift::factory()->create([
        'company_id' => $company->id,
        'title' => 'Morning Shift',
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
    ]);
    $shift->employees()->attach($employee, [
        'date' => '2026-04-06',
        'published' => true,
        'published_date' => '2026-04-06',
        'published_employee_id' => $employee->id,
    ]);

    $response = $this->get("/api/calendar/{$token}");

    $response->assertOk()
        ->assertHeader('content-type', 'text/calendar; charset=UTF-8');

    $body = $response->getContent();
    expect($body)->toContain('BEGIN:VCALENDAR')
        ->toContain('BEGIN:VEVENT')
        ->toContain('DTSTAMP:')
        ->toContain('SUMMARY:Morning Shift')
        ->toContain('DTSTART:20260406T080000')
        ->toContain('DTEND:20260406T160000')
        ->toContain('END:VEVENT')
        ->toContain('END:VCALENDAR');
});

it('returns 404 for an invalid calendar token', function () {
    $this->get('/api/calendar/nonexistent-token')->assertNotFound();
});

it('excludes unpublished shifts from public calendar', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $token = Str::uuid()->toString();
    $employee = Employee::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'name' => $user->name,
        'calendar_token' => $token,
    ]);

    $published = Shift::factory()->create([
        'company_id' => $company->id,
        'title' => 'Published Shift',
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
    ]);
    $published->employees()->attach($employee, [
        'date' => '2026-04-06',
        'published' => true,
        'published_date' => '2026-04-06',
        'published_employee_id' => $employee->id,
    ]);

    $unpublished = Shift::factory()->create([
        'company_id' => $company->id,
        'title' => 'Unpublished Shift',
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
    ]);
    $unpublished->employees()->attach($employee, [
        'date' => '2026-04-07',
        'published' => false,
    ]);

    $body = $this->get("/api/calendar/{$token}")->getContent();

    expect($body)->toContain('SUMMARY:Published Shift')
        ->not->toContain('SUMMARY:Unpublished Shift');
});

it('only returns shifts for the tokens employee', function () {
    $company = Company::factory()->create();

    $user1 = User::factory()->create(['company_id' => $company->id]);
    $token1 = Str::uuid()->toString();
    $employee1 = Employee::create([
        'company_id' => $company->id,
        'user_id' => $user1->id,
        'name' => 'Employee 1',
        'calendar_token' => $token1,
    ]);

    $user2 = User::factory()->create(['company_id' => $company->id]);
    $employee2 = Employee::create([
        'company_id' => $company->id,
        'user_id' => $user2->id,
        'name' => 'Employee 2',
        'calendar_token' => Str::uuid()->toString(),
    ]);

    $shift1 = Shift::factory()->create([
        'company_id' => $company->id,
        'title' => 'Employee 1 Shift',
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
    ]);
    $shift1->employees()->attach($employee1, [
        'date' => '2026-04-06',
        'published' => true,
        'published_date' => '2026-04-06',
        'published_employee_id' => $employee1->id,
    ]);

    $shift2 = Shift::factory()->create([
        'company_id' => $company->id,
        'title' => 'Employee 2 Shift',
        'start_time' => '10:00:00',
        'end_time' => '18:00:00',
    ]);
    $shift2->employees()->attach($employee2, [
        'date' => '2026-04-06',
        'published' => true,
        'published_date' => '2026-04-06',
        'published_employee_id' => $employee2->id,
    ]);

    $body = $this->get("/api/calendar/{$token1}")->getContent();

    expect($body)->toContain('SUMMARY:Employee 1 Shift')
        ->not->toContain('SUMMARY:Employee 2 Shift');
});

it('works without any authentication header', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $token = Str::uuid()->toString();
    Employee::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'name' => $user->name,
        'calendar_token' => $token,
    ]);

    // Explicitly not calling actingAs — no auth at all
    $this->get("/api/calendar/{$token}")->assertOk();
});

it('uses published date not draft date in public calendar', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $token = Str::uuid()->toString();
    $employee = Employee::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'name' => $user->name,
        'calendar_token' => $token,
    ]);

    $shift = Shift::factory()->create([
        'company_id' => $company->id,
        'title' => 'Moved Shift',
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
    ]);
    $shift->employees()->attach($employee, [
        'date' => '2026-04-07',
        'published' => true,
        'published_date' => '2026-04-06',
        'published_employee_id' => $employee->id,
    ]);

    $body = $this->get("/api/calendar/{$token}")->getContent();

    expect($body)->toContain('DTSTART:20260406T080000')
        ->not->toContain('DTSTART:20260407T080000');
});

// --- Subscribe endpoint tests (authenticated) ---

it('generates a calendar token when employee has none', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $employee = Employee::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'name' => $user->name,
    ]);

    $this->actingAs($user);

    $response = $this->postJson('/api/employee/calendar-subscribe');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['url']]);

    $employee->refresh();
    expect($employee->calendar_token)->not->toBeNull();
    expect($response->json('data.url'))->toContain($employee->calendar_token);
});

it('returns existing token if already generated', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $existingToken = Str::uuid()->toString();
    $employee = Employee::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'name' => $user->name,
        'calendar_token' => $existingToken,
    ]);

    $this->actingAs($user);

    $response = $this->postJson('/api/employee/calendar-subscribe');

    $response->assertOk();
    expect($response->json('data.url'))->toContain($existingToken);
    expect($employee->refresh()->calendar_token)->toBe($existingToken);
});

it('requires authentication for calendar subscribe', function () {
    $this->postJson('/api/employee/calendar-subscribe')->assertUnauthorized();
});

it('requires employee role for calendar subscribe', function () {
    $company = Company::factory()->create();
    $manager = User::factory()->create(['company_id' => $company->id]);
    $manager->companies()->attach($company, ['role' => 'owner']);

    $this->actingAs($manager);

    $this->postJson('/api/employee/calendar-subscribe')->assertForbidden();
});
