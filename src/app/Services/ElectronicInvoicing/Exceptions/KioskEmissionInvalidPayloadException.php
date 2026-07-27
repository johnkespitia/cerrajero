<?php

namespace App\Services\ElectronicInvoicing\Exceptions;

use Throwable;

/**
 * Raised when the request payload is structurally incompatible with the kiosk
 * emission flow (e.g. electronic_invoice=true without an acquirer block).
 *
 * The controller should map this to HTTP 422 with the `electronic_document`
 * key embedded in the validation errors response. Caja must NOT be blocked
 * by this exception when EI is disabled, so the controller checks the feature
 * flag before invoking the service.
 */
final class KioskEmissionInvalidPayloadException extends KioskEmissionException
{
    public const CODE_MISSING_ACQUIRER = 'missing_acquirer';
    public const CODE_INVALID_ACQUIRER = 'invalid_acquirer';
    public const CODE_INVALID_LINES = 'invalid_lines';

    public static function missingAcquirer(): self
    {
        return new self(
            self::CODE_MISSING_ACQUIRER,
            'electronic_invoice=true requires an acquirer block with at least document_type, document_number and legal_name.'
        );
    }

    public static function invalidAcquirer(string $field): self
    {
        return new self(
            self::CODE_INVALID_ACQUIRER,
            sprintf('acquirer.%s is missing or invalid.', $field)
        );
    }

    public static function invalidLines(?Throwable $previous = null): self
    {
        return new self(
            self::CODE_INVALID_LINES,
            'KioskInvoice has no consumable details to emit.',
            $previous
        );
    }
}
