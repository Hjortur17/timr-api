<?php

use App\Enums\SocialProvider;
use App\Models\Company;
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
    $socialUser->shouldReceive('getName')->andReturn($overrides['name'] ?? 'Social User');
    $socialUser->shouldReceive('getNickname')->andReturn($overrides['nickname'] ?? 'socialuser');
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
