<?php

namespace App\Services\Billing;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Services\Billing\Verifone\VerifoneClient;
use App\Services\Billing\Verifone\WebhookVerifier;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Verifone implementation of the billing provider.
 *
 * Recurring billing uses hosted Checkout to enrol a stored credential during the
 * trial (card capture, no charge), then merchant-initiated charges we schedule
 * ourselves. We hold only Verifone's opaque reference IDs — never a token or card.
 */
class VerifoneBillingProvider implements BillingProvider
{
    /** Transaction statuses that count as a successful charge. */
    private const SUCCESS_STATUSES = ['AUTHORIZED', 'CAPTURED', 'SETTLED', 'APPROVED', 'COMPLETED'];

    public function __construct(
        private VerifoneClient $client,
        private WebhookVerifier $verifier,
    ) {}

    public function createCheckoutSession(Subscription $subscription, array $opts = []): array
    {
        $this->client->ensureConfigured();

        // Trial tokenization: hosted CARD_CAPTURE just captures/tokenizes the card and
        // returns a reuse token — no transaction, so no amount/currency, and the checkout
        // does NOT accept stored_credentials/token_preference (the token scope is applied
        // automatically via the Verifone-Central link to the Merchant Site). The recurring
        // mandate + stored-credential reference are established on the first MIT charge.
        $payload = [
            'entity_id' => (string) config('services.verifone.entity_id'),
            'interaction_type' => 'HPP',
            'merchant_reference' => 'sub-'.$subscription->id.'-signup',
            'return_url' => (string) config('services.verifone.checkout_return_url'),
            'configurations' => ['card' => [
                'mode' => 'CARD_CAPTURE',
                'card_capture_mode' => 'v2',
                'payment_contract_id' => (string) config('services.verifone.payment_contract_id'),
            ]],
        ];

        $response = $this->client->createCheckout($payload);

        return [
            'id' => (string) ($response['id'] ?? ''),
            'url' => (string) ($response['redirect_url'] ?? $response['url'] ?? ''),
            'status' => (string) ($response['status'] ?? 'PENDING'),
        ];
    }

    public function chargeRecurring(Subscription $subscription, int $amount, string $idempotencyKey): ChargeResult
    {
        $this->client->ensureConfigured();

        if (! $subscription->canChargeRecurring()) {
            return ChargeResult::declined(null, 'no_stored_credential');
        }

        $timeUnit = $subscription->billing_period === BillingPeriod::Yearly ? 'YEAR' : 'MONTH';

        // The first charge signs up the stored credential (establishing the reference +
        // scheme_reference); subsequent charges reuse it.
        $isFirstCharge = $subscription->verifone_stored_credential_ref === null;

        $storedCredential = [
            'processing_model_details' => [
                'processing_model' => 'RECURRING',
                'payment_frequency' => ['time_unit' => $timeUnit, 'value' => 1],
            ],
        ];

        if ($isFirstCharge) {
            $storedCredential['stored_credential_type'] = 'SIGNUP';
        } else {
            $storedCredential['stored_credential_type'] = 'CHARGE';
            $storedCredential['reference'] = $subscription->verifone_stored_credential_ref;
            $storedCredential['scheme_reference'] = $subscription->verifone_scheme_reference;
        }

        $response = $this->client->chargeCard([
            'payment_provider_contract' => $subscription->verifone_payment_contract_id
                ?: (string) config('services.verifone.payment_contract_id'),
            'amount' => $amount,
            'currency_code' => $this->currency(),
            'auth_type' => 'FINAL_AUTH',
            'capture_now' => true,
            'merchant_reference' => $this->merchantReference($subscription),
            'reuse_token' => $subscription->verifone_reuse_token,
            'reuse_token_type' => 'INTERNAL',
            'stored_credential' => $storedCredential,
        ], $idempotencyKey);

        $status = strtoupper((string) ($response['status'] ?? ''));

        if (! in_array($status, self::SUCCESS_STATUSES, true)) {
            return ChargeResult::declined($status, (string) ($response['error_message'] ?? 'declined'), $response['id'] ?? null);
        }

        return ChargeResult::approved(
            $response['id'] ?? null,
            $status,
            $response['authorization_code'] ?? null,
            $response['rrn'] ?? null,
            Arr::get($response, 'stored_credential.scheme_reference') ?? $subscription->verifone_scheme_reference,
            Arr::get($response, 'stored_credential.reference') ?? $subscription->verifone_stored_credential_ref,
        );
    }

    public function cancel(Subscription $subscription): void
    {
        // Reference-only model: there is no gateway-side recurring object to cancel —
        // we simply stop initiating charges. Kept as a hook (and audit log) in case a
        // future flow needs to suspend the stored credential at Verifone.
        Log::info('Verifone subscription cancellation (local stop of MIT charges)', [
            'subscription_id' => $subscription->id,
        ]);
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $this->client->ensureConfigured();

        return $this->verifier->verify($rawBody, $headers);
    }

    public function handleWebhook(array $payload): void
    {
        $this->client->ensureConfigured();

        $eventType = (string) ($payload['eventType'] ?? $payload['event_type'] ?? '');
        $content = (array) ($payload['content'] ?? $payload);

        match (true) {
            $this->isTokenizationEvent($eventType, $content) => $this->onSignupComplete($payload, $content),
            $this->isApproval($eventType) => $this->onChargeApproved($content),
            $this->isDecline($eventType) => $this->onChargeDeclined($content),
            $this->isReversal($eventType) => $this->onReversal($content),
            default => Log::info('Verifone webhook ignored', ['event_type' => $eventType]),
        };
    }

    /**
     * Card captured + stored credential enrolled: persist the reference IDs we
     * charge against and record the display card. Status stays trialing until the
     * first real charge runs.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $content
     */
    private function onSignupComplete(array $payload, array $content): void
    {
        $subscription = $this->locateSubscription($payload, $content);
        if ($subscription === null) {
            return;
        }

        $updates = [
            'verifone_stored_credential_ref' => $this->pick($content, ['stored_credential.reference', 'stored_credential_reference', 'reference'])
                ?? $subscription->verifone_stored_credential_ref,
            'verifone_scheme_reference' => $this->pick($content, ['stored_credential.scheme_reference', 'scheme_reference'])
                ?? $subscription->verifone_scheme_reference,
            'verifone_payment_contract_id' => $this->pick($content, ['payment_contract_id', 'payment_provider_contract'])
                ?: (string) config('services.verifone.payment_contract_id'),
            'verifone_reference' => $this->pick($payload, ['eventId', 'recordId']) ?? $subscription->verifone_reference,
        ];

        // The reuse token is the handle we charge against — store it (encrypted) and its scope.
        $reuseToken = $this->pick($content, ['reuse_token', 'card.reuse_token', 'token', 'card.token']);
        if ($reuseToken !== null) {
            $updates['verifone_reuse_token'] = $reuseToken;
            $updates['verifone_token_scope'] = $this->pick($content, ['token_scope', 'card.token_scope'])
                ?: (string) config('services.verifone.token_scope_id');
        }

        $subscription->update($updates);

        $this->upsertPaymentMethod($subscription->company_id, $content);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function onChargeApproved(array $content): void
    {
        $subscription = $this->subscriptionFromMerchantReference($content);
        if ($subscription === null) {
            return;
        }

        // The scheduler already recorded the invoice + advanced the period from the
        // synchronous charge response; the webhook just confirms the paid state.
        if (! $subscription->isPaid()) {
            $subscription->update(['status' => SubscriptionStatus::Active]);
        }
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function onChargeDeclined(array $content): void
    {
        $subscription = $this->subscriptionFromMerchantReference($content);
        $subscription?->update(['status' => SubscriptionStatus::PastDue]);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function onReversal(array $content): void
    {
        $reference = $this->pick($content, ['id', 'transaction_id', 'original_transaction_id']);
        if ($reference === null) {
            return;
        }

        \App\Models\Invoice::where('verifone_reference', $reference)->update(['status' => 'refunded']);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function upsertPaymentMethod(int $companyId, array $content): void
    {
        PaymentMethod::updateOrCreate(
            ['company_id' => $companyId],
            [
                'brand' => $this->pick($content, ['card.scheme', 'card.brand', 'payment_product', 'brand']),
                'last4' => $this->pick($content, ['card.last_four', 'card.last4', 'masked_card_number_last4', 'last4']),
                'exp_month' => $this->pick($content, ['card.expiry_month', 'expiry_month', 'exp_month']),
                'exp_year' => $this->pick($content, ['card.expiry_year', 'expiry_year', 'exp_year']),
                'is_default' => true,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $content
     */
    private function locateSubscription(array $payload, array $content): ?Subscription
    {
        $checkoutId = $this->pick($content, ['checkout_id', 'id']) ?? $this->pick($payload, ['recordId', 'eventId']);
        if ($checkoutId !== null) {
            $match = Subscription::where('verifone_checkout_id', $checkoutId)->first();
            if ($match !== null) {
                return $match;
            }
        }

        return $this->subscriptionFromMerchantReference($content);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function subscriptionFromMerchantReference(array $content): ?Subscription
    {
        $reference = (string) ($this->pick($content, ['merchant_reference', 'merchantReference']) ?? '');

        if (preg_match('/^sub-(\d+)-/', $reference, $matches) === 1) {
            return Subscription::find((int) $matches[1]);
        }

        return null;
    }

    private function isTokenizationEvent(string $eventType, array $content): bool
    {
        $type = strtolower($eventType);

        return str_contains($type, 'token')
            || str_contains($type, 'checkout')
            || (Arr::get($content, 'stored_credential.reference') !== null || isset($content['stored_credential_reference']));
    }

    private function isApproval(string $eventType): bool
    {
        return in_array($eventType, ['TxnSaleApproved', 'TxnCaptureApproved', 'TxnAuthorisationApproved'], true);
    }

    private function isDecline(string $eventType): bool
    {
        return in_array($eventType, ['TxnSaleDeclined', 'TxnCaptureDeclined', 'TxnAuthorisationDeclined'], true);
    }

    private function isReversal(string $eventType): bool
    {
        return in_array($eventType, ['TxnRefundApproved', 'TxnVoidApproved'], true);
    }

    /**
     * First non-null value across the given dot-notation candidate paths.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $paths
     */
    private function pick(array $data, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = Arr::get($data, $path);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function merchantReference(Subscription $subscription): string
    {
        return 'sub-'.$subscription->id.'-p'.($subscription->payment_sequence + 1);
    }

    private function currency(): string
    {
        return (string) config('services.verifone.currency', 'ISK');
    }
}
