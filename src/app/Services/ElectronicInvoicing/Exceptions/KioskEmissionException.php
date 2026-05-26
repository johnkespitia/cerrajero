<?php

namespace App\Services\ElectronicInvoicing\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base class for errors surfaced by KioskInvoiceEmissionService.
 *
 * Each subclass carries a stable machine-readable code that the controller
 * propagates verbatim under `electronic_document_error.code`. The free-text
 * message is human-readable and NEVER contains certificate material, PINs,
 * software_security_code or PII beyond what is already part of the request.
 */
abstract class KioskEmissionException extends RuntimeException
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
