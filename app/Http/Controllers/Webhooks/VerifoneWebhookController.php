<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Services\Billing\BillingNotConfiguredException;
use App\Services\Billing\PaymentHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class VerifoneWebhookController extends Controller
{
    public function __construct(private PaymentHandler $payments) {}

    public function __invoke(Request $request): JsonResponse
    {
        // Verify against the RAW body — canonicalization/signature must see exactly
        // what Verifone signed, before any JSON re-encoding.
        $raw = $request->getContent();

        try {
            $verified = $this->payments->verifyWebhook($raw, $request->headers->all());
        } catch (BillingNotConfiguredException) {
            // Gateway not wired yet — acknowledge with 501 so Verifone stops retrying
            // until the handler is live.
            return response()->json(['reason' => 'billing_not_configured'], Response::HTTP_NOT_IMPLEMENTED);
        }

        if (! $verified) {
            return response()->json(['message' => 'Invalid signature.'], Response::HTTP_FORBIDDEN);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($raw, true) ?: [];
        $eventId = (string) ($payload['eventId'] ?? $payload['event_id'] ?? '');

        // Replay protection: a redelivered event is a no-op.
        if ($eventId !== '' && WebhookEvent::where('event_id', $eventId)->exists()) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        $this->payments->handleWebhook($payload);

        $this->recordEvent($eventId, $payload);

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordEvent(string $eventId, array $payload): void
    {
        if ($eventId === '') {
            return;
        }

        try {
            WebhookEvent::create([
                'provider' => 'verifone',
                'event_id' => $eventId,
                'event_type' => $payload['eventType'] ?? $payload['event_type'] ?? null,
                'event_at' => $this->parseEventTime($payload),
                'processed_at' => now(),
            ]);
        } catch (QueryException) {
            // Concurrent delivery of the same event — the unique index won the race.
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function parseEventTime(array $payload): ?Carbon
    {
        $raw = $payload['eventDateTime'] ?? $payload['event_at'] ?? null;

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
