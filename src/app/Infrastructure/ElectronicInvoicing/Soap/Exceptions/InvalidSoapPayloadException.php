<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a SOAP action receives a parameter map missing required fields
 * or carrying clearly invalid values (empty trackId, non-base64 contentFile,
 * negative range, ...).
 *
 * Message format references the parameter name. The actual values are never
 * echoed.
 */
final class InvalidSoapPayloadException extends InvalidArgumentException
{
    public static function for(string $param, string $reason = 'missing'): self
    {
        return new self(sprintf('SOAP parameter "%s" is %s.', $param, $reason));
    }
}
