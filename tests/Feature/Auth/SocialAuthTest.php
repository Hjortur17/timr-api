<?php

use App\Enums\SocialProvider;
use App\Models\Company;
use App\Models\Employee;
use App\Models\SocialAccount;
use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialUser;
use Laravel\Socialite\Facades\Socialite;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'password' => bcrypt('password123'),
    ]);
    $this->user->companies()->attach($this->company, ['role' => 'owner']);
});

function mockSocialiteUser(array $overrides = []): SocialUser
{
    $socialUser = Mockery::mock(SocialUser::class);
    $socialUser->shouldReceive('getId')->andReturn($overrides['id'] ?? '123456789');
    $socialUser->shouldReceive('getEmail')->andReturn($overrides['email'] ?? 'social@example.com');
    $socialUser->shouldReceive('getName')->andReturn(array_key_exists('name', $overrides) ? $overrides['name'] : 'Social User');
    $socialUser->shouldReceive('getNickname')->andReturn(array_key_exists('nickname', $overrides) ? $overrides['nickname'] : 'socialuser');
    $socialUser->shouldReceive('getAvatar')->andReturn($overrides['avatar'] ?? 'https://example.com/avatar.jpg');

    return $socialUser;
}

function mockSocialiteDriver(SocialUser $socialUser): void
{
    $driver = Mockery::mock();
    $driver->shouldReceive('stateless')->andReturnSelf();
    $driver->shouldReceive('user')->andReturn($socialUser);
    $driver->shouldReceive('userFromToken')->andReturn($socialUser);
    $driver->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    Socialite::shouldReceive('driver')->with('apple')->andReturn($driver);
}

// --- Token endpoint (mobile flow) ---

it('creates a new user via social token when no account exists', function () {
    $socialUser = mockSocialiteUser(['email' => 'newuser@example.com']);
    mockSocialiteDriver($socialUser);

    $response = $this->postJson('/api/auth/social/google', [
        'token' => 'mock-google-token',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data', 'token', 'is_new', 'message'])
        ->assertJsonPath('is_new', true)
        ->assertJsonPath('data.email', 'newuser@example.com');

    $this->assertDatabaseHas('social_accounts', [
        'provider' => 'google',
        'provider_id' => '123456789',
        'provider_email' => 'newuser@example.com',
    ]);
});

it('logs in existing user when social account already linked', function () {
    SocialAccount::factory()->create([
        'user_id' => $this->user->id,
        'provider' => SocialProvider::Google,
        'provider_id' => '123456789',
        'provider_email' => $this->user->email,
    ]);

    $socialUser = mockSocialiteUser([
        'id' => '123456789',
        'email' => $this->user->email,
    ]);
    mockSocialiteDriver($socialUser);

    $response = $this->postJson('/api/auth/social/google', [
        'token' => 'mock-google-token',
    ]);

    $response->assertOk()
        ->assertJsonPath('is_new', false)
        ->assertJsonPath('data.email', $this->user->email);
});

it('links social account to existing user with matching email', function () {
    $socialUser = mockSocialiteUser([
        'email' => $this->user->email,
        'id' => '987654321',
    ]);
    mockSocialiteDriver($socialUser);

    $response = $this->postJson('/api/auth/social/google', [
        'token' => 'mock-google-token',
    ]);

    $response->assertOk()
        ->assertJsonPath('is_new', false)
        ->assertJsonPath('data.email', $this->user->email);

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $this->user->id,
        'provider' => 'google',
        'provider_id' => '987654321',
    ]);
});

it('creates user with no password for new social login', function () {
    $socialUser = mockSocialiteUser(['email' => 'brand-new@example.com']);
    mockSocialiteDriver($socialUser);

    $this->postJson('/api/auth/social/google', [
        'token' => 'mock-google-token',
    ])->assertCreated();

    $newUser = User::withoutGlobalScope('company')
        ->where('email', 'brand-new@example.com')
        ->first();

    expect($newUser)->not->toBeNull();
    expect($newUser->password)->toBeNull();
});

// --- Apple name capture (name only arrives on first sign-in, via the client) ---

it('uses the client-provided name when the provider token has no name', function () {
    // Apple's identity token carries no name, so getName() is null.
    $socialUser = mockSocialiteUser(['name' => null, 'email' => 'apple-user@example.com']);
    mockSocialiteDriver($socialUser);

    $response = $this->postJson('/api/auth/social/apple', [
        'token' => 'mock-apple-token',
        'name' => 'Jón Jónsson',
    ]);

    $response->assertCreated()
        ->assertJsonPath('is_new', true)
        ->assertJsonPath('data.name', 'Jón Jónsson');

    $this->assertDatabaseHas('users', [
        'email' => 'apple-user@example.com',
        'name' => 'Jón Jónsson',
    ]);
});

it('prefers the provider name over the client name when the provider supplies one', function () {
    $socialUser = mockSocialiteUser(['name' => 'Provider Name', 'email' => 'google-user@example.com']);
    mockSocialiteDriver($socialUser);

    $this->postJson('/api/auth/social/google', [
        'token' => 'mock-google-token',
        'name' => 'Client Name',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Provider Name');
});

it('falls back to a default name when neither provider nor client supply one', function () {
    $socialUser = mockSocialiteUser(['name' => null, 'nickname' => null, 'email' => 'nameless@example.com']);
    mockSocialiteDriver($socialUser);

    $this->postJson('/api/auth/social/apple', [
        'token' => 'mock-apple-token',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'User');
});

it('ignores the client name for an existing user', function () {
    SocialAccount::factory()->create([
        'user_id' => $this->user->id,
        'provider' => SocialProvider::Apple,
        'provider_id' => '123456789',
        'provider_email' => $this->user->email,
    ]);

    $originalName = $this->user->name;

    $socialUser = mockSocialiteUser(['id' => '123456789', 'email' => $this->user->email]);
    mockSocialiteDriver($socialUser);

    $this->postJson('/api/auth/social/apple', [
        'token' => 'mock-apple-token',
        'name' => 'Should Be Ignored',
    ])->assertOk()
        ->assertJsonPath('data.name', $originalName);
});

// --- Name backfill for existing users ---

it('backfills a placeholder name for an existing social account when a real name arrives', function () {
    $placeholderUser = User::withoutGlobalScope('company')->create([
        'company_id' => $this->company->id,
        'name' => 'User',
        'email' => 'placeholder@example.com',
        'password' => null,
    ]);
    SocialAccount::factory()->create([
        'user_id' => $placeholderUser->id,
        'provider' => SocialProvider::Apple,
        'provider_id' => 'apple-existing-1',
        'provider_email' => $placeholderUser->email,
    ]);

    $socialUser = mockSocialiteUser(['id' => 'apple-existing-1', 'email' => $placeholderUser->email, 'name' => 'Jón Jónsson']);
    mockSocialiteDriver($socialUser);

    $this->postJson('/api/auth/social/apple', [
        'token' => 'mock-apple-token',
    ])->assertOk()
        ->assertJsonPath('is_new', false)
        ->assertJsonPath('data.name', 'Jón Jónsson');

    $this->assertDatabaseHas('users', ['id' => $placeholderUser->id, 'name' => 'Jón Jónsson']);
});

it('backfills a placeholder name when linking a social account by email', function () {
    $placeholderUser = User::withoutGlobalScope('company')->create([
        'company_id' => $this->company->id,
        'name' => 'User',
        'email' => 'link-me@example.com',
        'password' => null,
    ]);

    $socialUser = mockSocialiteUser(['id' => 'apple-link-1', 'email' => 'link-me@example.com', 'name' => 'Jón Jónsson']);
    mockSocialiteDriver($socialUser);

    $this->postJson('/api/auth/social/apple', [
        'token' => 'mock-apple-token',
    ])->assertOk()
        ->assertJsonPath('is_new', false)
        ->assertJsonPath('data.name', 'Jón Jónsson');

    $this->assertDatabaseHas('users', ['id' => $placeholderUser->id, 'name' => 'Jón Jónsson']);
});

it('does not overwrite an existing real name on social sign-in', function () {
    SocialAccount::factory()->create([
        'user_id' => $this->user->id,
        'provider' => SocialProvider::Apple,
        'provider_id' => 'apple-real-1',
        'provider_email' => $this->user->email,
    ]);
    $realName = $this->user->name;

    $socialUser = mockSocialiteUser(['id' => 'apple-real-1', 'email' => $this->user->email, 'name' => 'Should Not Replace']);
    mockSocialiteDriver($socialUser);

    $this->postJson('/api/auth/social/apple', [
        'token' => 'mock-apple-token',
    ])->assertOk()
        ->assertJsonPath('data.name', $realName);

    $this->assertDatabaseHas('users', ['id' => $this->user->id, 'name' => $realName]);
});

// --- Invite claim on first social sign-in ---

it('claims an unclaimed employee invite by matching email on first social sign-in', function () {
    $company = Company::factory()->create();
    $employee = Employee::factory()->create([
        'company_id' => $company->id,
        'user_id' => null,
        'email' => 'invited@example.com',
        'invite_token' => 'pending-invite-token',
        'invite_sent_at' => now(),
    ]);

    $socialUser = mockSocialiteUser(['email' => 'invited@example.com', 'id' => 'apple-sub-1']);
    mockSocialiteDriver($socialUser);

    $response = $this->postJson('/api/auth/social/apple', [
        'token' => 'mock-apple-token',
    ]);

    $response->assertCreated()
        ->assertJsonPath('is_new', true)
        ->assertJsonPath('data.company_id', $company->id)
        ->assertJsonPath('data.onboarding_step', 6);

    $user = User::withoutGlobalScope('company')->where('email', 'invited@example.com')->first();

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'user_id' => $user->id,
        'invite_token' => null,
        'invite_sent_at' => null,
    ]);
});

it('does not claim an employee that is already linked to a user', function () {
    $company = Company::factory()->create();
    $otherUser = User::withoutGlobalScope('company')->create([
        'company_id' => $company->id,
        'name' => 'Existing',
        'email' => 'existing-link@example.com',
        'password' => null,
    ]);
    Employee::factory()->create([
        'company_id' => $company->id,
        'user_id' => $otherUser->id,
        'email' => 'claimed@example.com',
    ]);

    $socialUser = mockSocialiteUser(['email' => 'claimed@example.com', 'id' => 'apple-sub-2']);
    mockSocialiteDriver($socialUser);

    // New user signs in with the same email an already-claimed employee uses.
    $response = $this->postJson('/api/auth/social/apple', [
        'token' => 'mock-apple-token',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.company_id', null)
        ->assertJsonPath('data.onboarding_step', 1);
});

it('leaves a brand-new owner at onboarding step 1 when no invite matches', function () {
    $socialUser = mockSocialiteUser(['email' => 'fresh-owner@example.com', 'id' => 'apple-sub-3']);
    mockSocialiteDriver($socialUser);

    $this->postJson('/api/auth/social/apple', [
        'token' => 'mock-apple-token',
    ])->assertCreated()
        ->assertJsonPath('data.company_id', null)
        ->assertJsonPath('data.onboarding_step', 1);
});

it('rejects unsupported social provider', function () {
    $this->postJson('/api/auth/social/facebook', [
        'token' => 'mock-token',
    ])->assertStatus(422);
});

it('requires token for social auth', function () {
    $this->postJson('/api/auth/social/google', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);
});

// --- Redirect endpoint ---

it('redirects to google oauth', function () {
    $socialUser = mockSocialiteUser();
    mockSocialiteDriver($socialUser);

    $response = $this->get('/api/auth/redirect/google');

    $response->assertRedirect();
});

// --- Callback endpoint ---

it('handles oauth callback and redirects with token', function () {
    $socialUser = mockSocialiteUser(['email' => 'callback@example.com']);
    mockSocialiteDriver($socialUser);

    $response = $this->get('/api/auth/callback/google');

    $response->assertRedirect();
    $redirectUrl = $response->headers->get('Location');
    expect($redirectUrl)->toContain('token=');
    expect($redirectUrl)->toContain('is_new=');
});

// --- Social accounts management ---

it('lists linked social accounts for authenticated user', function () {
    SocialAccount::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'provider' => SocialProvider::Google,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/auth/social-accounts');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('unlinks a social account', function () {
    $socialAccount = SocialAccount::factory()->create([
        'user_id' => $this->user->id,
        'provider' => SocialProvider::Google,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/auth/social-accounts/{$socialAccount->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('social_accounts', ['id' => $socialAccount->id]);
});

it('prevents unlinking last social account when user has no password', function () {
    $userNoPassword = User::withoutGlobalScope('company')->create([
        'company_id' => $this->company->id,
        'name' => 'No Password User',
        'email' => 'nopass@example.com',
        'password' => null,
    ]);

    $socialAccount = SocialAccount::factory()->create([
        'user_id' => $userNoPassword->id,
        'provider' => SocialProvider::Google,
    ]);

    $response = $this->actingAs($userNoPassword)
        ->deleteJson("/api/auth/social-accounts/{$socialAccount->id}");

    $response->assertStatus(422);
    $this->assertDatabaseHas('social_accounts', ['id' => $socialAccount->id]);
});

it('allows unlinking social account when user has a password', function () {
    $socialAccount = SocialAccount::factory()->create([
        'user_id' => $this->user->id,
        'provider' => SocialProvider::Google,
    ]);

    // User has a password (set in beforeEach), so they can unlink even their last account
    $response = $this->actingAs($this->user)
        ->deleteJson("/api/auth/social-accounts/{$socialAccount->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('social_accounts', ['id' => $socialAccount->id]);
});

it('prevents unlinking another users social account', function () {
    $otherUser = User::factory()->create(['company_id' => $this->company->id]);
    $socialAccount = SocialAccount::factory()->create([
        'user_id' => $otherUser->id,
        'provider' => SocialProvider::Google,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/auth/social-accounts/{$socialAccount->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('social_accounts', ['id' => $socialAccount->id]);
});

it('allows unlinking when user has multiple social accounts and no password', function () {
    $userNoPassword = User::withoutGlobalScope('company')->create([
        'company_id' => $this->company->id,
        'name' => 'Multi Social User',
        'email' => 'multi@example.com',
        'password' => null,
    ]);

    $googleAccount = SocialAccount::factory()->create([
        'user_id' => $userNoPassword->id,
        'provider' => SocialProvider::Google,
    ]);

    SocialAccount::factory()->create([
        'user_id' => $userNoPassword->id,
        'provider' => SocialProvider::Apple,
    ]);

    $response = $this->actingAs($userNoPassword)
        ->deleteJson("/api/auth/social-accounts/{$googleAccount->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('social_accounts', ['id' => $googleAccount->id]);
});
