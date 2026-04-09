<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\NotificationPreference;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->companies()->attach($this->company->id, ['role' => 'owner']);
    $this->actingAs($this->user);
});

it('returns all employee notification types with defaults for a manager', function () {
    $response = $this->getJson('/api/auth/notification-preferences')
        ->assertOk();

    $data = $response->json('data');
    $types = collect($data)->pluck('notification_type')->toArray();

    // Should include employee types
    expect($types)->toContain('shift_published');
    expect($types)->toContain('schedule_change_alert');
    expect($types)->toContain('shift_start_reminder');

    // Should include manager types for a manager
    expect($types)->toContain('unapproved_timesheets_alert');
    expect($types)->toContain('overtime_escalation');

    // Each item has required metadata
    foreach ($data as $pref) {
        expect($pref)->toHaveKeys([
            'notification_type',
            'label',
            'description',
            'mandatory',
            'manager_only',
            'available_channels',
            'channel_push',
            'channel_email',
            'channel_in_app',
        ]);
    }

    // Should include global settings
    expect($response->json('pause_all'))->toBeFalse();
    expect($response->json('quiet_hours_start'))->toBeNull();
    expect($response->json('quiet_hours_end'))->toBeNull();
});

it('excludes manager-only types for employees', function () {
    $employee = Employee::factory()->create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
    ]);

    // Remove owner role, make this user employee-only
    $this->user->companies()->updateExistingPivot($this->company->id, ['role' => 'employee']);

    $response = $this->getJson('/api/auth/notification-preferences')
        ->assertOk();

    $types = collect($response->json('data'))->pluck('notification_type')->toArray();

    expect($types)->not->toContain('unapproved_timesheets_alert');
    expect($types)->not->toContain('overtime_escalation');
    expect($types)->not->toContain('forgot_clock_out_escalation');
    expect($types)->not->toContain('timesheet_approval_deadline');
    expect($types)->not->toContain('unusual_activity_alert');

    // Should still include employee types
    expect($types)->toContain('shift_published');
    expect($types)->toContain('shift_start_reminder');
});

it('reflects saved channel preferences in the response', function () {
    NotificationPreference::create([
        'user_id' => $this->user->id,
        'notification_type' => 'shift_published',
        'channel_push' => false,
        'channel_email' => true,
        'channel_in_app' => false,
    ]);

    $response = $this->getJson('/api/auth/notification-preferences')
        ->assertOk();

    $pref = collect($response->json('data'))
        ->firstWhere('notification_type', 'shift_published');

    expect($pref['channel_push'])->toBeFalse();
    expect($pref['channel_email'])->toBeTrue();
    expect($pref['channel_in_app'])->toBeFalse();
});

it('allows updating channel preferences', function () {
    $this->putJson('/api/auth/notification-preferences', [
        'preferences' => [
            [
                'notification_type' => 'shift_published',
                'channel_push' => false,
                'channel_email' => true,
                'channel_in_app' => false,
            ],
        ],
    ])->assertOk();

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $this->user->id,
        'notification_type' => 'shift_published',
        'channel_push' => false,
        'channel_email' => true,
        'channel_in_app' => false,
    ]);
});

it('rejects disabling all channels on mandatory notifications', function () {
    $this->putJson('/api/auth/notification-preferences', [
        'preferences' => [
            [
                'notification_type' => 'schedule_change_alert',
                'channel_push' => false,
                'channel_email' => false,
                'channel_in_app' => false,
            ],
        ],
    ])->assertUnprocessable();
});

it('allows choosing channels on mandatory notifications as long as at least one is active', function () {
    $this->putJson('/api/auth/notification-preferences', [
        'preferences' => [
            [
                'notification_type' => 'schedule_change_alert',
                'channel_push' => false,
                'channel_email' => true,
                'channel_in_app' => false,
            ],
        ],
    ])->assertOk();

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $this->user->id,
        'notification_type' => 'schedule_change_alert',
        'channel_push' => false,
        'channel_email' => true,
        'channel_in_app' => false,
    ]);
});

it('updates pause_all on the user', function () {
    $this->putJson('/api/auth/notification-preferences', [
        'preferences' => [],
        'pause_all' => true,
    ])->assertOk();

    expect($this->user->fresh()->notifications_paused)->toBeTrue();
});

it('updates quiet hours on the user', function () {
    $this->putJson('/api/auth/notification-preferences', [
        'preferences' => [],
        'quiet_hours_start' => '23:00',
        'quiet_hours_end' => '07:00',
    ])->assertOk();

    $user = $this->user->fresh();
    expect($user->quiet_hours_start)->toBe('23:00');
    expect($user->quiet_hours_end)->toBe('07:00');
});

it('persists timing preferences', function () {
    $this->putJson('/api/auth/notification-preferences', [
        'preferences' => [
            [
                'notification_type' => 'shift_start_reminder',
                'channel_push' => true,
                'channel_email' => true,
                'channel_in_app' => false,
                'timing_value' => ['minutes_before' => 30],
            ],
        ],
    ])->assertOk();

    $pref = NotificationPreference::where('user_id', $this->user->id)
        ->where('notification_type', 'shift_start_reminder')
        ->first();

    expect($pref->timing_value)->toBe(['minutes_before' => 30]);
});

it('upserts rather than duplicates preferences', function () {
    NotificationPreference::create([
        'user_id' => $this->user->id,
        'notification_type' => 'shift_published',
        'channel_push' => true,
        'channel_email' => true,
        'channel_in_app' => true,
    ]);

    $this->putJson('/api/auth/notification-preferences', [
        'preferences' => [
            [
                'notification_type' => 'shift_published',
                'channel_push' => false,
                'channel_email' => false,
                'channel_in_app' => true,
            ],
        ],
    ])->assertOk();

    expect(
        NotificationPreference::where('user_id', $this->user->id)
            ->where('notification_type', 'shift_published')
            ->count()
    )->toBe(1);
});

it('validates notification_type is a valid enum value', function () {
    $this->putJson('/api/auth/notification-preferences', [
        'preferences' => [
            [
                'notification_type' => 'invalid_type',
                'channel_push' => true,
                'channel_email' => true,
                'channel_in_app' => true,
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['preferences.0.notification_type']);
});

it('marks mandatory types in the response', function () {
    $response = $this->getJson('/api/auth/notification-preferences')
        ->assertOk();

    $scheduleChange = collect($response->json('data'))
        ->firstWhere('notification_type', 'schedule_change_alert');

    expect($scheduleChange['mandatory'])->toBeTrue();

    $shiftPublished = collect($response->json('data'))
        ->firstWhere('notification_type', 'shift_published');

    expect($shiftPublished['mandatory'])->toBeFalse();
});
