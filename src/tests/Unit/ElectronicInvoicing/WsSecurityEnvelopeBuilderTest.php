<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Infrastructure\ElectronicInvoicing\Soap\Actions\SendBillSyncAction;
use App\Infrastructure\ElectronicInvoicing\Soap\SigningMaterial;
use App\Infrastructure\ElectronicInvoicing\Soap\SoapNamespaces;
use App\Infrastructure\ElectronicInvoicing\Soap\WsSecurityEnvelopeBuilder;
use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class WsSecurityEnvelopeBuilderTest extends TestCase
{
    /** @var WsSecurityEnvelopeBuilder */
    private $builder;

    /** @var SendBillSyncAction */
    private $action;

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('ext-openssl is required to exercise WS-Security signing.');
        }
        if (!extension_loaded('dom')) {
            $this->markTestSkipped('ext-dom is required for SOAP envelope building.');
        }
        $this->builder = new WsSecurityEnvelopeBuilder();
        $this->action = new SendBillSyncAction();
    }

    public function test_unsigned_envelope_has_expected_skeleton(): void
    {
        $envelope = $this->builder->build(
            $this->operationElement(),
            null,
            new DateTimeImmutable('2026-03-26T15:00:00', new DateTimeZone('UTC'))
        );

        $xp = $this->xpath($envelope);
        $this->assertSame(1, $xp->query('/soap:Envelope/soap:Header/wsse:Security')->length);
        $this->assertSame(1, $xp->query('//wsu:Timestamp/wsu:Created')->length);
        $this->assertSame(1, $xp->query('//wsu:Timestamp/wsu:Expires')->length);
        $this->assertSame(0, $xp->query('//wsse:BinarySecurityToken')->length);
        $this->assertSame(0, $xp->query('//ds:Signature')->length);
    }

    public function test_timestamp_uses_utc_iso8601_and_300s_window(): void
    {
        $created = new DateTimeImmutable('2026-03-26T15:00:00', new DateTimeZone('UTC'));
        $envelope = $this->builder->build(
            $this->operationElement(),
            null,
            $created,
            300
        );

        $xp = $this->xpath($envelope);
        $this->assertSame(
            '2026-03-26T15:00:00Z',
            $xp->query('//wsu:Timestamp/wsu:Created')->item(0)->textContent
        );
        $this->assertSame(
            '2026-03-26T15:05:00Z',
            $xp->query('//wsu:Timestamp/wsu:Expires')->item(0)->textContent
        );
    }

    public function test_body_carries_operation_under_wcf_dian_namespace(): void
    {
        $envelope = $this->builder->build($this->operationElement());

        $xp = $this->xpath($envelope);
        $this->assertSame(1, $xp->query('/soap:Envelope/soap:Body/wcf:SendBillSync')->length);
        $this->assertSame('SETP1.zip', $xp->query('//wcf:SendBillSync/wcf:fileName')->item(0)->textContent);
    }

    public function test_body_has_wsu_id_for_signature_reference(): void
    {
        $envelope = $this->builder->build($this->operationElement());
        $xp = $this->xpath($envelope);
        $body = $xp->query('/soap:Envelope/soap:Body')->item(0);
        $this->assertNotSame('', $body->getAttributeNS(SoapNamespaces::WSU, 'Id'));
    }

    public function test_signed_envelope_emits_binary_security_token(): void
    {
        $material = $this->makeSigningMaterial();
        $envelope = $this->builder->build($this->operationElement(), $material);

        $xp = $this->xpath($envelope);
        $token = $xp->query('//wsse:BinarySecurityToken')->item(0);
        $this->assertNotNull($token);
        $this->assertSame(SoapNamespaces::X509_VALUE_TYPE, $token->getAttribute('ValueType'));
        $this->assertSame(SoapNamespaces::X509_ENCODING_BASE64, $token->getAttribute('EncodingType'));
        $this->assertSame($material->certificateBase64(), trim($token->textContent));
    }

    public function test_signed_envelope_emits_signature_with_two_references(): void
    {
        $material = $this->makeSigningMaterial();
        $envelope = $this->builder->build($this->operationElement(), $material);

        $xp = $this->xpath($envelope);
        $signature = $xp->query('//ds:Signature')->item(0);
        $this->assertNotNull($signature);
        $this->assertSame(2, $xp->query('.//ds:Reference', $signature)->length);
        $this->assertSame(
            SoapNamespaces::SIG_RSA_SHA256,
            $xp->query('.//ds:SignatureMethod', $signature)->item(0)->getAttribute('Algorithm')
        );
        $this->assertSame(
            SoapNamespaces::EXC_C14N,
            $xp->query('.//ds:CanonicalizationMethod', $signature)->item(0)->getAttribute('Algorithm')
        );
    }

    public function test_signature_references_point_to_timestamp_and_body_ids(): void
    {
        $material = $this->makeSigningMaterial();
        $envelope = $this->builder->build($this->operationElement(), $material);

        $xp = $this->xpath($envelope);
        $tsId = $xp->query('//wsu:Timestamp')->item(0)->getAttributeNS(SoapNamespaces::WSU, 'Id');
        $bodyId = $xp->query('/soap:Envelope/soap:Body')->item(0)->getAttributeNS(SoapNamespaces::WSU, 'Id');
        $uris = [];
        foreach ($xp->query('//ds:Signature/ds:SignedInfo/ds:Reference') as $ref) {
            $uris[] = $ref->getAttribute('URI');
        }
        sort($uris);
        $expected = ['#' . $bodyId, '#' . $tsId];
        sort($expected);
        $this->assertSame($expected, $uris);
    }

    public function test_signature_value_verifies_with_matching_public_key(): void
    {
        $keys = $this->generateKeyPair();
        $material = new SigningMaterial($keys['certificate'], $keys['private']);
        $envelope = $this->builder->build($this->operationElement(), $material);

        $xp = $this->xpath($envelope);
        $signedInfo = $xp->query('//ds:Signature/ds:SignedInfo')->item(0);
        $signatureValue = trim(
            $xp->query('//ds:Signature/ds:SignatureValue')->item(0)->textContent
        );

        $canonical = $signedInfo->C14N(true, false);
        $this->assertNotFalse($canonical);

        $publicKey = openssl_pkey_get_public($keys['publicPem']);
        $this->assertNotFalse($publicKey);

        $rawSig = base64_decode($signatureValue, true);
        $this->assertNotFalse($rawSig);

        $verified = openssl_verify($canonical, $rawSig, $publicKey, OPENSSL_ALGO_SHA256);
        $this->assertSame(1, $verified, 'SignedInfo signature must verify against matching public key.');
    }

    public function test_security_token_reference_points_to_binary_security_token(): void
    {
        $material = $this->makeSigningMaterial();
        $envelope = $this->builder->build($this->operationElement(), $material);

        $xp = $this->xpath($envelope);
        $tokenId = $xp->query('//wsse:BinarySecurityToken')->item(0)->getAttributeNS(SoapNamespaces::WSU, 'Id');
        $refUri = $xp->query('//ds:KeyInfo/wsse:SecurityTokenReference/wsse:Reference')
            ->item(0)
            ->getAttribute('URI');
        $this->assertSame('#' . $tokenId, $refUri);
    }

    public function test_signature_appears_before_timestamp_in_security_header(): void
    {
        $material = $this->makeSigningMaterial();
        $envelope = $this->builder->build($this->operationElement(), $material);

        $xp = $this->xpath($envelope);
        $children = [];
        foreach ($xp->query('//wsse:Security')->item(0)->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $children[] = $child->localName;
            }
        }
        $this->assertSame(['BinarySecurityToken', 'Signature', 'Timestamp'], $children);
    }

    public function test_window_seconds_must_be_positive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->builder->build($this->operationElement(), null, null, 0);
    }

    private function operationElement(): \DOMElement
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $payload = [
            'fileName' => 'SETP1.zip',
            'contentFile' => base64_encode('<Invoice/>'),
        ];
        return $this->action->buildOperationElement($doc, $payload);
    }

    private function makeSigningMaterial(): SigningMaterial
    {
        $keys = $this->generateKeyPair();
        return new SigningMaterial($keys['certificate'], $keys['private']);
    }

    /**
     * @return array{certificate:string, private:string, publicPem:string}
     */
    private function generateKeyPair(): array
    {
        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        ]);
        $this->assertNotFalse($keyPair);

        $csr = openssl_csr_new(['commonName' => 'TEST'], $keyPair, ['digest_alg' => 'sha256']);
        $this->assertNotFalse($csr);
        $cert = openssl_csr_sign($csr, null, $keyPair, 1, ['digest_alg' => 'sha256']);
        $this->assertNotFalse($cert);

        $certPem = '';
        openssl_x509_export($cert, $certPem);
        $privatePem = '';
        openssl_pkey_export($keyPair, $privatePem);
        $details = openssl_pkey_get_details($keyPair);

        return [
            'certificate' => $certPem,
            'private' => $privatePem,
            'publicPem' => $details['key'],
        ];
    }

    private function xpath(string $xml): DOMXPath
    {
        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('soap', SoapNamespaces::SOAP_1_1);
        $xp->registerNamespace('wsse', SoapNamespaces::WSSE);
        $xp->registerNamespace('wsu', SoapNamespaces::WSU);
        $xp->registerNamespace('ds', SoapNamespaces::DS);
        $xp->registerNamespace('wcf', SoapNamespaces::WCF_DIAN);
        return $xp;
    }
}
