<?php

use App\Mail\DashboardLink;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->user->companies()->attach($this->company, ['role' => 'owner']);
});

it('requires authentication', function () {
    Mail::fake();

    $this->postJson('/api/auth/send-dashboard-link')->assertUnauthorized();

    Mail::assertNothingOutgoing();
});

it('emails the dashboard link to the signed-in user', function () {
    Mail::fake();

    $this->actingAs($this->user)
        ->postJson('/api/auth/send-dashboard-link')
        ->assertOk();

    Mail::assertSent(DashboardLink::class, function (DashboardLink $mail) {
        return $mail->hasTo($this->user->email);
    });
});

it('throttles repeated requests', function () {
    Mail::fake();

    $this->actingAs($this->user)
        ->postJson('/api/auth/send-dashboard-link')
        ->assertOk();

    $this->actingAs($this->user)
        ->postJson('/api/auth/send-dashboard-link')
        ->assertStatus(429);

    Mail::assertSent(DashboardLink::class, 1);
});

it('redirects a valid signed login link to the web login with email prefilled', function () {
    $url = URL::temporarySignedRoute('auth.login-link', now()->addMinutes(30), ['user' => $this->user->id]);

    $response = $this->get($url);

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain('/login')
        ->toContain(urlencode($this->user->email));
});

it('rejects a tampered or unsigned login link', function () {
    $this->get('/login-link/'.$this->user->id)->assertForbidden();
});
