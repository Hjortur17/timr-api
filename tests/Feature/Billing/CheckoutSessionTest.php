<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->plan = Plan::updateOrCreate(
        ['key' => 'nettur'],
        ['name' => 'Nettur', 'price_monthly' => 2490, 'price_yearly' => 2075, 'max_employees' => 15, 'is_active' => true, 'sort_order' => 1],
    );
    $this->company = Company::factory()->create();
    $this->manager = User::factory()->create(['company_id' => $this->company->id]);
    $this->manager->companies()->attach($this->company, ['role' => 'owner']);

    config()->set([
        'services.verifone.enabled' => true,
        'services.verifone.environment' => 'sandbox',
        'services.verifone.region' => 'emea',
        'services.verifone.user_id' => 'user-1',
        'services.verifone.api_key' => 'apikey-1',
        'services.verifone.entity_id' => 'entity-1',
        'services.verifone.payment_contract_id' => 'contract-1',
        'services.verifone.currency' => 'ISK',
    ]);
});

it('creates a hosted checkout session and stores the checkout id', function () {
    Subscription::factory()->trialing()->create(['company_id' => $this->company->id, 'plan_id' => $this->plan->id]);

    Http::fake([
        'cst.test-gsc.vfims.com/oidc/checkout-service/v2/checkout' => Http::response([
            'id' => 'chk_9001',
            'redirect_url' => 'https://pay.verifone.test/redirect/chk_9001',
            'status' => 'PENDING',
        ], 201),
    ]);

    $this->actingAs($this->manager)
        ->postJson('/api/manager/billing/checkout-session', [])
        ->assertOk()
        ->assertJsonPath('data.checkout_id', 'chk_9001')
        ->assertJsonPath('data.url', 'https://pay.verifone.test/redirect/chk_9001');

    expect($this->company->subscription->refresh()->verifone_checkout_id)->toBe('chk_9001');

    // Trial tokenization: CARD_CAPTURE only — no transaction, so no amount/currency, and no
    // stored_credentials/token_preference (the token scope is applied via the Verifone-Central link).
    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/checkout-service/v2/checkout')) {
            return false;
        }
        $body = $request->data();
        $card = $body['configurations']['card'];

        return ! array_key_exists('amount', $body)
            && ! array_key_exists('currency_code', $body)
            && $card['mode'] === 'CARD_CAPTURE'
            && $card['card_capture_mode'] === 'v2'
            && $card['payment_contract_id'] === 'contract-1'
            && $request->hasHeader('Authorization'); // Basic auth
    });
});

it('surfaces a gateway rejection as billing_error, not billing_not_configured', function () {
    Subscription::factory()->trialing()->create(['company_id' => $this->company->id, 'plan_id' => $this->plan->id]);

    Http::fake([
        'cst.test-gsc.vfims.com/oidc/checkout-service/v2/checkout' => Http::response([
            'code' => 127, 'name' => 'UNEXPECTED_PARAMETERS_ERROR', 'message' => 'nope',
        ], 400),
    ]);

    $this->actingAs($this->manager)
        ->postJson('/api/manager/billing/checkout-session', [])
        ->assertStatus(502)
        ->assertJsonPath('reason', 'billing_error');
});

it('still returns 503 billing_not_configured when disabled', function () {
    config()->set('services.verifone.enabled', false);
    Subscription::factory()->trialing()->create(['company_id' => $this->company->id, 'plan_id' => $this->plan->id]);

    $this->actingAs($this->manager)
        ->postJson('/api/manager/billing/checkout-session', [])
        ->assertStatus(503)
        ->assertJsonPath('reason', 'billing_not_configured');
});
