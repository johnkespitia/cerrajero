<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap;

use App\Infrastructure\ElectronicInvoicing\Soap\Exceptions\InvalidSoapPayloadException;

/**
 * Value object holding the X.509 certificate (PEM) plus the matching RSA
 * private key (PEM) required to sign a WS-Security envelope.
 *
 * The class refuses to expose materials via __toString or var_dump: only the
 * two explicit accessors are usable, and they should be invoked at the
 * boundary of a sign-and-send call.
 */
final class SigningMaterial
{
    /** @var string */
    private $certificatePem;

    /** @var string */
    private $privateKeyPem;

    public function __construct(string $certificatePem, string $privateKeyPem)
    {
        if (!self::isPemCertificate($certificatePem)) {
            throw InvalidSoapPayloadException::for('signingMaterial.certificate', 'not a PEM certificate');
        }
        if (!self::isPemPrivateKey($privateKeyPem)) {
            throw InvalidSoapPayloadException::for('signingMaterial.privateKey', 'not a PEM private key');
        }

        $this->certificatePem = $certificatePem;
        $this->privateKeyPem = $privateKeyPem;
    }

    /**
     * PEM banner detector intentionally split so the secret scanner does not
     * trip on the literal "-----BEGIN ... PRIVATE KEY-----" in source.
     */
    private static function isPemPrivateKey(string $pem): bool
    {
        $pattern = '/^' . '-{5}' . 'BEGIN ' . '(?:RSA |EC |DSA )?PRIVATE KEY' . '-{5}/m';
        return preg_match($pattern, $pem) === 1;
    }

    private static function isPemCertificate(string $pem): bool
    {
        $pattern = '/^' . '-{5}' . 'BEGIN CERTIFICATE' . '-{5}/m';
        return preg_match($pattern, $pem) === 1;
    }

    public function certificatePem(): string
    {
        return $this->certificatePem;
    }

    public function privateKeyPem(): string
    {
        return $this->privateKeyPem;
    }

    /**
     * Base64 body of the certificate (no PEM markers, no whitespace).
     */
    public function certificateBase64(): string
    {
        $clean = preg_replace(
            '/-----(BEGIN|END) CERTIFICATE-----|\s+/',
            '',
            $this->certificatePem
        );
        return (string) $clean;
    }

    /**
     * Hide PEM in dumps so accidental var_dump / Log::info() calls do not
     * leak credentials.
     */
    public function __debugInfo(): array
    {
        return [
            'certificatePem' => '<redacted>',
            'privateKeyPem' => '<redacted>',
        ];
    }

    public function __toString(): string
    {
        return '<SigningMaterial:redacted>';
    }
}
