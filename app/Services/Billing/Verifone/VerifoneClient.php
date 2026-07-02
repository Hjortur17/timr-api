<?php

namespace App\Services\Billing\Verifone;

use App\Services\Billing\BillingNotConfiguredException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for the Verifone eCommerce + Checkout APIs.
 *
 * The only class that talks to Verifone over the wire. Handles OAuth2
 * client-credentials token acquisition + caching, per-environment URL resolution,
 * idempotency headers and redacted error logging. Never logs card data or secrets.
 */
class VerifoneClient
{
    private const TOKEN_TTL_BUFFER = 60;

    /** Sensitive keys stripped from any payload before it reaches the log. */
    private const REDACT_KEYS = [
        'encrypted_card', 'encrypted_cvv', 'cvv', 'card', 'reuse_token',
        'client_secret', 'access_token', 'authorization',
    ];

    public function enabled(): bool
    {
        return (bool) config('services.verifone.enabled') && $this->hasCredentials();
    }

    /**
     * Basic auth (user-uid + API key) is the primary scheme; OAuth
     * client-credentials is an optional fallback for endpoints that require a
     * Bearer JWT.
     */
    private function hasCredentials(): bool
    {
        return $this->usesBasicAuth()
            || (filled(config('services.verifone.client_id')) && filled(config('services.verifone.client_secret')));
    }

    private function usesBasicAuth(): bool
    {
        return filled(config('services.verifone.user_id')) && filled(config('services.verifone.api_key'));
    }

    /**
     * Ensure the client is usable before an API call; throws the graceful
     * "not configured" exception otherwise.
     */
    public function ensureConfigured(): void
    {
        if (! $this->enabled()) {
            throw new BillingNotConfiguredException;
        }
    }

    /**
     * Create a hosted checkout session. Returns the decoded response.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCheckout(array $payload): array
    {
        return $this->request('post', $this->apiBaseUrl().'/checkout-service/v2/checkout', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCheckout(string $checkoutId): array
    {
        return $this->request('get', $this->apiBaseUrl().'/checkout-service/v2/checkout/'.$checkoutId);
    }

    /**
     * Create a card transaction (used for merchant-initiated recurring charges).
     * The idempotency key lets Verifone dedupe retries of the same period.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function chargeCard(array $payload, string $idempotencyKey): array
    {
        return $this->request(
            'post',
            $this->apiBaseUrl().'/api/v2/transactions/card',
            $payload,
            ['x-vfi-api-idempotencykey' => $idempotencyKey],
        );
    }

    /**
     * Fetch (and cache) the webhook-signing JWKS. `$forceRefresh` re-fetches after
     * a kid miss caused by key rotation.
     *
     * @return array<string, mixed>
     */
    public function jwks(bool $forceRefresh = false): array
    {
        $cacheKey = 'verifone:webhook_jwks:'.$this->environment();

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addDay(), function (): array {
            $response = Http::timeout($this->timeout())->acceptJson()->get($this->jwksUrl());

            if ($response->failed()) {
                throw new VerifoneApiException('Failed to fetch Verifone webhook JWKS.', $response->status());
            }

            return $response->json() ?? [];
        });
    }

    /**
     * Obtain a Bearer access token, cached until shortly before it expires.
     */
    public function token(): string
    {
        $cacheKey = 'verifone:oauth_token:'.$this->environment();

        $token = Cache::get($cacheKey);
        if (is_string($token) && $token !== '') {
            return $token;
        }

        return $this->fetchToken($cacheKey);
    }

    private function fetchToken(string $cacheKey): string
    {
        $response = Http::asForm()
            ->timeout($this->timeout())
            ->retry($this->retries(), 200, throw: false)
            ->post($this->tokenUrl(), [
                'grant_type' => 'client_credentials',
                'client_id' => (string) config('services.verifone.client_id'),
                'client_secret' => (string) config('services.verifone.client_secret'),
                'scope' => (string) config('services.verifone.scope'),
            ]);

        if ($response->failed()) {
            Log::error('Verifone OAuth token request failed', ['status' => $response->status()]);
            throw new VerifoneApiException('Verifone authentication failed.', $response->status());
        }

        $token = (string) $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 300);

        Cache::put($cacheKey, $token, max(60, $expiresIn - self::TOKEN_TTL_BUFFER));

        return $token;
    }

    /**
     * Perform an authenticated JSON request, refreshing the token once on a 401.
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $body = [], array $headers = []): array
    {
        $this->ensureConfigured();

        $response = $this->send($method, $url, $body, $headers);

        // Bearer token may have been revoked before its cache TTL elapsed — refresh
        // once. (No-op under Basic auth, where a 401 means bad credentials.)
        if ($response->status() === 401 && ! $this->usesBasicAuth()) {
            Cache::forget('verifone:oauth_token:'.$this->environment());
            $response = $this->send($method, $url, $body, $headers);
        }

        if ($response->failed()) {
            $this->logFailure($method, $url, $body, $response);
            throw new VerifoneApiException(
                'Verifone API request failed.',
                $response->status(),
                ['url' => $url, 'body' => $this->redact($response->json() ?? [])],
            );
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    private function send(string $method, string $url, array $body, array $headers): Response
    {
        $request = $this->baseRequest()->withHeaders($headers);

        return $method === 'get'
            ? $request->get($url)
            : $request->{$method}($url, $body);
    }

    private function baseRequest(): PendingRequest
    {
        $request = Http::acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->retry($this->retries(), 200, throw: false);

        return $this->usesBasicAuth()
            ? $request->withBasicAuth(
                (string) config('services.verifone.user_id'),
                (string) config('services.verifone.api_key'),
            )
            : $request->withToken($this->token());
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function logFailure(string $method, string $url, array $body, Response $response): void
    {
        Log::error('Verifone API request failed', [
            'method' => $method,
            'url' => $url,
            'status' => $response->status(),
            'request' => $this->redact($body),
            'response' => $this->redact($response->json() ?? []),
        ]);
    }

    /**
     * Recursively strip sensitive values so nothing card- or secret-related is logged.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::REDACT_KEYS, true)) {
                $data[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }

    private function environment(): string
    {
        return config('services.verifone.environment') === 'production' ? 'production' : 'sandbox';
    }

    private function region(): string
    {
        return (string) (config('services.verifone.region') ?: 'emea');
    }

    private function apiBaseUrl(): string
    {
        return $this->environment() === 'production'
            ? 'https://'.$this->region().'.gsc.verifone.cloud/oidc'
            : 'https://cst.test-gsc.vfims.com/oidc';
    }

    private function tokenUrl(): string
    {
        $host = $this->environment() === 'production'
            ? 'https://'.$this->region().'.vam.verifone.cloud'
            : 'https://cst1.test-vam.vfims.com';

        return $host.'/oauth2/realms/root/realms/VerifoneServices/access_token';
    }

    private function jwksUrl(): string
    {
        $configured = config('services.verifone.webhook_jwks_url');
        if (filled($configured)) {
            return (string) $configured;
        }

        return $this->environment() === 'production'
            ? 'https://vf11gtostorage1.blob.core.windows.net/prod-webhook-sign-keys/prod-webhook-sign-keys.jwks'
            : 'https://vf11gtostorage1.blob.core.windows.net/test-webhook-sign-keys/test-webhook-sign-keys.jwks';
    }

    private function timeout(): int
    {
        return (int) config('services.verifone.http_timeout', 15);
    }

    private function retries(): int
    {
        return (int) config('services.verifone.http_retries', 2);
    }
}
