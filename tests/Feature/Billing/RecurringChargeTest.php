<?php

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\PaymentHandler;
use Illuminate\Support\Facades\Http;
use Ramsey\Uuid\Uuid;

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
        'services.verifone.payment_contract_id' => 'contract-1',
        'services.verifone.currency' => 'ISK',
    ]);
});

/** @param array<string,mixed> $overrides */
function dueSubscription(int $companyId, int $planId, array $overrides = []): Subscription
{
    return Subscription::factory()->create(array_merge([
        'company_id' => $companyId,
        'plan_id' => $planId,
        'status' => SubscriptionStatus::Active,
        'trial_ends_at' => now()->subDays(40),
        'grace_ends_at' => now()->subDays(33),
        'current_period_ends_at' => now()->subDay(),
        'verifone_reuse_token' => 'rtok-1',
        'verifone_token_scope' => 'scope-1',
        'verifone_stored_credential_ref' => 'scref-1',
        'verifone_scheme_reference' => 'sch-1',
        'verifone_payment_contract_id' => 'contract-1',
        'payment_sequence' => 1,
    ], $overrides));
}

it('charges a due subscription (renewal), records an invoice and advances the period', function () {
    $sub = dueSubscription($this->company->id, $this->plan->id);

    Http::fake([
        'cst.test-gsc.vfims.com/oidc/api/v2/transactions/card' => Http::response([
            'id' => 'txn_1', 'status' => 'AUTHORIZED', 'authorization_code' => 'A1', 'rrn' => 'R1',
            'stored_credential' => ['scheme_reference' => 'sch-2'],
        ], 201),
    ]);

    $this->artisan('subscriptions:charge')->assertSuccessful();

    $sub->refresh();
    expect($sub->status)->toBe(SubscriptionStatus::Active);
    expect($sub->payment_sequence)->toBe(2);
    expect($sub->current_period_ends_at->isFuture())->toBeTrue();
    expect($sub->verifone_scheme_reference)->toBe('sch-2');

    $invoice = Invoice::where('company_id', $this->company->id)->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->amount)->toBe(2490);
    expect($invoice->status)->toBe('paid');
    expect($invoice->verifone_reference)->toBe('txn_1');

    // Deterministic per-period idempotency key (next sequence = 2); renewal → CHARGE via reuse token.
    $expectedKey = Uuid::uuid5('7c9f2a3e-4b1d-4f6a-9c2e-1a2b3c4d5e6f', 'sub:'.$sub->id.':seq:2')->toString();
    Http::assertSent(function ($request) use ($expectedKey) {
        if (! str_contains($request->url(), '/transactions/card')) {
            return false;
        }
        $body = $request->data();

        return $request->hasHeader('x-vfi-api-idempotencykey', $expectedKey)
            && $body['reuse_token'] === 'rtok-1'
            && $body['stored_credential']['stored_credential_type'] === 'CHARGE'
            && $body['stored_credential']['reference'] === 'scref-1';
    });
});

it('signs up the stored credential on the first charge and stores the returned reference', function () {
    $sub = dueSubscription($this->company->id, $this->plan->id, [
        'verifone_stored_credential_ref' => null,
        'verifone_scheme_reference' => null,
        'payment_sequence' => 0,
    ]);

    Http::fake([
        'cst.test-gsc.vfims.com/oidc/api/v2/transactions/card' => Http::response([
            'id' => 'txn_first', 'status' => 'AUTHORIZED',
            'stored_credential' => ['reference' => 'scref-new', 'scheme_reference' => 'sch-new'],
        ], 201),
    ]);

    $this->artisan('subscriptions:charge')->assertSuccessful();

    $sub->refresh();
    expect($sub->verifone_stored_credential_ref)->toBe('scref-new');
    expect($sub->verifone_scheme_reference)->toBe('sch-new');
    expect($sub->payment_sequence)->toBe(1);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/transactions/card')) {
            return false;
        }

        return $request->data()['stored_credential']['stored_credential_type'] === 'SIGNUP';
    });
});

it('marks the subscription past_due on a decline and writes no invoice', function () {
    $sub = dueSubscription($this->company->id, $this->plan->id);

    Http::fake([
        'cst.test-gsc.vfims.com/oidc/api/v2/transactions/card' => Http::response([
            'id' => 'txn_2', 'status' => 'DECLINED', 'error_message' => 'insufficient_funds',
        ], 201),
    ]);

    app(PaymentHandler::class)->chargeDue($sub);

    $sub->refresh();
    expect($sub->status)->toBe(SubscriptionStatus::PastDue);
    expect($sub->payment_sequence)->toBe(1);
    expect(Invoice::where('company_id', $this->company->id)->count())->toBe(0);
});

it('does not double-charge once the period has been advanced', function () {
    $sub = dueSubscription($this->company->id, $this->plan->id);

    Http::fake([
        'cst.test-gsc.vfims.com/oidc/api/v2/transactions/card' => Http::response([
            'id' => 'txn_3', 'status' => 'AUTHORIZED',
        ], 201),
    ]);

    $this->artisan('subscriptions:charge')->assertSuccessful();
    // Second run: the subscription is no longer due (period advanced), so it is skipped.
    $this->artisan('subscriptions:charge')->assertSuccessful();

    expect(Invoice::where('company_id', $this->company->id)->count())->toBe(1);
    expect($sub->refresh()->payment_sequence)->toBe(2);
});

it('skips subscriptions pending cancellation', function () {
    dueSubscription($this->company->id, $this->plan->id, ['canceled_at' => now()]);

    $this->artisan('subscriptions:charge')->assertSuccessful();

    expect(Invoice::where('company_id', $this->company->id)->count())->toBe(0);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/transactions/card'));
});
