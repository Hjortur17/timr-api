<?php

namespace App\Services\Billing;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Application-level orchestration for billing actions.
 *
 * Controllers depend on this class, never on the gateway directly. It owns the
 * local Subscription state transitions (plan, seats, cancellation, invoicing) and
 * delegates anything that touches the payment gateway to the injected
 * {@see BillingProvider} (Verifone).
 */
class PaymentHandler
{
    /** Fixed UUID namespace for deterministic per-period idempotency keys. */
    private const IDEMPOTENCY_NAMESPACE = '7c9f2a3e-4b1d-4f6a-9c2e-1a2b3c4d5e6f';

    public function __construct(private BillingProvider $provider) {}

    /**
     * Record the plan / billing period the company wants. During the trial this
     * is purely local (no charge); the chosen plan converts when the trial ends.
     * Under the merchant-initiated model a paid plan change simply applies at the
     * next period's charge — no immediate gateway call (no proration).
     */
    public function changePlan(Subscription $subscription, Plan $plan, BillingPeriod $period): Subscription
    {
        $subscription->update([
            'plan_id' => $plan->id,
            'billing_period' => $period,
        ]);

        return $subscription->refresh()->load('plan');
    }

    /**
     * Start Verifone's hosted checkout so the customer can enter their card and
     * enrol a recurring stored credential. Returns the redirect URL; the card and
     * references are captured later via the completion webhook.
     *
     * @param  array{charge_now?: bool}  $opts
     * @return array{id: string, url: string, status: string}
     */
    public function createCheckoutSession(Subscription $subscription, array $opts = []): array
    {
        $session = $this->provider->createCheckoutSession($subscription, $opts);

        $subscription->update(['verifone_checkout_id' => $session['id']]);

        return $session;
    }

    /**
     * Cancel the subscription at period end: stop future charges but retain access
     * until the current period ends. Best-effort gateway cancellation first.
     */
    public function cancel(Subscription $subscription): Subscription
    {
        if ($subscription->canChargeRecurring() || $subscription->verifone_reference !== null) {
            $this->provider->cancel($subscription);
        }

        // Paid subscription: keep access until the period ends, then the scheduler
        // flips it to Canceled. Trial/unpaid: cancel immediately.
        $subscription->update($subscription->isPaid()
            ? ['canceled_at' => now()]
            : ['status' => SubscriptionStatus::Canceled, 'canceled_at' => now()]);

        return $subscription->refresh()->load('plan');
    }

    /**
     * Run the due recurring charge for a subscription. Creates a paid Invoice and
     * advances the period on success; flips to past_due on decline.
     */
    public function chargeDue(Subscription $subscription): ChargeResult
    {
        $amount = $this->periodAmount($subscription);
        $key = $this->idempotencyKey($subscription);

        $result = $this->provider->chargeRecurring($subscription, $amount, $key);

        if (! $result->success) {
            $subscription->update(['status' => SubscriptionStatus::PastDue]);

            return $result;
        }

        DB::transaction(function () use ($subscription, $amount, $result) {
            $periodStart = $subscription->current_period_ends_at?->isFuture()
                ? $subscription->current_period_ends_at
                : now();
            $periodEnd = $this->nextPeriodEnd($subscription, $periodStart);

            Invoice::create([
                'company_id' => $subscription->company_id,
                'number' => $this->invoiceNumber($subscription),
                'amount' => $amount,
                'currency' => (string) config('services.verifone.currency', 'ISK'),
                'status' => 'paid',
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'issued_at' => now(),
                'paid_at' => now(),
                'verifone_reference' => $result->transactionId,
            ]);

            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'current_period_ends_at' => $periodEnd,
                'payment_sequence' => $subscription->payment_sequence + 1,
                'last_charge_at' => now(),
                // The first (SIGNUP) charge returns the stored-credential reference we
                // reuse for subsequent renewals.
                'verifone_stored_credential_ref' => $result->storedCredentialReference ?? $subscription->verifone_stored_credential_ref,
                'verifone_scheme_reference' => $result->schemeReference ?? $subscription->verifone_scheme_reference,
            ]);
        });

        return $result;
    }

    /**
     * Handle an inbound gateway webhook (verified upstream in the controller).
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload): void
    {
        $this->provider->handleWebhook($payload);
    }

    /**
     * Verify a raw webhook body's signature.
     *
     * @param  array<string, list<string|null>>  $headers
     */
    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        return $this->provider->verifyWebhook($rawBody, $headers);
    }

    private function periodAmount(Subscription $subscription): int
    {
        $plan = $subscription->plan;

        return (int) ($subscription->billing_period === BillingPeriod::Yearly
            ? $plan->price_yearly
            : $plan->price_monthly);
    }

    private function nextPeriodEnd(Subscription $subscription, \Illuminate\Support\Carbon $from): \Illuminate\Support\Carbon
    {
        return $subscription->billing_period === BillingPeriod::Yearly
            ? $from->copy()->addYear()
            : $from->copy()->addMonth();
    }

    /**
     * Deterministic per-period key: retries of the same period reuse it (Verifone
     * dedupes), so a timeout or re-run never double-charges.
     */
    private function idempotencyKey(Subscription $subscription): string
    {
        return Uuid::uuid5(
            self::IDEMPOTENCY_NAMESPACE,
            'sub:'.$subscription->id.':seq:'.($subscription->payment_sequence + 1),
        )->toString();
    }

    private function invoiceNumber(Subscription $subscription): string
    {
        return sprintf('INV-%d-%04d', $subscription->company_id, $subscription->payment_sequence + 1);
    }
}
