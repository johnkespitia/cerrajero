<?php

namespace App\Infrastructure\ElectronicInvoicing\Secrets;

use RuntimeException;

/**
 * Raised when a secret reference cannot be resolved.
 *
 * The exception message contains the reference key only (e.g. "hab/pin"); it
 * NEVER carries the literal secret. Callers should rethrow as a structured
 * "configuration_missing" error rather than surfacing the raw message to API
 * clients.
 */
final class SecretUnavailableException extends RuntimeException
{
    public static function for(string $ref): self
    {
        return new self(sprintf('Secret reference "%s" could not be resolved.', $ref));
    }
}
