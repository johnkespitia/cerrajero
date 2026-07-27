<?php

namespace App\Services\ElectronicInvoicing\Exceptions;

use RuntimeException;
use Throwable;

class SigningException extends RuntimeException
{
    public const CODE_WRONG_STATUS = 'signing_wrong_status';
    public const CODE_MISSING_UNSIGNED = 'signing_missing_unsigned_xml';
    public const CODE_CERT_UNAVAILABLE = 'signing_certificate_unavailable';
    public const CODE_SIGNER_UNAVAILABLE = 'signing_signer_unavailable';
    public const CODE_FAILED = 'signing_failed';

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
            sprintf('Document is in status "%s"; signing requires "ubl_built".', $current)
        );
    }

    public static function missingUnsignedXml(): self
    {
        return new self(
            self::CODE_MISSING_UNSIGNED,
            'Document does not have an unsigned XML artifact available for signing.'
        );
    }

    public static function certificateUnavailable(Throwable $previous): self
    {
        return new self(
            self::CODE_CERT_UNAVAILABLE,
            'Active fiscal certificate could not be loaded: ' . $previous->getMessage(),
            $previous
        );
    }

    public static function signerUnavailable(Throwable $previous): self
    {
        return new self(
            self::CODE_SIGNER_UNAVAILABLE,
            'XAdES-EPES signer is not ready: ' . $previous->getMessage(),
            $previous
        );
    }

    public static function signingFailed(Throwable $previous): self
    {
        return new self(
            self::CODE_FAILED,
            'XAdES-EPES signing failed: ' . $previous->getMessage(),
            $previous
        );
    }
}
