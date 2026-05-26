<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap;

use App\Infrastructure\ElectronicInvoicing\Soap\Exceptions\DianSoapSigningUnavailableException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * Builds SOAP 1.1 envelopes with the DIAN WS-Security header.
 *
 * Two modes:
 *
 *  1. Unsigned (SigningMaterial is null):
 *     - Emits soapenv:Envelope, soapenv:Header/wsse:Security, wsu:Timestamp
 *       and soapenv:Body, all wired with wsu:Id attributes.
 *     - Skips wsse:BinarySecurityToken and ds:Signature.
 *     - This shape is what tests and dry-runs use.
 *
 *  2. Signed (SigningMaterial provided):
 *     - Adds wsse:BinarySecurityToken (Base64 of the X.509v3 certificate).
 *     - Computes Exclusive XML c14n + SHA-256 digests over Timestamp + Body.
 *     - Builds ds:SignedInfo with CanonicalizationMethod = exc-c14n and
 *       SignatureMethod = rsa-sha256.
 *     - Signs the canonicalised SignedInfo with RSA-SHA256.
 *     - Embeds ds:SignatureValue and ds:KeyInfo / wsse:SecurityTokenReference.
 *
 * The signed path is real, deterministic and verifiable. It uses ext-dom
 * (DOMNode::C14N) and ext-openssl, both shipped with the Laravel image. No
 * external xmlseclibs dependency is required for SOAP WS-Security; XAdES-EPES
 * for UBL still depends on the next hardening slice.
 */
final class WsSecurityEnvelopeBuilder
{
    public const DEFAULT_WINDOW_SECONDS = 300;

    /** @var DateTimeZone */
    private $utc;

    /** @var int */
    private $idCounter = 0;

    public function __construct()
    {
        $this->utc = new DateTimeZone('UTC');
    }

    /**
     * Build a SOAP envelope around the given operation body.
     *
     * @param DOMElement       $operationBody   Already-created element under WCF_DIAN namespace.
     * @param SigningMaterial  $material        null = unsigned envelope (test / dry-run).
     * @param DateTimeImmutable|null $createdAt Defaults to now (UTC).
     * @param int              $windowSeconds   Timestamp window (DIAN expects 5 min = 300s).
     */
    public function build(
        DOMElement $operationBody,
        ?SigningMaterial $material = null,
        ?DateTimeImmutable $createdAt = null,
        int $windowSeconds = self::DEFAULT_WINDOW_SECONDS
    ): string {
        if ($windowSeconds <= 0) {
            throw new \InvalidArgumentException('Timestamp window must be positive.');
        }
        if ($createdAt === null) {
            $createdAt = new DateTimeImmutable('now', $this->utc);
        } else {
            $createdAt = $createdAt->setTimezone($this->utc);
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        $envelope = $doc->createElementNS(SoapNamespaces::SOAP_1_1, 'soapenv:Envelope');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wcf', SoapNamespaces::WCF_DIAN);
        $doc->appendChild($envelope);

        $header = $doc->createElementNS(SoapNamespaces::SOAP_1_1, 'soapenv:Header');
        $envelope->appendChild($header);

        $security = $doc->createElementNS(SoapNamespaces::WSSE, 'wsse:Security');
        $security->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wsu', SoapNamespaces::WSU);
        $security->setAttributeNS(SoapNamespaces::SOAP_1_1, 'soapenv:mustUnderstand', '1');
        $header->appendChild($security);

        $tokenId = $this->nextId('X509-');
        $timestampId = $this->nextId('TS-');
        $bodyId = $this->nextId('BODY-');
        $signatureId = $this->nextId('SIG-');

        if ($material !== null) {
            $security->appendChild($this->buildBinarySecurityToken($doc, $material, $tokenId));
        }

        $timestamp = $this->buildTimestamp($doc, $createdAt, $windowSeconds, $timestampId);
        $body = $this->buildBody($doc, $operationBody, $bodyId);

        $security->appendChild($timestamp);
        $envelope->appendChild($body);

        if ($material !== null) {
            // Build the Signature skeleton (SignedInfo + Refs + empty
            // SignatureValue + KeyInfo) AFTER Timestamp and Body are already
            // attached to the envelope. We then insert the skeleton just
            // before Timestamp, canonicalise SignedInfo in its final tree
            // position and fill SignatureValue. This way the c14n context at
            // signing time matches the c14n context any verifier will see
            // after re-parsing the envelope.
            $signature = $this->buildSignatureSkeleton(
                $doc,
                $timestamp,
                $body,
                $tokenId,
                $signatureId
            );
            $security->insertBefore($signature, $timestamp);

            $this->fillSignatureValue($signature, $material);
        }

        $xml = $doc->saveXML();
        if ($xml === false) {
            throw new RuntimeException('Could not serialise SOAP envelope.');
        }
        return $xml;
    }

    private function buildBinarySecurityToken(DOMDocument $doc, SigningMaterial $material, string $id): DOMElement
    {
        $token = $doc->createElementNS(SoapNamespaces::WSSE, 'wsse:BinarySecurityToken');
        $token->setAttribute('EncodingType', SoapNamespaces::X509_ENCODING_BASE64);
        $token->setAttribute('ValueType', SoapNamespaces::X509_VALUE_TYPE);
        $token->setAttributeNS(SoapNamespaces::WSU, 'wsu:Id', $id);
        $token->appendChild($doc->createTextNode($material->certificateBase64()));
        return $token;
    }

    private function buildTimestamp(DOMDocument $doc, DateTimeImmutable $createdAt, int $windowSeconds, string $id): DOMElement
    {
        $expires = $createdAt->modify('+' . $windowSeconds . ' seconds');

        $ts = $doc->createElementNS(SoapNamespaces::WSU, 'wsu:Timestamp');
        $ts->setAttributeNS(SoapNamespaces::WSU, 'wsu:Id', $id);

        $created = $doc->createElementNS(SoapNamespaces::WSU, 'wsu:Created');
        $created->appendChild($doc->createTextNode($this->formatUtc($createdAt)));
        $ts->appendChild($created);

        $exp = $doc->createElementNS(SoapNamespaces::WSU, 'wsu:Expires');
        $exp->appendChild($doc->createTextNode($this->formatUtc($expires)));
        $ts->appendChild($exp);

        return $ts;
    }

    private function buildBody(DOMDocument $doc, DOMElement $operationBody, string $id): DOMElement
    {
        $body = $doc->createElementNS(SoapNamespaces::SOAP_1_1, 'soapenv:Body');
        $body->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wsu', SoapNamespaces::WSU);
        $body->setAttributeNS(SoapNamespaces::WSU, 'wsu:Id', $id);

        $imported = $doc->importNode($operationBody, true);
        $body->appendChild($imported);

        return $body;
    }

    private function buildSignatureSkeleton(
        DOMDocument $doc,
        DOMElement $timestamp,
        DOMElement $body,
        string $tokenId,
        string $signatureId
    ): DOMElement {
        $tsCanonical = $timestamp->C14N(true, false);
        $bodyCanonical = $body->C14N(true, false);
        if ($tsCanonical === false || $bodyCanonical === false) {
            throw new DianSoapSigningUnavailableException(
                'Exclusive XML Canonicalization is unavailable on this PHP build.'
            );
        }

        $tsDigest = base64_encode(hash('sha256', $tsCanonical, true));
        $bodyDigest = base64_encode(hash('sha256', $bodyCanonical, true));

        $signature = $doc->createElementNS(SoapNamespaces::DS, 'ds:Signature');
        $signature->setAttribute('Id', $signatureId);

        $signedInfo = $doc->createElementNS(SoapNamespaces::DS, 'ds:SignedInfo');

        $c14nMethod = $doc->createElementNS(SoapNamespaces::DS, 'ds:CanonicalizationMethod');
        $c14nMethod->setAttribute('Algorithm', SoapNamespaces::EXC_C14N);
        $signedInfo->appendChild($c14nMethod);

        $sigMethod = $doc->createElementNS(SoapNamespaces::DS, 'ds:SignatureMethod');
        $sigMethod->setAttribute('Algorithm', SoapNamespaces::SIG_RSA_SHA256);
        $signedInfo->appendChild($sigMethod);

        $signedInfo->appendChild(
            $this->buildReference(
                $doc,
                $timestamp->getAttributeNS(SoapNamespaces::WSU, 'Id'),
                $tsDigest
            )
        );
        $signedInfo->appendChild(
            $this->buildReference(
                $doc,
                $body->getAttributeNS(SoapNamespaces::WSU, 'Id'),
                $bodyDigest
            )
        );

        $signature->appendChild($signedInfo);

        $signatureValue = $doc->createElementNS(SoapNamespaces::DS, 'ds:SignatureValue');
        $signature->appendChild($signatureValue);

        $keyInfo = $doc->createElementNS(SoapNamespaces::DS, 'ds:KeyInfo');
        $str = $doc->createElementNS(SoapNamespaces::WSSE, 'wsse:SecurityTokenReference');
        $ref = $doc->createElementNS(SoapNamespaces::WSSE, 'wsse:Reference');
        $ref->setAttribute('URI', '#' . $tokenId);
        $ref->setAttribute('ValueType', SoapNamespaces::X509_VALUE_TYPE);
        $str->appendChild($ref);
        $keyInfo->appendChild($str);
        $signature->appendChild($keyInfo);

        return $signature;
    }

    private function fillSignatureValue(DOMElement $signature, SigningMaterial $material): void
    {
        $signedInfoList = $signature->getElementsByTagNameNS(SoapNamespaces::DS, 'SignedInfo');
        if ($signedInfoList->length === 0) {
            throw new DianSoapSigningUnavailableException('Signature is missing SignedInfo.');
        }
        $signedInfo = $signedInfoList->item(0);

        $canonical = $signedInfo->C14N(true, false);
        if ($canonical === false) {
            throw new DianSoapSigningUnavailableException(
                'Exclusive XML Canonicalization of SignedInfo failed.'
            );
        }

        $privateKey = openssl_pkey_get_private($material->privateKeyPem());
        if ($privateKey === false) {
            throw new DianSoapSigningUnavailableException(
                'Could not parse the WS-Security private key.'
            );
        }

        $rawSig = '';
        $ok = openssl_sign($canonical, $rawSig, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$ok || $rawSig === '') {
            throw new DianSoapSigningUnavailableException('RSA-SHA256 signature failed.');
        }

        $sigValueList = $signature->getElementsByTagNameNS(SoapNamespaces::DS, 'SignatureValue');
        if ($sigValueList->length === 0) {
            throw new DianSoapSigningUnavailableException('Signature is missing SignatureValue.');
        }
        $sigValueEl = $sigValueList->item(0);
        $sigValueEl->appendChild($signature->ownerDocument->createTextNode(base64_encode($rawSig)));
    }

    private function buildReference(DOMDocument $doc, string $uri, string $digestValue): DOMElement
    {
        $ref = $doc->createElementNS(SoapNamespaces::DS, 'ds:Reference');
        $ref->setAttribute('URI', '#' . $uri);

        $transforms = $doc->createElementNS(SoapNamespaces::DS, 'ds:Transforms');
        $transform = $doc->createElementNS(SoapNamespaces::DS, 'ds:Transform');
        $transform->setAttribute('Algorithm', SoapNamespaces::EXC_C14N);
        $transforms->appendChild($transform);
        $ref->appendChild($transforms);

        $digestMethod = $doc->createElementNS(SoapNamespaces::DS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', SoapNamespaces::DIGEST_SHA256);
        $ref->appendChild($digestMethod);

        $digestValueEl = $doc->createElementNS(SoapNamespaces::DS, 'ds:DigestValue');
        $digestValueEl->appendChild($doc->createTextNode($digestValue));
        $ref->appendChild($digestValueEl);

        return $ref;
    }

    private function nextId(string $prefix): string
    {
        $this->idCounter++;
        return $prefix . $this->idCounter;
    }

    private function formatUtc(DateTimeInterface $dt): string
    {
        return $dt->format('Y-m-d\TH:i:s\Z');
    }
}
