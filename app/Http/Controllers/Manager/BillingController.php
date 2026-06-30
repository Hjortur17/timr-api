<?php

namespace App\Http\Controllers\Manager;

use App\Enums\BillingPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\ChangePlanRequest;
use App\Http\Requests\Manager\SetupPaymentRequest;
use App\Http\Requests\Manager\UpdateBillingEmailRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Services\Billing\PaymentHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    public function __construct(private PaymentHandler $payments) {}

    /**
     * Everything the billing page needs in one call.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $subscription = $this->subscription($request);

        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();
        $paymentMethod = PaymentMethod::where('company_id', $companyId)->first();
        $invoices = Invoice::where('company_id', $companyId)->orderByDesc('issued_at')->get();

        return response()->json([
            'data' => [
                'subscription' => $subscription ? new SubscriptionResource($subscription) : null,
                'plans' => PlanResource::collection($plans),
                'active_employees' => $subscription?->activeEmployeeCount() ?? 0,
                'payment_method' => $paymentMethod ? new PaymentMethodResource($paymentMethod) : null,
                'invoices' => InvoiceResource::collection($invoices),
            ],
        ]);
    }

    public function changePlan(ChangePlanRequest $request): JsonResponse
    {
        $subscription = $this->subscription($request);
        abort_if($subscription === null, Response::HTTP_NOT_FOUND, 'No subscription.');

        $plan = Plan::where('key', $request->validated('plan_key'))->firstOrFail();
        $period = BillingPeriod::from($request->validated('billing_period'));

        $subscription = $this->payments->changePlan($subscription, $plan, $period);

        return response()->json(['data' => new SubscriptionResource($subscription)]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $subscription = $this->subscription($request);
        abort_if($subscription === null, Response::HTTP_NOT_FOUND, 'No subscription.');

        $subscription = $this->payments->cancel($subscription);

        return response()->json(['data' => new SubscriptionResource($subscription)]);
    }

    public function setupPayment(SetupPaymentRequest $request): JsonResponse
    {
        $subscription = $this->subscription($request);
        abort_if($subscription === null, Response::HTTP_NOT_FOUND, 'No subscription.');

        try {
            $paymentMethod = $this->payments->setupPaymentMethod($subscription, $request->validated());
        } catch (RuntimeException) {
            // Gateway not wired yet (Verifone contract pending).
            return response()->json([
                'message' => 'Billing is not yet available.',
                'reason' => 'billing_not_configured',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json(['data' => new PaymentMethodResource($paymentMethod)]);
    }

    public function updateBillingEmail(UpdateBillingEmailRequest $request): JsonResponse
    {
        $subscription = $this->subscription($request);
        abort_if($subscription === null, Response::HTTP_NOT_FOUND, 'No subscription.');

        $subscription->update(['billing_email' => $request->validated('billing_email')]);

        return response()->json(['data' => new SubscriptionResource($subscription)]);
    }

    private function subscription(Request $request): ?\App\Models\Subscription
    {
        return $request->user()->company?->subscription()->with('plan')->first();
    }
}
