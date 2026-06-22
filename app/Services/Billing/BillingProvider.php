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
     * Begin a paid subscription for the company at the gateway, returning the
     * external reference to persist on the local Subscription.
     */
    public function startSubscription(Subscription $subscription): string;

    /**
     * Cancel the subscription at the gateway.
     */
    public function cancel(Subscription $subscription): void;

    /**
     * Handle an inbound webhook payload from the gateway.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload): void;
}
