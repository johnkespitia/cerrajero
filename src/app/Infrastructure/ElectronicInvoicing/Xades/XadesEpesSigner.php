<?php

namespace App\Infrastructure\ElectronicInvoicing\Xades;

use App\Domain\ElectronicInvoicing\Ports\CertificateProviderInterface;
use App\Domain\ElectronicInvoicing\Ports\XadesSignerInterface;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use RuntimeException;

/**
 * XAdES-EPES signer for UBL 2.1 fiscal documents per DIAN.
 *
 * Produces an enveloped XAdES-EPES signature located at:
 *
 *   /Invoice/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/ds:Signature
 *
 * Cryptographic profile (matches the DIAN signature policy):
 *  - DigestMethod: SHA-256
 *  - SignatureMethod: RSA-SHA256
 *  - Canonicalization (signed elements + SignedInfo): Exclusive XML
 *    Canonicalization (xml-exc-c14n) via DOMNode::C14N(true).
 *
 * Implementation notes / explicit debt (next slices/deploys):
 *  - The signature only embeds the **end-entity** X.509 certificate.
 *    Production deployments must also include the intermediate CA chain
 *    in `ds:KeyInfo/ds:X509Data` so DIAN's signature validator accepts
 *    the trust path. The `chain_pem` argument is honoured when present.
 *  - We intentionally rely on the bundled libxml C14N implementation
 *    instead of `xmlsec1` / `robrichards/xmlseclibs`. Final acceptance
 *    by DIAN requires an external XAdES validator (xmlsec1 with the
 *    XAdES profile, or a dedicated Java tool) wired in CI before
 *    cutover to PROD. See R-02 in the spec.
 *  - All inputs that contain key material (private key, password) are
 *    consumed in-process only and are never logged.
 */
final class XadesEpesSigner implements XadesSignerInterface
{
    public const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';
    public const NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';
    public const NS_EXT = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';

    public const DIGEST_METHOD = 'http://www.w3.org/2001/04/xmlenc#sha256';
    public const SIGNATURE_METHOD = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
    public const CANONICALIZATION_METHOD = 'http://www.w3.org/2001/10/xml-exc-c14n#';

    /** @var CertificateProviderInterface */
    private $certificateProvider;

    /** @var array<string, mixed> */
    private $signatureConfig;

    /**
     * Material bank for signing requests originated through `sign($xml, $alias)`.
     *
     * Each entry follows the `signWithMaterial()` parameter shape:
     *   ['certificate' => string PEM, 'private_key' => string PEM, 'chain_pem' => string|null]
     *
     * @var array<string, array{certificate:string, private_key:string, chain_pem?:string|null}>
     */
    private array $materialByAlias = [];

    /**
     * @param array<string, mixed> $signatureConfig Typically config('electronic-invoicing.signature').
     */
    public function __construct(CertificateProviderInterface $certificateProvider, array $signatureConfig)
    {
        $this->certificateProvider = $certificateProvider;
        $this->signatureConfig = $signatureConfig;
    }

    /**
     * Pre-load key material for a given alias so subsequent calls to
     * `sign($xml, $alias)` can resolve cert/key without going back to the
     * provider. Used by `SigningCoordinator` and tests.
     *
     * @param array{certificate:string, private_key:string, chain_pem?:string|null} $material
     */
    public function withMaterial(string $alias, array $material): self
    {
        $clone = clone $this;
        $clone->materialByAlias = $this->materialByAlias;
        $clone->materialByAlias[$alias] = $material;

        return $clone;
    }

    public function sign(string $unsignedXml, string $certificateAlias): string
    {
        $this->assertCryptoExtensions();
        $this->assertValidUnsignedXml($unsignedXml);
        $this->assertValidAlias($certificateAlias);
        $this->assertPolicyConfigured();

        if (! isset($this->materialByAlias[$certificateAlias])) {
            throw new XadesEpesSigningUnavailableException(
                'No key material is bound to the given certificate alias. '
                . 'Pre-load it through withMaterial() before invoking sign().'
            );
        }
        $material = $this->materialByAlias[$certificateAlias];

        return $this->signWithMaterial($unsignedXml, $material);
    }

    /**
     * Direct entry point used by `SigningCoordinator`: signs the XML
     * with the supplied material without going through the alias bank.
     *
     * @param array{certificate:string, private_key:string, chain_pem?:string|null} $material
     */
    public function signWithMaterial(string $unsignedXml, array $material): string
    {
        $this->assertCryptoExtensions();
        $this->assertValidUnsignedXml($unsignedXml);
        $this->assertPolicyConfigured();
        $this->assertMaterial($material);

        $document = $this->loadDocument($unsignedXml);
        $root = $document->documentElement;
        if ($root === null) {
            throw new InvalidArgumentException('Unsigned XML has no root element.');
        }

        $extensionContent = $this->ensureExtensionContent($document, $root);

        $signatureId = 'Signature-' . $this->randomToken();
        $signedInfoId = 'SignedInfo-' . $signatureId;
        $signedPropertiesId = 'SignedProperties-' . $signatureId;
        $keyInfoId = 'KeyInfo-' . $signatureId;
        $signedPropertiesRefId = 'Reference-XAdES-' . $signatureId;
        $keyInfoRefId = 'Reference-KeyInfo-' . $signatureId;
        $documentRefId = 'Reference-Document-' . $signatureId;

        $signature = $document->createElementNS(self::NS_DS, 'ds:Signature');
        $signature->setAttribute('Id', $signatureId);
        $extensionContent->appendChild($signature);

        // SignedInfo placeholder; we fill the digests after we attach
        // KeyInfo + SignedProperties because their canonical form depends
        // on context inherited namespaces.
        $signedInfo = $document->createElementNS(self::NS_DS, 'ds:SignedInfo');
        $signedInfo->setAttribute('Id', $signedInfoId);
        $signature->appendChild($signedInfo);

        $canonicalizationMethod = $document->createElementNS(self::NS_DS, 'ds:CanonicalizationMethod');
        $canonicalizationMethod->setAttribute('Algorithm', self::CANONICALIZATION_METHOD);
        $signedInfo->appendChild($canonicalizationMethod);

        $signatureMethod = $document->createElementNS(self::NS_DS, 'ds:SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', self::SIGNATURE_METHOD);
        $signedInfo->appendChild($signatureMethod);

        $signatureValue = $document->createElementNS(self::NS_DS, 'ds:SignatureValue', '');
        $signatureValue->setAttribute('Id', 'SignatureValue-' . $signatureId);

        $keyInfo = $this->buildKeyInfo($document, $material, $keyInfoId);
        $object = $document->createElementNS(self::NS_DS, 'ds:Object');
        $qualifyingProperties = $document->createElementNS(self::NS_XADES, 'xades:QualifyingProperties');
        $qualifyingProperties->setAttribute('Target', '#' . $signatureId);
        $object->appendChild($qualifyingProperties);

        $signedProperties = $this->buildSignedProperties(
            $document,
            $material['certificate'],
            $signedPropertiesId
        );
        $qualifyingProperties->appendChild($signedProperties);

        $signature->appendChild($signatureValue);
        $signature->appendChild($keyInfo);
        $signature->appendChild($object);

        // Build references AFTER the elements are attached so the C14N
        // captures inherited namespaces from the surrounding document.
        $documentReference = $this->buildDocumentReference($document, $root, $documentRefId);
        $signedInfo->appendChild($documentReference);

        $signedPropertiesReference = $this->buildSignedPropertiesReference(
            $document,
            $signedProperties,
            $signedPropertiesRefId,
            $signedPropertiesId
        );
        $signedInfo->appendChild($signedPropertiesReference);

        $keyInfoReference = $this->buildKeyInfoReference($document, $keyInfo, $keyInfoRefId, $keyInfoId);
        $signedInfo->appendChild($keyInfoReference);

        // Canonicalize SignedInfo and sign it.
        $canonicalSignedInfo = $signedInfo->C14N(true, false);
        if ($canonicalSignedInfo === false || $canonicalSignedInfo === '') {
            throw new RuntimeException('Failed to canonicalize SignedInfo.');
        }
        $signatureValue->nodeValue = $this->signRawRsaSha256($canonicalSignedInfo, $material['private_key']);

        $signed = $document->saveXML();
        if ($signed === false || $signed === '') {
            throw new RuntimeException('Failed to serialize signed XML.');
        }

        return $signed;
    }

    /**
     * Full structural + cryptographic verification.
     *
     * Steps:
     *  1. For each `ds:Reference`, recompute the digest according to its
     *     URI + transforms and compare against the stored `ds:DigestValue`.
     *     A mismatch on any reference -> the document body or properties
     *     were tampered with.
     *  2. Canonicalize `SignedInfo` (exclusive C14N) and verify its
     *     signature against the X.509 certificate embedded in `KeyInfo`.
     *
     * Returns true when the envelope verifies end-to-end. Returns false
     * on any structural or cryptographic mismatch.
     *
     * This is NOT a full XSD/Schematron + DIAN policy validation -- that
     * requires `xmlsec1` with the XAdES profile (deuda explicita en R-02).
     */
    public function verifySignature(string $signedXml): bool
    {
        if (! extension_loaded('openssl') || ! extension_loaded('dom')) {
            return false;
        }

        $document = $this->loadDocument($signedXml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('ds', self::NS_DS);

        $signature = $xpath->query('//ds:Signature')->item(0);
        if (! $signature instanceof DOMElement) {
            return false;
        }

        $signedInfo = $xpath->query('ds:SignedInfo', $signature)->item(0);
        $signatureValue = $xpath->query('ds:SignatureValue', $signature)->item(0);
        $x509 = $xpath->query('ds:KeyInfo//ds:X509Certificate', $signature)->item(0);
        if (! $signedInfo instanceof DOMElement || $signatureValue === null || $x509 === null) {
            return false;
        }

        // 1. Verify every reference digest.
        $references = $xpath->query('ds:Reference', $signedInfo);
        foreach ($references as $reference) {
            if (! $reference instanceof DOMElement) {
                continue;
            }
            if (! $this->verifyReference($reference, $document, $xpath)) {
                return false;
            }
        }

        // 2. Verify SignedInfo signature.
        $canonicalSignedInfo = $signedInfo->C14N(true, false);
        if ($canonicalSignedInfo === false) {
            return false;
        }
        $rawSignature = base64_decode(preg_replace('/\s+/', '', $signatureValue->nodeValue) ?? '', true);
        if ($rawSignature === false) {
            return false;
        }
        $certPem = $this->wrapCertificatePem(preg_replace('/\s+/', '', $x509->nodeValue) ?? '');
        $publicKey = @openssl_pkey_get_public($certPem);
        if ($publicKey === false) {
            return false;
        }

        return openssl_verify($canonicalSignedInfo, $rawSignature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    private function verifyReference(DOMElement $reference, DOMDocument $document, DOMXPath $xpath): bool
    {
        $uri = $reference->getAttribute('URI');
        $digestValueNode = $xpath->query('ds:DigestValue', $reference)->item(0);
        if ($digestValueNode === null) {
            return false;
        }
        $expected = trim((string) $digestValueNode->nodeValue);

        $targetNode = $this->resolveReferenceTarget($uri, $document, $xpath);
        if ($targetNode === null) {
            return false;
        }

        $hasEnvelopedTransform = false;
        $transformNodes = $xpath->query('ds:Transforms/ds:Transform', $reference);
        foreach ($transformNodes as $transform) {
            if ($transform instanceof DOMElement
                && $transform->getAttribute('Algorithm') === 'http://www.w3.org/2000/09/xmldsig#enveloped-signature') {
                $hasEnvelopedTransform = true;
                break;
            }
        }

        if ($hasEnvelopedTransform && $targetNode instanceof DOMElement) {
            $canonical = $this->canonicalizeWithoutSignature($targetNode);
        } else {
            $canonical = (string) $targetNode->C14N(true, false);
        }

        $actual = base64_encode(hash('sha256', $canonical, true));

        return hash_equals($expected, $actual);
    }

    private function resolveReferenceTarget(string $uri, DOMDocument $document, DOMXPath $xpath): ?\DOMNode
    {
        if ($uri === '' || $uri === null) {
            return $document->documentElement;
        }
        if (strpos($uri, '#') === 0) {
            $id = substr($uri, 1);
            // Walk every element manually because not every Id attribute is
            // declared as xml:id in the XSD; this keeps the verification
            // independent from optional DTDs.
            $candidates = $xpath->query(sprintf('//*[@Id="%s"]', addslashes($id)));
            if ($candidates->length > 0) {
                return $candidates->item(0);
            }
        }

        return null;
    }

    /**
     * Base64 SHA-256 digest of the payload. Building block for ds:DigestValue.
     */
    public function digestSha256(string $payload): string
    {
        return base64_encode(hash('sha256', $payload, true));
    }

    /**
     * RSA-SHA256 signature over the payload using a PEM-encoded private key.
     * Returns the base64-encoded signature (ds:SignatureValue format).
     *
     * Private key material is consumed in-process only and is never logged.
     */
    public function signRawRsaSha256(string $payload, string $privateKeyPem): string
    {
        if ($privateKeyPem === '') {
            throw new InvalidArgumentException('Private key material is required.');
        }

        $key = @openssl_pkey_get_private($privateKeyPem);
        if ($key === false) {
            throw new InvalidArgumentException('Could not parse the provided private key.');
        }

        $signature = '';
        $ok = openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256);

        if (! $ok || $signature === '') {
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
        return (string) ($this->signatureConfig['canonicalization'] ?? self::CANONICALIZATION_METHOD);
    }

    // -------------------------------------------------------------------
    // Internal builders
    // -------------------------------------------------------------------

    private function ensureExtensionContent(DOMDocument $document, DOMElement $root): DOMElement
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('ext', self::NS_EXT);

        $extensions = $xpath->query('ext:UBLExtensions', $root)->item(0);
        if (! $extensions instanceof DOMElement) {
            $extensions = $document->createElementNS(self::NS_EXT, 'ext:UBLExtensions');
            if ($root->firstChild !== null) {
                $root->insertBefore($extensions, $root->firstChild);
            } else {
                $root->appendChild($extensions);
            }
        }

        $extension = $document->createElementNS(self::NS_EXT, 'ext:UBLExtension');
        $extensions->appendChild($extension);

        $extensionContent = $document->createElementNS(self::NS_EXT, 'ext:ExtensionContent');
        $extension->appendChild($extensionContent);

        return $extensionContent;
    }

    /**
     * @param array{certificate:string, chain_pem?:string|null} $material
     */
    private function buildKeyInfo(DOMDocument $document, array $material, string $keyInfoId): DOMElement
    {
        $keyInfo = $document->createElementNS(self::NS_DS, 'ds:KeyInfo');
        $keyInfo->setAttribute('Id', $keyInfoId);

        $x509Data = $document->createElementNS(self::NS_DS, 'ds:X509Data');
        $keyInfo->appendChild($x509Data);

        $appendCertificate = static function (string $pem) use ($document, $x509Data): void {
            $cleaned = preg_replace(
                '/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/',
                '',
                $pem
            ) ?? '';
            if ($cleaned === '') {
                return;
            }
            $element = $document->createElementNS(
                XadesEpesSigner::NS_DS,
                'ds:X509Certificate',
                $cleaned
            );
            $x509Data->appendChild($element);
        };

        $appendCertificate((string) $material['certificate']);
        $chain = (string) ($material['chain_pem'] ?? '');
        if ($chain !== '') {
            $matches = [];
            preg_match_all(
                '/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s',
                $chain,
                $matches
            );
            foreach ($matches[0] as $intermediate) {
                $appendCertificate($intermediate);
            }
        }

        return $keyInfo;
    }

    private function buildSignedProperties(
        DOMDocument $document,
        string $certificatePem,
        string $signedPropertiesId
    ): DOMElement {
        $signedProperties = $document->createElementNS(self::NS_XADES, 'xades:SignedProperties');
        $signedProperties->setAttribute('Id', $signedPropertiesId);

        $signedSignatureProperties = $document->createElementNS(self::NS_XADES, 'xades:SignedSignatureProperties');
        $signedProperties->appendChild($signedSignatureProperties);

        $signingTime = $document->createElementNS(
            self::NS_XADES,
            'xades:SigningTime',
            (new DateTimeImmutable('now'))->format(DATE_ATOM)
        );
        $signedSignatureProperties->appendChild($signingTime);

        // SigningCertificate
        $signingCertificate = $document->createElementNS(self::NS_XADES, 'xades:SigningCertificate');
        $signedSignatureProperties->appendChild($signingCertificate);

        $cert = $document->createElementNS(self::NS_XADES, 'xades:Cert');
        $signingCertificate->appendChild($cert);

        $certDigest = $document->createElementNS(self::NS_XADES, 'xades:CertDigest');
        $cert->appendChild($certDigest);

        $digestMethod = $document->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::DIGEST_METHOD);
        $certDigest->appendChild($digestMethod);

        $certDer = $this->certificatePemToDer($certificatePem);
        $digestValue = $document->createElementNS(
            self::NS_DS,
            'ds:DigestValue',
            base64_encode(hash('sha256', $certDer, true))
        );
        $certDigest->appendChild($digestValue);

        // IssuerSerial (best-effort: x509_parse provides the data we need).
        $parsed = @openssl_x509_parse($certificatePem, true);
        if (is_array($parsed)) {
            $issuerSerial = $document->createElementNS(self::NS_XADES, 'xades:IssuerSerial');
            $cert->appendChild($issuerSerial);

            $issuerName = $document->createElementNS(
                self::NS_DS,
                'ds:X509IssuerName',
                $this->formatDn($parsed['issuer'] ?? [])
            );
            $issuerSerial->appendChild($issuerName);

            $serial = isset($parsed['serialNumberHex']) && is_string($parsed['serialNumberHex'])
                ? strtoupper((string) $parsed['serialNumberHex'])
                : (string) ($parsed['serialNumber'] ?? '');
            $serialElement = $document->createElementNS(self::NS_DS, 'ds:X509SerialNumber', $serial);
            $issuerSerial->appendChild($serialElement);
        }

        // SignaturePolicyIdentifier (EPES)
        $signaturePolicyIdentifier = $document->createElementNS(self::NS_XADES, 'xades:SignaturePolicyIdentifier');
        $signedSignatureProperties->appendChild($signaturePolicyIdentifier);

        $signaturePolicyId = $document->createElementNS(self::NS_XADES, 'xades:SignaturePolicyId');
        $signaturePolicyIdentifier->appendChild($signaturePolicyId);

        $sigPolicyId = $document->createElementNS(self::NS_XADES, 'xades:SigPolicyId');
        $signaturePolicyId->appendChild($sigPolicyId);

        $identifier = $document->createElementNS(
            self::NS_XADES,
            'xades:Identifier',
            (string) ($this->signatureConfig['policy_url'] ?? '')
        );
        $sigPolicyId->appendChild($identifier);

        $description = $document->createElementNS(
            self::NS_XADES,
            'xades:Description',
            'DIAN Signature Policy ' . (string) ($this->signatureConfig['policy_oid'] ?? '')
        );
        $sigPolicyId->appendChild($description);

        $sigPolicyHash = $document->createElementNS(self::NS_XADES, 'xades:SigPolicyHash');
        $signaturePolicyId->appendChild($sigPolicyHash);

        $hashMethod = $document->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $hashMethod->setAttribute('Algorithm', self::DIGEST_METHOD);
        $sigPolicyHash->appendChild($hashMethod);

        $hashValue = $document->createElementNS(
            self::NS_DS,
            'ds:DigestValue',
            (string) ($this->signatureConfig['policy_hash_b64'] ?? '')
        );
        $sigPolicyHash->appendChild($hashValue);

        return $signedProperties;
    }

    private function buildDocumentReference(DOMDocument $document, DOMElement $root, string $refId): DOMElement
    {
        $reference = $document->createElementNS(self::NS_DS, 'ds:Reference');
        $reference->setAttribute('Id', $refId);
        $reference->setAttribute('URI', '');

        $transforms = $document->createElementNS(self::NS_DS, 'ds:Transforms');
        $reference->appendChild($transforms);

        $transformEnveloped = $document->createElementNS(self::NS_DS, 'ds:Transform');
        $transformEnveloped->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($transformEnveloped);

        $transformC14n = $document->createElementNS(self::NS_DS, 'ds:Transform');
        $transformC14n->setAttribute('Algorithm', self::CANONICALIZATION_METHOD);
        $transforms->appendChild($transformC14n);

        $digestMethod = $document->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::DIGEST_METHOD);
        $reference->appendChild($digestMethod);

        $envelope = $this->canonicalizeWithoutSignature($root);
        $digestValue = $document->createElementNS(
            self::NS_DS,
            'ds:DigestValue',
            base64_encode(hash('sha256', $envelope, true))
        );
        $reference->appendChild($digestValue);

        return $reference;
    }

    private function buildSignedPropertiesReference(
        DOMDocument $document,
        DOMElement $signedProperties,
        string $refId,
        string $signedPropertiesId
    ): DOMElement {
        $reference = $document->createElementNS(self::NS_DS, 'ds:Reference');
        $reference->setAttribute('Id', $refId);
        $reference->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
        $reference->setAttribute('URI', '#' . $signedPropertiesId);

        $transforms = $document->createElementNS(self::NS_DS, 'ds:Transforms');
        $reference->appendChild($transforms);

        $transformC14n = $document->createElementNS(self::NS_DS, 'ds:Transform');
        $transformC14n->setAttribute('Algorithm', self::CANONICALIZATION_METHOD);
        $transforms->appendChild($transformC14n);

        $digestMethod = $document->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::DIGEST_METHOD);
        $reference->appendChild($digestMethod);

        $canonical = $signedProperties->C14N(true, false);
        $digestValue = $document->createElementNS(
            self::NS_DS,
            'ds:DigestValue',
            base64_encode(hash('sha256', (string) $canonical, true))
        );
        $reference->appendChild($digestValue);

        return $reference;
    }

    private function buildKeyInfoReference(
        DOMDocument $document,
        DOMElement $keyInfo,
        string $refId,
        string $keyInfoId
    ): DOMElement {
        $reference = $document->createElementNS(self::NS_DS, 'ds:Reference');
        $reference->setAttribute('Id', $refId);
        $reference->setAttribute('URI', '#' . $keyInfoId);

        $transforms = $document->createElementNS(self::NS_DS, 'ds:Transforms');
        $reference->appendChild($transforms);

        $transformC14n = $document->createElementNS(self::NS_DS, 'ds:Transform');
        $transformC14n->setAttribute('Algorithm', self::CANONICALIZATION_METHOD);
        $transforms->appendChild($transformC14n);

        $digestMethod = $document->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::DIGEST_METHOD);
        $reference->appendChild($digestMethod);

        $canonical = $keyInfo->C14N(true, false);
        $digestValue = $document->createElementNS(
            self::NS_DS,
            'ds:DigestValue',
            base64_encode(hash('sha256', (string) $canonical, true))
        );
        $reference->appendChild($digestValue);

        return $reference;
    }

    /**
     * Canonicalize the root element after temporarily detaching the
     * `ds:Signature` we just appended. This implements the
     * `xmldsig#enveloped-signature` transform.
     */
    private function canonicalizeWithoutSignature(DOMElement $root): string
    {
        $xpath = new DOMXPath($root->ownerDocument);
        $xpath->registerNamespace('ds', self::NS_DS);

        $signature = $xpath->query('.//ds:Signature', $root)->item(0);
        if ($signature instanceof DOMElement) {
            $parent = $signature->parentNode;
            $parent->removeChild($signature);
            try {
                $canonical = $root->C14N(true, false);
            } finally {
                $parent->appendChild($signature);
            }
        } else {
            $canonical = $root->C14N(true, false);
        }

        return is_string($canonical) ? $canonical : '';
    }

    private function loadDocument(string $xml): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            $document->preserveWhiteSpace = true;
            $document->formatOutput = false;
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $loaded) {
            throw new InvalidArgumentException('Unsigned XML is not well-formed XML.');
        }

        return $document;
    }

    /**
     * @param array{certificate:string, private_key:string, chain_pem?:string|null} $material
     */
    private function assertMaterial(array $material): void
    {
        if (empty($material['certificate'])) {
            throw new InvalidArgumentException('Certificate material is required.');
        }
        if (empty($material['private_key'])) {
            throw new InvalidArgumentException('Private key material is required.');
        }
    }

    private function assertCryptoExtensions(): void
    {
        if (! extension_loaded('openssl')) {
            throw new XadesEpesSigningUnavailableException(
                'PHP OpenSSL extension is required to produce DIAN signatures.'
            );
        }
        if (! extension_loaded('dom') || ! extension_loaded('libxml')) {
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
            $loaded = $dom->loadXML($unsignedXml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $loaded) {
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

    private function certificatePemToDer(string $pem): string
    {
        $body = preg_replace(
            '/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/',
            '',
            $pem
        ) ?? '';
        $der = base64_decode($body, true);
        if ($der === false) {
            throw new InvalidArgumentException('Certificate is not valid PEM.');
        }

        return $der;
    }

    private function wrapCertificatePem(string $body): string
    {
        return "-----BEGIN CERTIFICATE-----\n"
            . trim(chunk_split($body, 64, "\n"))
            . "\n-----END CERTIFICATE-----\n";
    }

    /**
     * @param array<string, string> $dn
     */
    private function formatDn(array $dn): string
    {
        $parts = [];
        foreach ($dn as $key => $value) {
            $parts[] = sprintf('%s=%s', strtoupper((string) $key), (string) $value);
        }

        return implode(', ', array_reverse($parts));
    }

    private function randomToken(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (\Throwable $e) {
            return substr(md5(uniqid('', true)), 0, 16);
        }
    }
}
