<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);
    $this->actingAs($this->manager);
});

it('allows a manager to upload a company logo', function () {
    $response = $this->post('/api/manager/company/logo', [
        'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
    ], ['Accept' => 'application/json']);

    $response->assertOk();
    expect($response->json('data.logo_url'))->toBeString();

    $this->company->refresh();
    expect($this->company->logo_path)->toBe("companies/{$this->company->id}/logo.png");
    Storage::disk('public')->assertExists($this->company->logo_path);
});

it('replaces the previous logo on re-upload', function () {
    $this->post('/api/manager/company/logo', [
        'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
    ], ['Accept' => 'application/json'])->assertOk();

    $oldPath = $this->company->fresh()->logo_path;

    $this->post('/api/manager/company/logo', [
        'logo' => UploadedFile::fake()->image('logo.webp', 300, 300),
    ], ['Accept' => 'application/json'])->assertOk();

    $newPath = $this->company->fresh()->logo_path;

    expect($newPath)->toBe("companies/{$this->company->id}/logo.webp");
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($newPath);
});

it('allows a manager to remove the company logo', function () {
    $this->post('/api/manager/company/logo', [
        'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
    ], ['Accept' => 'application/json'])->assertOk();

    $path = $this->company->fresh()->logo_path;

    $this->deleteJson('/api/manager/company/logo')
        ->assertOk()
        ->assertJsonPath('data.logo_url', null);

    expect($this->company->fresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('rejects a non-image upload', function () {
    $this->post('/api/manager/company/logo', [
        'logo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('logo');
});

it('rejects a disallowed image mime', function () {
    $this->post('/api/manager/company/logo', [
        'logo' => UploadedFile::fake()->image('logo.gif', 300, 300),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('logo');
});

it('rejects an oversized logo', function () {
    $this->post('/api/manager/company/logo', [
        'logo' => UploadedFile::fake()->image('logo.png', 300, 300)->size(6000),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('logo');
});

it('requires a logo file', function () {
    $this->post('/api/manager/company/logo', [], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('logo');
});

it('does not allow a non-manager to upload a logo', function () {
    $company = Company::factory()->create();
    $accountant = User::factory()->create(['company_id' => $company->id]);
    $accountant->companies()->attach($company, ['role' => 'accountant']);
    $this->actingAs($accountant);

    $this->post('/api/manager/company/logo', [
        'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
    ], ['Accept' => 'application/json'])
        ->assertForbidden();
});
