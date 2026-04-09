<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\NotificationPreference;
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

it('returns all employee notification types with default enabled state', function () {
    $response = $this->getJson('/api/employee/notification-preferences')
        ->assertOk();

    $data = $response->json('data');
    $types = collect($data)->pluck('type')->toArray();

    expect($types)->toContain('shift_published');
    expect($types)->toContain('schedule_change_alert');
    expect($types)->toContain('shift_start_reminder');

    // All default to enabled
    foreach ($data as $pref) {
        expect($pref['enabled'])->toBeTrue();
    }
});

it('reflects saved preferences in the response', function () {
    NotificationPreference::create([
        'user_id' => $this->user->id,
        'notification_type' => 'shift_start_reminder',
        'channel_push' => false,
        'channel_email' => false,
        'channel_in_app' => false,
    ]);

    $response = $this->getJson('/api/employee/notification-preferences')
        ->assertOk();

    $reminder = collect($response->json('data'))
        ->firstWhere('type', 'shift_start_reminder');

    expect($reminder['enabled'])->toBeFalse();
});

it('allows an employee to update notification preferences', function () {
    $this->putJson('/api/employee/notification-preferences', [
        'preferences' => [
            ['type' => 'shift_published', 'enabled' => false],
            ['type' => 'shift_start_reminder', 'enabled' => false],
        ],
    ])->assertOk();

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $this->user->id,
        'notification_type' => 'shift_published',
        'channel_push' => false,
        'channel_email' => false,
        'channel_in_app' => false,
    ]);

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $this->user->id,
        'notification_type' => 'shift_start_reminder',
        'channel_push' => false,
        'channel_email' => false,
        'channel_in_app' => false,
    ]);
});

it('upserts rather than duplicates existing preferences', function () {
    NotificationPreference::create([
        'user_id' => $this->user->id,
        'notification_type' => 'shift_published',
        'channel_push' => true,
        'channel_email' => true,
        'channel_in_app' => true,
    ]);

    $this->putJson('/api/employee/notification-preferences', [
        'preferences' => [
            ['type' => 'shift_published', 'enabled' => false],
        ],
    ])->assertOk();

    expect(
        NotificationPreference::where('user_id', $this->user->id)
            ->where('notification_type', 'shift_published')
            ->count()
    )->toBe(1);

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $this->user->id,
        'notification_type' => 'shift_published',
        'channel_push' => false,
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
