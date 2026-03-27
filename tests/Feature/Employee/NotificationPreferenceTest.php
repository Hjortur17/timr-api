<?php

use App\Enums\NotificationType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
    ]);
    $this->actingAs($this->user);
});

it('returns all notification types with default enabled state', function () {
    $response = $this->getJson('/api/employee/notification-preferences')
        ->assertOk();

    $data = $response->json('data');
    $types = collect($data)->pluck('type')->toArray();

    expect($types)->toContain('shift_published');
    expect($types)->toContain('shift_changed');
    expect($types)->toContain('shift_reminder');

    // All default to enabled
    foreach ($data as $pref) {
        expect($pref['enabled'])->toBeTrue();
    }
});

it('reflects saved preferences in the response', function () {
    $this->employee->notificationPreferences()->create([
        'type' => NotificationType::ShiftReminder->value,
        'enabled' => false,
    ]);

    $response = $this->getJson('/api/employee/notification-preferences')
        ->assertOk();

    $reminder = collect($response->json('data'))
        ->firstWhere('type', 'shift_reminder');

    expect($reminder['enabled'])->toBeFalse();
});

it('allows an employee to update notification preferences', function () {
    $this->putJson('/api/employee/notification-preferences', [
        'preferences' => [
            ['type' => 'shift_published', 'enabled' => false],
            ['type' => 'shift_reminder', 'enabled' => false],
        ],
    ])->assertOk();

    $this->assertDatabaseHas('notification_preferences', [
        'employee_id' => $this->employee->id,
        'type' => 'shift_published',
        'enabled' => false,
    ]);

    $this->assertDatabaseHas('notification_preferences', [
        'employee_id' => $this->employee->id,
        'type' => 'shift_reminder',
        'enabled' => false,
    ]);
});

it('upserts rather than duplicates existing preferences', function () {
    $this->employee->notificationPreferences()->create([
        'type' => NotificationType::ShiftPublished->value,
        'enabled' => true,
    ]);

    $this->putJson('/api/employee/notification-preferences', [
        'preferences' => [
            ['type' => 'shift_published', 'enabled' => false],
        ],
    ])->assertOk();

    expect($this->employee->notificationPreferences()->where('type', 'shift_published')->count())->toBe(1);

    $this->assertDatabaseHas('notification_preferences', [
        'employee_id' => $this->employee->id,
        'type' => 'shift_published',
        'enabled' => false,
    ]);
});

it('validates that preference type is a valid NotificationType', function () {
    $this->putJson('/api/employee/notification-preferences', [
        'preferences' => [
            ['type' => 'invalid_type', 'enabled' => true],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['preferences.0.type']);
});
