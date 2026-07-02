<?php

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWKSet;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Mmccook\JsonCanonicalizator\JsonCanonicalizatorFactory;

beforeEach(function () {
    $this->plan = Plan::updateOrCreate(
        ['key' => 'nettur'],
        ['name' => 'Nettur', 'price_monthly' => 2490, 'price_yearly' => 2075, 'max_employees' => 15, 'is_active' => true, 'sort_order' => 1],
    );
    $this->company = Company::factory()->create();

    config()->set([
        'services.verifone.enabled' => true,
        'services.verifone.user_id' => 'user-1',
        'services.verifone.api_key' => 'apikey-1',
        'services.verifone.webhook_jwks_url' => 'https://jwks.verifone.test/keys',
    ]);

    $this->signingKey = JWKFactory::createRSAKey(2048, ['alg' => 'RS256', 'use' => 'sig', 'kid' => 'k1']);
    $jwks = (new JWKSet([$this->signingKey->toPublic()]))->jsonSerialize();

    Http::fake(['jwks.verifone.test/keys' => Http::response($jwks)]);
});

/**
 * Build a detached JWS over the RFC 8785 canonicalization of the payload — mirrors
 * exactly what Verifone signs.
 */
function signDetached(array $payload, $key): string
{
    $canonical = JsonCanonicalizatorFactory::getInstance()->canonicalize($payload);

    $jws = (new JWSBuilder(new AlgorithmManager([new RS256])))
        ->create()
        ->withPayload($canonical, true)
        ->addSignature($key, ['alg' => 'RS256', 'kid' => 'k1'])
        ->build();

    return (new CompactSerializer)->serialize($jws, 0);
}

it('accepts a correctly signed webhook and records the event', function () {
    Subscription::factory()->paid()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
    ]);

    $sub = $this->company->subscription;
    $payload = ['eventType' => 'TxnSaleApproved', 'eventId' => 'evt-1', 'content' => ['merchant_reference' => "sub-{$sub->id}-p1"]];
    $token = signDetached($payload, $this->signingKey);

    $this->call('POST', '/api/webhooks/verifone', [], [], [], ['HTTP_X-VFI-JWS' => $token], json_encode($payload))
        ->assertOk()
        ->assertJsonPath('received', true);

    expect(WebhookEvent::where('event_id', 'evt-1')->exists())->toBeTrue();
});

it('rejects a tampered body', function () {
    $signed = ['eventType' => 'TxnSaleApproved', 'eventId' => 'evt-2', 'content' => ['amount' => 2490]];
    $token = signDetached($signed, $this->signingKey);

    // Send a different body with the signature computed over $signed.
    $tampered = ['eventType' => 'TxnSaleApproved', 'eventId' => 'evt-2', 'content' => ['amount' => 999999]];

    $this->call('POST', '/api/webhooks/verifone', [], [], [], ['HTTP_X-VFI-JWS' => $token], json_encode($tampered))
        ->assertStatus(403);
});

it('rejects a webhook with no signature header', function () {
    $payload = ['eventType' => 'TxnSaleApproved', 'eventId' => 'evt-3'];

    $this->postJson('/api/webhooks/verifone', $payload)
        ->assertStatus(403);
});

it('processes a repeated eventId only once', function () {
    Subscription::factory()->paid()->create(['company_id' => $this->company->id, 'plan_id' => $this->plan->id]);
    $sub = $this->company->subscription;

    $payload = ['eventType' => 'TxnSaleApproved', 'eventId' => 'evt-dup', 'content' => ['merchant_reference' => "sub-{$sub->id}-p1"]];
    $token = signDetached($payload, $this->signingKey);

    $this->call('POST', '/api/webhooks/verifone', [], [], [], ['HTTP_X-VFI-JWS' => $token], json_encode($payload))->assertOk();
    $this->call('POST', '/api/webhooks/verifone', [], [], [], ['HTTP_X-VFI-JWS' => $token], json_encode($payload))
        ->assertOk()
        ->assertJsonPath('duplicate', true);

    expect(WebhookEvent::where('event_id', 'evt-dup')->count())->toBe(1);
});

it('stores stored-credential references and a payment method on signup completion', function () {
    $sub = Subscription::factory()->trialing()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'verifone_checkout_id' => 'chk_x',
    ]);

    $payload = [
        'eventType' => 'CheckoutComplete',
        'eventId' => 'evt-signup',
        'content' => [
            'checkout_id' => 'chk_x',
            'reuse_token' => 'rtok-9',
            'token_scope' => 'scope-9',
            'payment_contract_id' => 'contract-1',
            'card' => ['scheme' => 'VISA', 'last_four' => '4242', 'expiry_month' => 12, 'expiry_year' => 2030],
        ],
    ];
    $token = signDetached($payload, $this->signingKey);

    $this->call('POST', '/api/webhooks/verifone', [], [], [], ['HTTP_X-VFI-JWS' => $token], json_encode($payload))
        ->assertOk();

    $sub->refresh();
    expect($sub->verifone_reuse_token)->toBe('rtok-9');
    expect($sub->verifone_token_scope)->toBe('scope-9');
    expect($sub->canChargeRecurring())->toBeTrue();

    // Token is encrypted at rest — the raw column value is not the plaintext.
    $raw = DB::table('subscriptions')->where('id', $sub->id)->value('verifone_reuse_token');
    expect($raw)->not->toBe('rtok-9');

    $pm = PaymentMethod::where('company_id', $this->company->id)->first();
    expect($pm)->not->toBeNull();
    expect($pm->brand)->toBe('VISA');
    expect($pm->last4)->toBe('4242');

    // Signup does not activate — still trialing until the first charge.
    expect($sub->status)->toBe(SubscriptionStatus::Trialing);
});
