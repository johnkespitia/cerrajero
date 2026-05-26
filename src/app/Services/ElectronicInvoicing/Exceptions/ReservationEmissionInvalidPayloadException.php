<?php

namespace App\Services\ElectronicInvoicing\Exceptions;

use Throwable;

/**
 * Raised when the reservation checkout payload is structurally incompatible
 * with FEV emission (missing acquirer, no consumable lines, etc.). The
 * controller should map this to HTTP 422 with `electronic_document_error`
 * embedded in the response.
 */
final class ReservationEmissionInvalidPayloadException extends ReservationEmissionException
{
    public const CODE_MISSING_ACQUIRER = 'missing_acquirer';
    public const CODE_INVALID_ACQUIRER = 'invalid_acquirer';
    public const CODE_INVALID_LINES = 'invalid_lines';

    public static function missingAcquirer(): self
    {
        return new self(
            self::CODE_MISSING_ACQUIRER,
            'electronic_invoice=true on a reservation checkout requires an acquirer block with at least document_type, document_number and legal_name.'
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
            'Reservation has no billable concepts (lodging, services, minibar or room charges) to emit.',
            $previous
        );
    }
}
