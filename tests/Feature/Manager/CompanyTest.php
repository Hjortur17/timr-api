<?php

use App\Models\Company;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Old Name']);
    $this->manager = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);
    $this->actingAs($this->manager);
});

it('allows a manager to update company details', function () {
    $payload = [
        'name' => 'Acme ehf.',
        'kennitala' => '5012345679',
        'locale' => 'en',
    ];

    $this->patchJson('/api/manager/company', $payload)
        ->assertOk()
        ->assertJsonPath('data.name', 'Acme ehf.')
        ->assertJsonPath('data.kennitala', '5012345679')
        ->assertJsonPath('data.locale', 'en');

    $this->company->refresh();
    expect($this->company->name)->toBe('Acme ehf.');
    expect($this->company->kennitala)->toBe('5012345679');
    expect($this->company->locale)->toBe('en');
});

it('allows clearing the optional kennitala', function () {
    $this->company->update(['kennitala' => '5012345679']);

    $this->patchJson('/api/manager/company', [
        'name' => 'Acme ehf.',
        'kennitala' => null,
        'locale' => 'is',
    ])->assertOk();

    expect($this->company->refresh()->kennitala)->toBeNull();
});

it('requires a company name', function () {
    $this->patchJson('/api/manager/company', [
        'name' => '',
        'locale' => 'is',
    ])->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('rejects an unsupported locale', function () {
    $this->patchJson('/api/manager/company', [
        'name' => 'Acme ehf.',
        'locale' => 'de',
    ])->assertUnprocessable()->assertJsonValidationErrors('locale');
});

it('does not allow a non-manager to update company details', function () {
    $company = Company::factory()->create();
    $accountant = User::factory()->create(['company_id' => $company->id]);
    $accountant->companies()->attach($company, ['role' => 'accountant']);
    $this->actingAs($accountant);

    $this->patchJson('/api/manager/company', [
        'name' => 'Hacked',
        'locale' => 'is',
    ])->assertForbidden();
});

it('requires authentication', function () {
    auth()->forgetGuards();

    $this->patchJson('/api/manager/company', [
        'name' => 'Acme ehf.',
        'locale' => 'is',
    ])->assertUnauthorized();
});
