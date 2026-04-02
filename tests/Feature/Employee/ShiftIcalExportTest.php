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

it('returns a valid ical file with published shifts', function () {
    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
        'title' => 'Morning Shift',
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
    ]);
    $shift->employees()->attach($this->employee, [
        'date' => '2026-04-06',
        'published' => true,
        'published_date' => '2026-04-06',
        'published_employee_id' => $this->employee->id,
    ]);

    $response = $this->get('/api/employee/shifts/ical');

    $response->assertOk()
        ->assertHeader('content-type', 'text/calendar; charset=UTF-8')
        ->assertHeader('content-disposition', 'attachment; filename=vaktir.ics');

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

it('excludes unpublished shifts from ical export', function () {
    $published = Shift::factory()->create([
        'company_id' => $this->company->id,
        'title' => 'Published Shift',
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
    ]);
    $published->employees()->attach($this->employee, [
        'date' => '2026-04-06',
        'published' => true,
        'published_date' => '2026-04-06',
        'published_employee_id' => $this->employee->id,
    ]);

    $unpublished = Shift::factory()->create([
        'company_id' => $this->company->id,
        'title' => 'Unpublished Shift',
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
    ]);
    $unpublished->employees()->attach($this->employee, [
        'date' => '2026-04-07',
        'published' => false,
    ]);

    $body = $this->get('/api/employee/shifts/ical')->getContent();

    expect($body)->toContain('SUMMARY:Published Shift')
        ->not->toContain('SUMMARY:Unpublished Shift');
});

it('does not include shifts assigned to other employees', function () {
    $otherUser = User::factory()->create(['company_id' => $this->company->id]);
    $otherEmployee = Employee::create([
        'company_id' => $this->company->id,
        'user_id' => $otherUser->id,
        'name' => $otherUser->name,
    ]);

    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
        'title' => 'Other Employee Shift',
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
    ]);
    $shift->employees()->attach($otherEmployee, [
        'date' => '2026-04-06',
        'published' => true,
        'published_date' => '2026-04-06',
        'published_employee_id' => $otherEmployee->id,
    ]);

    $body = $this->get('/api/employee/shifts/ical')->getContent();

    expect($body)->toContain('BEGIN:VCALENDAR')
        ->not->toContain('BEGIN:VEVENT');
});

it('returns an empty calendar when employee has no shifts', function () {
    $response = $this->get('/api/employee/shifts/ical');

    $response->assertOk();

    $body = $response->getContent();
    expect($body)->toContain('BEGIN:VCALENDAR')
        ->toContain('END:VCALENDAR')
        ->not->toContain('BEGIN:VEVENT');
});

it('prevents a manager from accessing ical export', function () {
    $manager = User::factory()->create(['company_id' => $this->company->id]);
    $manager->companies()->attach($this->company, ['role' => 'owner']);
    $this->actingAs($manager);

    $this->get('/api/employee/shifts/ical')->assertForbidden();
});

it('includes multiple shifts on different dates', function () {
    $shift1 = Shift::factory()->create([
        'company_id' => $this->company->id,
        'title' => 'Monday Shift',
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
    ]);
    $shift1->employees()->attach($this->employee, [
        'date' => '2026-04-06',
        'published' => true,
        'published_date' => '2026-04-06',
        'published_employee_id' => $this->employee->id,
    ]);

    $shift2 = Shift::factory()->create([
        'company_id' => $this->company->id,
        'title' => 'Tuesday Shift',
        'start_time' => '10:00:00',
        'end_time' => '18:00:00',
    ]);
    $shift2->employees()->attach($this->employee, [
        'date' => '2026-04-07',
        'published' => true,
        'published_date' => '2026-04-07',
        'published_employee_id' => $this->employee->id,
    ]);

    $body = $this->get('/api/employee/shifts/ical')->getContent();

    expect($body)->toContain('SUMMARY:Monday Shift')
        ->toContain('DTSTART:20260406T080000')
        ->toContain('SUMMARY:Tuesday Shift')
        ->toContain('DTSTART:20260407T100000');
});

it('uses published date not draft date in ical export', function () {
    $shift = Shift::factory()->create([
        'company_id' => $this->company->id,
        'title' => 'Moved Shift',
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
    ]);
    $shift->employees()->attach($this->employee, [
        'date' => '2026-04-07',
        'published' => true,
        'published_date' => '2026-04-06',
        'published_employee_id' => $this->employee->id,
    ]);

    $body = $this->get('/api/employee/shifts/ical')->getContent();

    expect($body)->toContain('DTSTART:20260406T080000')
        ->not->toContain('DTSTART:20260407T080000');
});
