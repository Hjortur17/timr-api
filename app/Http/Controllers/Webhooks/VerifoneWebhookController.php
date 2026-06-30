<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Billing\PaymentHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class VerifoneWebhookController extends Controller
{
    public function __construct(private PaymentHandler $payments) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $this->payments->handleWebhook($request->all());
        } catch (RuntimeException) {
            // Gateway not wired yet — acknowledge so Verifone does not retry once
            // the endpoint exists but the handler is still stubbed.
            return response()->json(['reason' => 'billing_not_configured'], Response::HTTP_NOT_IMPLEMENTED);
        }

        return response()->json(['received' => true]);
    }
}
