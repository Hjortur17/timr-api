<?php

namespace App\Services\Billing;

use RuntimeException;

/**
 * Thrown when the billing gateway is not yet wired up (missing credentials or the
 * master `services.verifone.enabled` switch is off). Extends RuntimeException so the
 * existing controller `catch (RuntimeException)` seams surface it as the graceful
 * 503 / 501 "billing_not_configured" response.
 */
class BillingNotConfiguredException extends RuntimeException
{
    public function __construct(string $message = 'Verifone billing is not yet configured.')
    {
        parent::__construct($message);
    }
}
