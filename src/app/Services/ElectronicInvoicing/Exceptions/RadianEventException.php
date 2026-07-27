<?php

namespace App\Services\ElectronicInvoicing\Exceptions;

use RuntimeException;

/**
 * Structured exception thrown by `RadianEventService`. Each constructor
 * tags the exception with a stable `error_code` so the controller and
 * frontend can surface a consistent message catalog.
 */
class RadianEventException extends RuntimeException
{
    public function __construct(private readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public static function unsupportedDocument(string $documentType): self
    {
        return new self(
            'radian_unsupported_document',
            "RADIAN events only apply to FEV documents (got [{$documentType}])."
        );
    }

    public static function documentNotAccepted(string $status): self
    {
        return new self(
            'radian_document_not_accepted',
            "RADIAN events require the parent document to be DIAN_ACCEPTED (current [{$status}])."
        );
    }

    public static function missingCufe(): self
    {
        return new self('radian_missing_cufe', 'Parent document has no CUFE.');
    }

    public static function invalidEventCode(string $code): self
    {
        return new self('radian_invalid_code', "Unknown RADIAN event code [{$code}].");
    }

    public static function alreadyAccepted(string $code): self
    {
        return new self(
            'radian_already_accepted',
            "RADIAN event [{$code}] is already DIAN_ACCEPTED for this document."
        );
    }

    public static function signingFailed(\Throwable $previous): self
    {
        $self = new self('radian_signing_failed', 'Failed to sign the RADIAN event payload.');
        return $self;
    }

    public static function soapFailed(\Throwable $previous): self
    {
        return new self('radian_soap_failed', 'SOAP error while submitting RADIAN event: ' . $previous->getMessage());
    }
}
