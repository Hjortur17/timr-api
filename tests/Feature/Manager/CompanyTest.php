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
        'phone' => '+354 555 1234',
        'address' => 'Laugavegur 1, 101 Reykjavík',
        'email' => 'hi@acme.is',
        'locale' => 'en',
    ];

    $this->patchJson('/api/manager/company', $payload)
        ->assertOk()
        ->assertJsonPath('data.name', 'Acme ehf.')
        ->assertJsonPath('data.kennitala', '5012345679')
        ->assertJsonPath('data.email', 'hi@acme.is')
        ->assertJsonPath('data.locale', 'en');

    $this->company->refresh();
    expect($this->company->name)->toBe('Acme ehf.');
    expect($this->company->kennitala)->toBe('5012345679');
    expect($this->company->address)->toBe('Laugavegur 1, 101 Reykjavík');
    expect($this->company->locale)->toBe('en');
});

it('allows clearing optional company details', function () {
    $this->company->update(['phone' => '+354 555 0000', 'email' => 'old@acme.is']);

    $this->patchJson('/api/manager/company', [
        'name' => 'Acme ehf.',
        'phone' => null,
        'email' => null,
        'locale' => 'is',
    ])->assertOk();

    $this->company->refresh();
    expect($this->company->phone)->toBeNull();
    expect($this->company->email)->toBeNull();
});

it('requires a company name', function () {
    $this->patchJson('/api/manager/company', [
        'name' => '',
        'locale' => 'is',
    ])->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('rejects an invalid email', function () {
    $this->patchJson('/api/manager/company', [
        'name' => 'Acme ehf.',
        'email' => 'not-an-email',
        'locale' => 'is',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
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
