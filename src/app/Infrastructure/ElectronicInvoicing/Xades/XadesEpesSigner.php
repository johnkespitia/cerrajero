<?php

namespace App\Infrastructure\ElectronicInvoicing\Xades;

use App\Domain\ElectronicInvoicing\Ports\CertificateProviderInterface;
use App\Domain\ElectronicInvoicing\Ports\XadesSignerInterface;
use DOMDocument;
use InvalidArgumentException;
use RuntimeException;

/**
 * XAdES-EPES signer for UBL 2.1 fiscal documents per DIAN.
 *
 * Status (slice cufe-and-xades):
 *  - Validates the unsigned XML payload and the signing inputs.
 *  - Exposes building-block crypto helpers (SHA-256 digest, RSA-SHA256 signing)
 *    that the next slice will wire into the full XAdES envelope.
 *  - sign() intentionally raises XadesEpesSigningUnavailableException because
 *    the full XAdES-EPES envelope, the DIAN SignaturePolicyIdentifier and the
 *    xmlsec1 / xmlseclibs canonicalization are still pending.
 *  - Never accepts, logs or echoes certificate password, private key or PIN.
 *
 * Deuda explicita para el siguiente slice:
 *  - Conectar fixtures oficiales DIAN (SignaturePolicy hash y URLs).
 *  - Integrar robrichards/xmlseclibs o llamada controlada a xmlsec1 para
 *    canonicalizacion exclusiva y firma con SignedProperties XAdES.
 *  - Endurecer assertExtensions con la lista exacta de capacidades runtime
 *    requeridas en el contenedor cerrajero (xmlsec1 binary, ext-dom, ext-libxml).
 */
final class XadesEpesSigner implements XadesSignerInterface
{
    /** @var CertificateProviderInterface */
    private $certificateProvider;

    /** @var array<string, mixed> */
    private $signatureConfig;

    /**
     * @param array<string, mixed> $signatureConfig Typically config('electronic-invoicing.signature').
     */
    public function __construct(CertificateProviderInterface $certificateProvider, array $signatureConfig)
    {
        $this->certificateProvider = $certificateProvider;
        $this->signatureConfig = $signatureConfig;
    }

    public function sign(string $unsignedXml, string $certificateAlias): string
    {
        $this->assertCryptoExtensions();
        $this->assertValidUnsignedXml($unsignedXml);
        $this->assertValidAlias($certificateAlias);
        $this->assertPolicyConfigured();

        // Reserved hook: once xmlseclibs/xmlsec1 + DIAN fixtures are wired,
        // the full XAdES-EPES envelope will be produced here using
        // $this->certificateProvider->load(...) and $this->signatureConfig.
        throw new XadesEpesSigningUnavailableException(
            'XAdES-EPES full signing is not yet wired in this build. '
            . 'Connect xmlsec1 / xmlseclibs and the DIAN signature policy fixtures before issuing real documents.'
        );
    }

    /**
     * Returns the base64-encoded SHA-256 digest of the payload.
     *
     * Building block for ds:DigestValue under SignedInfo.
     */
    public function digestSha256(string $payload): string
    {
        return base64_encode(hash('sha256', $payload, true));
    }

    /**
     * Signs an arbitrary payload with RSA-SHA256 using a PEM-encoded private key.
     *
     * Returns the base64-encoded signature (ds:SignatureValue format).
     *
     * The private key material is consumed in-process only; this method never
     * persists or logs it. Errors raised by openssl are surfaced as generic
     * RuntimeException without echoing key bytes.
     *
     * @param string $payload     Raw bytes to sign (typically the canonicalized SignedInfo).
     * @param string $privateKeyPem PEM-encoded RSA private key (already decrypted).
     */
    public function signRawRsaSha256(string $payload, string $privateKeyPem): string
    {
        if ($privateKeyPem === '') {
            throw new InvalidArgumentException('Private key material is required.');
        }

        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false) {
            throw new InvalidArgumentException('Could not parse the provided private key.');
        }

        $signature = '';
        $ok = openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256);

        if (!$ok || $signature === '') {
            throw new RuntimeException('RSA-SHA256 signing failed.');
        }

        return base64_encode($signature);
    }

    public function signatureAlgorithm(): string
    {
        return (string) ($this->signatureConfig['algorithm'] ?? 'RSA-SHA256');
    }

    public function canonicalizationMethod(): string
    {
        return (string) ($this->signatureConfig['canonicalization']
            ?? 'http://www.w3.org/2001/10/xml-exc-c14n#');
    }

    private function assertCryptoExtensions(): void
    {
        if (!extension_loaded('openssl')) {
            throw new XadesEpesSigningUnavailableException(
                'PHP OpenSSL extension is required to produce DIAN signatures.'
            );
        }
        if (!extension_loaded('dom') || !extension_loaded('libxml')) {
            throw new XadesEpesSigningUnavailableException(
                'PHP DOM / libxml extensions are required to canonicalize UBL XML.'
            );
        }
    }

    private function assertValidUnsignedXml(string $unsignedXml): void
    {
        if (trim($unsignedXml) === '') {
            throw new InvalidArgumentException('Unsigned XML payload must not be empty.');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument();
            $loaded = $dom->loadXML(
                $unsignedXml,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded) {
            throw new InvalidArgumentException('Unsigned XML is not well-formed XML.');
        }
    }

    private function assertValidAlias(string $certificateAlias): void
    {
        if (trim($certificateAlias) === '') {
            throw new InvalidArgumentException('Certificate alias must not be empty.');
        }
    }

    private function assertPolicyConfigured(): void
    {
        foreach (['policy_oid', 'policy_url', 'policy_hash_b64'] as $key) {
            if (empty($this->signatureConfig[$key])) {
                throw new XadesEpesSigningUnavailableException(
                    'DIAN signature policy is not fully configured.'
                );
            }
        }
    }
}
