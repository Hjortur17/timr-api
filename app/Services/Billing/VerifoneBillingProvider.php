<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use RuntimeException;

/**
 * Verifone implementation of the billing provider.
 *
 * NOTE: Verifone integration is not yet wired up — the contract is still being
 * negotiated. These methods are intentionally stubbed so the integration seam
 * exists and can be filled in without touching the subscription lifecycle.
 */
class VerifoneBillingProvider implements BillingProvider
{
    public function startSubscription(Subscription $subscription): string
    {
        // TODO: call the Verifone API to create a recurring subscription and
        // return its external reference.
        throw new RuntimeException('Verifone billing is not yet configured.');
    }

    public function cancel(Subscription $subscription): void
    {
        // TODO: cancel the subscription at Verifone.
        throw new RuntimeException('Verifone billing is not yet configured.');
    }

    public function handleWebhook(array $payload): void
    {
        // TODO: verify signature and flip subscription status based on the event
        // (active / past_due / canceled).
        throw new RuntimeException('Verifone billing is not yet configured.');
    }
}
