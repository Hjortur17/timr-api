<?php

namespace App\Services\Billing\Verifone;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWKSet;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWS;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Mmccook\JsonCanonicalizator\JsonCanonicalizatorFactory;
use Throwable;

/**
 * Verifies the detached JWS signature Verifone attaches to webhook deliveries
 * (`x-vfi-jws` header). The signed content is the RFC 8785 (JCS) canonicalization
 * of the JSON body; the RS256 public key is selected from Verifone's JWKS by kid.
 *
 * No shared secret is involved — authenticity rests entirely on this signature,
 * so a failure here must reject the webhook.
 */
class WebhookVerifier
{
    private const SIGNATURE_HEADER = 'x-vfi-jws';

    private JWSVerifier $verifier;

    private CompactSerializer $serializer;

    public function __construct(private VerifoneClient $client)
    {
        $this->verifier = new JWSVerifier(new AlgorithmManager([new RS256]));
        $this->serializer = new CompactSerializer;
    }

    /**
     * @param  array<string, list<string|null>>  $headers
     */
    public function verify(string $rawBody, array $headers): bool
    {
        $token = $this->header($headers, self::SIGNATURE_HEADER);
        if ($token === null || $token === '') {
            return false;
        }

        $decoded = json_decode($rawBody, true);
        if (! is_array($decoded)) {
            return false;
        }

        try {
            $jws = $this->serializer->unserialize($token);
        } catch (Throwable) {
            return false;
        }

        // The signed payload should be the canonical JSON; fall back to the raw body
        // in case Verifone signs the untransformed bytes.
        $canonical = JsonCanonicalizatorFactory::getInstance()->canonicalize($decoded);
        $payloads = array_values(array_unique([$canonical, $rawBody]));

        // Try the cached JWKS first; on failure, refresh once to pick up rotated keys.
        return $this->verifyAgainst($jws, $this->jwkSet(false), $payloads)
            || $this->verifyAgainst($jws, $this->jwkSet(true), $payloads);
    }

    /**
     * @param  list<string>  $payloads
     */
    private function verifyAgainst(JWS $jws, ?JWKSet $jwkSet, array $payloads): bool
    {
        if ($jwkSet === null || $jwkSet->count() === 0) {
            return false;
        }

        $embedded = $jws->getPayload();

        try {
            // Attached-payload JWS: verify the embedded payload as-is.
            if ($embedded !== null && $embedded !== '') {
                return $this->verifier->verifyWithKeySet($jws, $jwkSet, 0, null);
            }

            // Detached JWS: try each candidate payload.
            foreach ($payloads as $payload) {
                if ($this->verifier->verifyWithKeySet($jws, $jwkSet, 0, $payload)) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    private function jwkSet(bool $forceRefresh): ?JWKSet
    {
        try {
            return JWKSet::createFromKeyData($this->client->jwks($forceRefresh));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, list<string|null>>  $headers
     */
    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $values) {
            if (strtolower((string) $key) === $name) {
                $value = is_array($values) ? ($values[0] ?? null) : $values;

                return $value !== null ? (string) $value : null;
            }
        }

        return null;
    }
}
