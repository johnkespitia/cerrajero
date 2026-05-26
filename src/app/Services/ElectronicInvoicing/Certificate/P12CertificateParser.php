<?php

namespace App\Services\ElectronicInvoicing\Certificate;

use App\Services\ElectronicInvoicing\Exceptions\InvalidCertificateException;
use DateTimeImmutable;

/**
 * Reads a PKCS#12 (.p12 / .pfx) container and extracts the metadata that
 * fiscal-admin needs to persist on `fiscal_certificates`.
 *
 * The parser is intentionally side-effect free: it consumes raw bytes and
 * a password and returns a value object. It never writes to disk and
 * never logs the password or the private key. Callers are responsible
 * for routing the raw payload to a disk-backed storage and the password
 * to a secret manager.
 *
 * Implementation notes:
 *  - Uses `openssl_pkcs12_read`, which is part of the PHP openssl ext.
 *  - SHA-256 fingerprint is computed on the X.509 certificate (PEM-decoded)
 *    so it matches what `openssl x509 -fingerprint -sha256 -in cert.crt`
 *    would produce locally.
 *  - We deliberately tolerate certificates that do not yet include all
 *    common DN attributes (issuerName may have OU/O only); the only
 *    hard requirements are subject CN, issuer CN, serial, validity.
 */
class P12CertificateParser
{
    /**
     * @return ParsedCertificate
     *
     * @throws InvalidCertificateException
     */
    public function parse(string $p12Bytes, string $password): ParsedCertificate
    {
        if ($p12Bytes === '') {
            throw InvalidCertificateException::emptyPayload();
        }

        $certs = [];
        $previous = libxml_use_internal_errors(true);
        try {
            $opened = @openssl_pkcs12_read($p12Bytes, $certs, $password);
        } finally {
            libxml_use_internal_errors($previous);
        }
        if (! $opened || ! is_array($certs) || empty($certs['cert'])) {
            throw InvalidCertificateException::cannotOpen();
        }

        $certPem = (string) $certs['cert'];
        $resource = @openssl_x509_read($certPem);
        if ($resource === false) {
            throw InvalidCertificateException::malformedCertificate();
        }
        try {
            $details = @openssl_x509_parse($resource, true);
        } finally {
            // PHP < 8.0 returns a resource, >= 8.0 returns OpenSSLCertificate
            // (no explicit close required for the object variant).
            if (is_resource($resource)) {
                @openssl_x509_free($resource);
            }
        }
        if (! is_array($details)) {
            throw InvalidCertificateException::malformedCertificate();
        }

        $subjectCn = (string) ($details['subject']['CN'] ?? '');
        $issuerCn = (string) ($details['issuer']['CN'] ?? '');
        if ($subjectCn === '' || $issuerCn === '') {
            throw InvalidCertificateException::missingCommonNames();
        }

        $serial = isset($details['serialNumberHex']) && is_string($details['serialNumberHex'])
            ? strtoupper($details['serialNumberHex'])
            : (isset($details['serialNumber']) ? (string) $details['serialNumber'] : '');
        if ($serial === '') {
            throw InvalidCertificateException::missingSerialNumber();
        }

        $notBefore = isset($details['validFrom_time_t']) && (int) $details['validFrom_time_t'] > 0
            ? (new DateTimeImmutable('@' . (int) $details['validFrom_time_t']))
            : null;
        $notAfter = isset($details['validTo_time_t']) && (int) $details['validTo_time_t'] > 0
            ? (new DateTimeImmutable('@' . (int) $details['validTo_time_t']))
            : null;
        if ($notBefore === null || $notAfter === null) {
            throw InvalidCertificateException::missingValidity();
        }

        $fingerprint = $this->fingerprintSha256($certPem);

        return new ParsedCertificate(
            subjectCn: $subjectCn,
            issuerCn: $issuerCn,
            serialNumber: $serial,
            notBefore: $notBefore,
            notAfter: $notAfter,
            fingerprintSha256: $fingerprint,
        );
    }

    private function fingerprintSha256(string $certPem): string
    {
        $clean = preg_replace('/-----[A-Z ]+-----|\s+/', '', $certPem) ?? '';
        $der = base64_decode($clean, true);
        if ($der === false || $der === '') {
            throw InvalidCertificateException::malformedCertificate();
        }

        return hash('sha256', $der);
    }
}
