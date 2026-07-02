<?php

namespace App\Services\Billing;

use App\Models\Subscription;

/**
 * Abstraction over the external billing provider (Verifone).
 *
 * Implementations talk to the payment gateway; the rest of the app depends only
 * on this interface so swapping or stubbing the provider never touches
 * controllers, middleware or the subscription lifecycle.
 */
interface BillingProvider
{
    /**
     * Create a hosted checkout session so the customer can enter their card on
     * Verifone's page and enrol a recurring stored credential. Returns the session
     * id and the redirect URL to send the customer to.
     *
     * @param  array{charge_now?: bool}  $opts
     * @return array{id: string, url: string, status: string}
     */
    public function createCheckoutSession(Subscription $subscription, array $opts = []): array;

    /**
     * Run a merchant-initiated recurring charge against the stored credential.
     */
    public function chargeRecurring(Subscription $subscription, int $amount, string $idempotencyKey): ChargeResult;

    /**
     * Cancel the recurring credential at the gateway (best-effort; local
     * period-end cancellation is owned by PaymentHandler).
     */
    public function cancel(Subscription $subscription): void;

    /**
     * Verify the authenticity of an inbound webhook (detached JWS signature) over
     * the raw request body.
     *
     * @param  array<string, list<string|null>>  $headers
     */
    public function verifyWebhook(string $rawBody, array $headers): bool;

    /**
     * Handle a verified inbound webhook payload from the gateway.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload): void;
}
