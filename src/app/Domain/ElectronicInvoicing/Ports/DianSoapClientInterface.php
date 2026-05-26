<?php

namespace App\Domain\ElectronicInvoicing\Ports;

/**
 * SOAP client to the DIAN webservice with WS-Security.
 *
 * Concrete impl: App\Infrastructure\ElectronicInvoicing\Soap\WsSecuritySoapClient.
 *
 * Implementations MUST handle:
 * - wsse:Security with wsu:Timestamp (Created + Expires, 5 min window).
 * - wsse:BinarySecurityToken with X509v3, EncodingType=Base64Binary.
 * - ds:Signature over Timestamp + Body with Exclusive XML c14n + RSA-SHA256.
 * - TLS 1.2+ with verify_peer=true.
 */
interface DianSoapClientInterface
{
    /**
     * Synchronous submission of a signed UBL (zipped, base64-encoded).
     *
     * @return array DIAN response (parsed): IsValid, StatusCode, ErrorMessages, XmlBytes, etc.
     */
    public function sendBillSync(string $fileName, string $zipBase64): array;

    public function sendBillAsync(string $fileName, string $zipBase64): array;

    public function sendTestSetAsync(string $fileName, string $zipBase64, string $testSetId): array;

    public function getStatus(string $trackId): array;

    public function getStatusZip(string $trackId): array;

    public function getNumberingRange(array $params): array;

    public function sendEventUpdateStatus(string $fileName, string $zipBase64): array;

    public function getXmlByDocumentKey(string $cufe): array;
}
