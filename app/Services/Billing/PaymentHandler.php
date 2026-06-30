<?php

namespace App\Services\Billing;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Subscription;

/**
 * Application-level orchestration for billing actions.
 *
 * Controllers depend on this class, never on the gateway directly. It owns the
 * local Subscription state transitions (plan, seats, cancellation) and delegates
 * anything that touches the payment gateway to the injected {@see BillingProvider}
 * (Verifone). Once the Verifone contract lands, fill in VerifoneBillingProvider —
 * the call sites here already invoke it in the right places.
 */
class PaymentHandler
{
    public function __construct(private BillingProvider $provider) {}

    /**
     * Record the plan / billing period the company wants. During the trial this
     * is purely local (no charge); the chosen plan converts when the trial ends.
     */
    public function changePlan(Subscription $subscription, Plan $plan, BillingPeriod $period): Subscription
    {
        $subscription->update([
            'plan_id' => $plan->id,
            'billing_period' => $period,
        ]);

        // TODO(Verifone): if $subscription->isPaid(), push the plan change to the
        // gateway here so the next invoice reflects the new plan/price.

        return $subscription->refresh()->load('plan');
    }

    /**
     * Cancel the subscription. Cancels at the gateway first when a reference
     * exists, then records the cancellation locally.
     */
    public function cancel(Subscription $subscription): Subscription
    {
        if ($subscription->verifone_reference !== null) {
            $this->provider->cancel($subscription);
        }

        $subscription->update([
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => now(),
        ]);

        return $subscription->refresh()->load('plan');
    }

    /**
     * Begin taking real payment for the company. This is the seam that requires a
     * live gateway — VerifoneBillingProvider throws until the contract is wired,
     * which the controller surfaces as a graceful "billing not configured" response.
     *
     * @param  array<string, mixed>  $data
     */
    public function setupPaymentMethod(Subscription $subscription, array $data): PaymentMethod
    {
        // Hands off to the gateway to register the payment instrument and start
        // the recurring subscription; throws until Verifone is configured.
        $reference = $this->provider->startSubscription($subscription);

        $subscription->update(['verifone_reference' => $reference]);

        return PaymentMethod::updateOrCreate(
            ['company_id' => $subscription->company_id],
            [
                'brand' => $data['brand'] ?? null,
                'last4' => $data['last4'] ?? null,
                'exp_month' => $data['exp_month'] ?? null,
                'exp_year' => $data['exp_year'] ?? null,
                'verifone_reference' => $reference,
                'is_default' => true,
            ],
        );
    }

    /**
     * Handle an inbound gateway webhook (active / past_due / canceled events).
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload): void
    {
        $this->provider->handleWebhook($payload);
    }
}
