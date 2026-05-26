<?php

namespace App\Services\ElectronicInvoicing\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base class for errors surfaced by CreditDebitNoteService.
 *
 * Mirrors the contract of KioskEmissionException and ReservationEmissionException:
 * each subclass carries a stable machine-readable code that the controller
 * propagates verbatim under `electronic_document_error.code` for 422 cases or
 * inside the response envelope for configuration gaps.
 */
abstract class CreditDebitNoteException extends RuntimeException
{
    /** @var string */
    private $emissionCode;

    public function __construct(string $code, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->emissionCode = $code;
    }

    public function emissionCode(): string
    {
        return $this->emissionCode;
    }
}
