<?php

namespace App\Services\ElectronicInvoicing\Exceptions;

use RuntimeException;
use Throwable;

class DispatchException extends RuntimeException
{
    public const CODE_WRONG_STATUS = 'dispatch_wrong_status';
    public const CODE_MISSING_SIGNED = 'dispatch_missing_signed_xml';
    public const CODE_SOAP_FAILED = 'dispatch_soap_failed';
    public const CODE_PACKAGING_FAILED = 'dispatch_packaging_failed';

    private string $errorCode;

    public function __construct(string $code, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $code;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public static function wrongStatus(string $current): self
    {
        return new self(
            self::CODE_WRONG_STATUS,
            sprintf('Document is in status "%s"; dispatch requires "xades_signed".', $current)
        );
    }

    public static function missingSignedXml(): self
    {
        return new self(
            self::CODE_MISSING_SIGNED,
            'Document does not have a signed XML artifact available for dispatch.'
        );
    }

    public static function soapFailed(Throwable $previous): self
    {
        return new self(
            self::CODE_SOAP_FAILED,
            'DIAN SOAP call failed: ' . $previous->getMessage(),
            $previous
        );
    }

    public static function packagingFailed(Throwable $previous): self
    {
        return new self(
            self::CODE_PACKAGING_FAILED,
            'Could not package the signed XML into the DIAN ZIP container: ' . $previous->getMessage(),
            $previous
        );
    }
}
