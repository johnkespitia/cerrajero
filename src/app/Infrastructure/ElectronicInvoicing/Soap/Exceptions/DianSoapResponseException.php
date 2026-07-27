<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap\Exceptions;

use RuntimeException;

/**
 * Thrown when DIAN returns a soap:Fault or a non-2xx HTTP status. Status code
 * and (sanitised) fault code are surfaced through the exception properties so
 * callers can map them via DianErrorMapper without parsing strings.
 */
final class DianSoapResponseException extends RuntimeException
{
    /** @var int */
    private $httpStatus;

    /** @var string|null */
    private $faultCode;

    public function __construct(string $message, int $httpStatus, ?string $faultCode = null)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
        $this->faultCode = $faultCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function faultCode(): ?string
    {
        return $this->faultCode;
    }
}
