<?php

namespace App\Services\Billing\Verifone;

use RuntimeException;
use Throwable;

/**
 * A non-2xx / transport failure talking to the Verifone API. Carries the HTTP
 * status and a safe (already-redacted) subset of the response body for logging.
 */
class VerifoneApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
