<?php

namespace App\Services\Billing;

/**
 * Outcome of a merchant-initiated recurring charge, decoupled from the gateway's
 * wire format so PaymentHandler can act on it without knowing Verifone specifics.
 */
readonly class ChargeResult
{
    public function __construct(
        public bool $success,
        public ?string $transactionId = null,
        public ?string $status = null,
        public ?string $authorizationCode = null,
        public ?string $rrn = null,
        public ?string $schemeReference = null,
        public ?string $storedCredentialReference = null,
        public ?string $declineReason = null,
    ) {}

    public static function approved(
        ?string $transactionId,
        ?string $status,
        ?string $authorizationCode = null,
        ?string $rrn = null,
        ?string $schemeReference = null,
        ?string $storedCredentialReference = null,
    ): self {
        return new self(true, $transactionId, $status, $authorizationCode, $rrn, $schemeReference, $storedCredentialReference);
    }

    public static function declined(?string $status, ?string $reason = null, ?string $transactionId = null): self
    {
        return new self(false, $transactionId, $status, declineReason: $reason);
    }
}
