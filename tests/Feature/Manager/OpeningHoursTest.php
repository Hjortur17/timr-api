<?php

use App\Models\Company;
use App\Models\User;
use App\Models\VacationPolicy;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);
    $this->actingAs($this->manager);
});

function sameHours(array $days = [true, true, true, true, true, false, false]): array
{
    return [
        'days' => $days,
        'time_mode' => 'same',
        'open' => '09:00',
        'close' => '17:00',
        'times' => array_fill(0, 7, ['open' => '09:00', 'close' => '17:00']),
        'exc' => [],
    ];
}

it('returns default general opening hours seeded from working days', function () {
    $this->getJson('/api/manager/opening-hours')
        ->assertOk()
        ->assertJsonPath('data.time_mode', 'same')
        ->assertJsonPath('data.open', '09:00')
        ->assertJsonPath('data.close', '17:00')
        ->assertJsonPath('data.days', [true, true, true, true, true, false, false]);
});

it('persists the general opening hours', function () {
    $payload = sameHours();
    $payload['exc'] = [
        ['date' => '2026-12-24', 'label' => 'Aðfangadagur', 'mode' => 'hours', 'open' => '10:00', 'close' => '13:00'],
        ['date' => '2026-12-25', 'label' => 'Jóladagur', 'mode' => 'closed', 'open' => null, 'close' => null],
    ];

    $this->putJson('/api/manager/opening-hours', $payload)
        ->assertOk()
        ->assertJsonPath('data.exc.0.date', '2026-12-24')
        ->assertJsonPath('data.exc.1.mode', 'closed');

    expect(Company::find($this->company->id)->opening_hours['exc'])->toHaveCount(2);
});

it('mirrors the open days into the vacation policy working_days', function () {
    // Mon, Wed, Fri open → ISO [1, 3, 5].
    $this->putJson('/api/manager/opening-hours', sameHours([true, false, true, false, true, false, false]))
        ->assertOk();

    $policy = VacationPolicy::withoutGlobalScope('company')->where('company_id', $this->company->id)->first();
    expect($policy->working_days)->toBe([1, 3, 5]);
});

it('supports per-day times', function () {
    $payload = sameHours();
    $payload['time_mode'] = 'perday';
    $payload['times'][1] = ['open' => '11:00', 'close' => '14:00'];

    $this->putJson('/api/manager/opening-hours', $payload)
        ->assertOk()
        ->assertJsonPath('data.time_mode', 'perday')
        ->assertJsonPath('data.times.1.open', '11:00');
});

it('validates the opening hours payload', function () {
    $bad = sameHours();
    $bad['time_mode'] = 'weird';
    $this->putJson('/api/manager/opening-hours', $bad)
        ->assertStatus(422)
        ->assertJsonValidationErrors('time_mode');

    $badTime = sameHours();
    $badTime['open'] = 'nope';
    $this->putJson('/api/manager/opening-hours', $badTime)
        ->assertStatus(422)
        ->assertJsonValidationErrors('open');

    $badExc = sameHours();
    $badExc['exc'] = [['date' => '24. des', 'mode' => 'closed']];
    $this->putJson('/api/manager/opening-hours', $badExc)
        ->assertStatus(422)
        ->assertJsonValidationErrors('exc.0.date');
});

it('isolates opening hours between companies', function () {
    $this->putJson('/api/manager/opening-hours', sameHours([true, false, false, false, false, false, false]))
        ->assertOk();

    $otherCompany = Company::factory()->create();
    $otherManager = User::factory()->create(['company_id' => $otherCompany->id]);
    $otherManager->companies()->attach($otherCompany, ['role' => 'owner']);
    $this->actingAs($otherManager);

    $this->getJson('/api/manager/opening-hours')
        ->assertOk()
        ->assertJsonPath('data.days', [true, true, true, true, true, false, false]);
});

it('prevents a non-manager from accessing opening hours', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    $user->companies()->attach($this->company, ['role' => 'accountant']);
    $this->actingAs($user);

    $this->getJson('/api/manager/opening-hours')->assertForbidden();
    $this->putJson('/api/manager/opening-hours', [])->assertForbidden();
});
