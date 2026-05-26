<?php

namespace App\Services\ElectronicInvoicing\Exceptions;

use RuntimeException;

class InvalidCertificateException extends RuntimeException
{
    public const CODE_EMPTY_PAYLOAD = 'certificate_empty';
    public const CODE_CANNOT_OPEN = 'certificate_cannot_open';
    public const CODE_MALFORMED = 'certificate_malformed';
    public const CODE_MISSING_CN = 'certificate_missing_cn';
    public const CODE_MISSING_SERIAL = 'certificate_missing_serial';
    public const CODE_MISSING_VALIDITY = 'certificate_missing_validity';
    public const CODE_EXPIRED = 'certificate_expired';
    public const CODE_DUPLICATE = 'certificate_duplicate_fingerprint';

    private string $errorCode;

    public function __construct(string $code, string $message)
    {
        parent::__construct($message);
        $this->errorCode = $code;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public static function emptyPayload(): self
    {
        return new self(self::CODE_EMPTY_PAYLOAD, 'Certificate payload is empty.');
    }

    public static function cannotOpen(): self
    {
        return new self(self::CODE_CANNOT_OPEN, 'PKCS#12 container could not be opened. Verify the password and the file integrity.');
    }

    public static function malformedCertificate(): self
    {
        return new self(self::CODE_MALFORMED, 'X.509 certificate inside the PKCS#12 container is malformed.');
    }

    public static function missingCommonNames(): self
    {
        return new self(self::CODE_MISSING_CN, 'Certificate is missing subject or issuer common name (CN).');
    }

    public static function missingSerialNumber(): self
    {
        return new self(self::CODE_MISSING_SERIAL, 'Certificate is missing serial number.');
    }

    public static function missingValidity(): self
    {
        return new self(self::CODE_MISSING_VALIDITY, 'Certificate is missing validity window (notBefore/notAfter).');
    }

    public static function expired(): self
    {
        return new self(self::CODE_EXPIRED, 'Certificate is already expired and cannot be activated.');
    }

    public static function duplicateFingerprint(string $fingerprint): self
    {
        return new self(self::CODE_DUPLICATE, sprintf('A certificate with fingerprint %s is already registered.', $fingerprint));
    }
}
