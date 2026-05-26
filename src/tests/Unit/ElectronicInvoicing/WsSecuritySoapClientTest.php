<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Infrastructure\ElectronicInvoicing\Soap\Exceptions\DianSoapResponseException;
use App\Infrastructure\ElectronicInvoicing\Soap\Exceptions\DianSoapSigningUnavailableException;
use App\Infrastructure\ElectronicInvoicing\Soap\Exceptions\InvalidSoapPayloadException;
use App\Infrastructure\ElectronicInvoicing\Soap\SigningMaterial;
use App\Infrastructure\ElectronicInvoicing\Soap\SoapNamespaces;
use App\Infrastructure\ElectronicInvoicing\Soap\Transport\RecordingTransport;
use App\Infrastructure\ElectronicInvoicing\Soap\WsSecurityEnvelopeBuilder;
use App\Infrastructure\ElectronicInvoicing\Soap\WsSecuritySoapClient;
use PHPUnit\Framework\TestCase;

class WsSecuritySoapClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('ext-openssl is required.');
        }
    }

    public function test_dry_run_mode_returns_envelope_without_calling_transport(): void
    {
        $transport = new RecordingTransport();
        $client = new WsSecuritySoapClient(
            new WsSecurityEnvelopeBuilder(),
            $transport,
            ['endpoint' => 'https://test.invalid/svc', 'dry_run' => true]
        );

        $result = $client->sendBillSync('SETP1.zip', base64_encode('<Invoice/>'));

        $this->assertTrue($result['dry_run']);
        $this->assertSame(
            'http://wcf.dian.colombia/IWcfDianCustomerServices/SendBillSync',
            $result['soap_action']
        );
        $this->assertStringContainsString('<wcf:SendBillSync', $result['envelope']);
        $this->assertSame([], $transport->requests(), 'Transport must NOT be invoked in dry-run.');
    }

    public function test_signing_unavailable_when_no_material_and_not_dry_run(): void
    {
        $transport = new RecordingTransport();
        $client = new WsSecuritySoapClient(
            new WsSecurityEnvelopeBuilder(),
            $transport,
            ['endpoint' => 'https://test.invalid/svc']
        );

        $this->expectException(DianSoapSigningUnavailableException::class);
        $this->expectExceptionMessage('WS-Security signing material');
        $client->getStatus('TRACK-1');
    }

    public function test_real_dispatch_signs_envelope_and_passes_soap_action_header(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueueResponse(200, $this->fakeSendBillSyncResponse(), [
            'content-type' => 'text/xml; charset=utf-8',
        ]);

        $material = $this->makeSigningMaterial();

        $client = new WsSecuritySoapClient(
            new WsSecurityEnvelopeBuilder(),
            $transport,
            ['endpoint' => 'https://hab.example/svc'],
            $material
        );

        $b64 = base64_encode('<Invoice/>');
        $result = $client->sendBillSync('SETP1.zip', $b64);

        $this->assertFalse($result['dry_run']);
        $this->assertSame(200, $result['http_status']);
        $this->assertSame('true', $result['result']['IsValid']);

        $request = $transport->lastRequest();
        $this->assertSame('https://hab.example/svc', $request['url']);
        $this->assertSame(
            '"http://wcf.dian.colombia/IWcfDianCustomerServices/SendBillSync"',
            $request['headers']['SOAPAction']
        );
        $this->assertStringContainsString('<wsse:BinarySecurityToken', $request['body']);
        $this->assertStringContainsString('<ds:Signature', $request['body']);
        $this->assertStringContainsString($b64, $request['body']);
    }

    public function test_non_2xx_response_raises_response_exception_with_status(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueueResponse(500, $this->fakeFault());

        $client = new WsSecuritySoapClient(
            new WsSecurityEnvelopeBuilder(),
            $transport,
            ['endpoint' => 'https://hab.example/svc'],
            $this->makeSigningMaterial()
        );

        try {
            $client->getStatus('TRACK-X');
            $this->fail('Expected DianSoapResponseException.');
        } catch (DianSoapResponseException $e) {
            $this->assertSame(500, $e->httpStatus());
            $this->assertSame('soap:Server', $e->faultCode());
        }
    }

    public function test_response_exception_message_never_includes_signing_material(): void
    {
        $transport = new RecordingTransport();
        $transport->enqueueResponse(503, $this->fakeFault());

        $material = $this->makeSigningMaterial();
        $client = new WsSecuritySoapClient(
            new WsSecurityEnvelopeBuilder(),
            $transport,
            ['endpoint' => 'https://hab.example/svc'],
            $material
        );

        try {
            $client->getStatus('TRACK-X');
        } catch (DianSoapResponseException $e) {
            $message = $e->getMessage();
            $this->assertStringNotContainsString('BEGIN CERTIFICATE', $message);
            $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $message);
            $this->assertStringNotContainsString('BEGIN RSA PRIVATE KEY', $message);
            $this->assertStringNotContainsString($material->certificateBase64(), $message);
        }
    }

    public function test_invalid_param_propagates_as_invalid_soap_payload_exception(): void
    {
        $transport = new RecordingTransport();
        $client = new WsSecuritySoapClient(
            new WsSecurityEnvelopeBuilder(),
            $transport,
            ['endpoint' => 'https://test.invalid/svc', 'dry_run' => true]
        );

        $this->expectException(InvalidSoapPayloadException::class);
        $this->expectExceptionMessage('trackId');
        $client->getStatus('');
    }

    public function test_get_xml_by_document_key_dispatches_with_cufe(): void
    {
        $transport = new RecordingTransport();
        $client = new WsSecuritySoapClient(
            new WsSecurityEnvelopeBuilder(),
            $transport,
            ['endpoint' => 'https://hab.example/svc', 'dry_run' => true]
        );

        $cufe = str_repeat('a', 96);
        $result = $client->getXmlByDocumentKey($cufe);

        $this->assertTrue($result['dry_run']);
        $this->assertStringContainsString('<wcf:GetXmlByDocumentKey', $result['envelope']);
        $this->assertStringContainsString('<wcf:trackId>' . $cufe . '</wcf:trackId>', $result['envelope']);
    }

    public function test_supported_operations_lists_all_eight_actions(): void
    {
        $client = new WsSecuritySoapClient(
            new WsSecurityEnvelopeBuilder(),
            new RecordingTransport(),
            ['endpoint' => 'https://hab.example/svc']
        );

        $this->assertSame(
            [
                'SendBillSync',
                'SendBillAsync',
                'SendTestSetAsync',
                'GetStatus',
                'GetStatusZip',
                'GetNumberingRange',
                'SendEventUpdateStatus',
                'GetXmlByDocumentKey',
            ],
            $client->supportedOperations()
        );
    }

    public function test_endpoint_must_be_configured(): void
    {
        $transport = new RecordingTransport();
        $client = new WsSecuritySoapClient(
            new WsSecurityEnvelopeBuilder(),
            $transport,
            [],
            $this->makeSigningMaterial()
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('config.endpoint');
        $client->sendBillSync('SETP1.zip', base64_encode('<Invoice/>'));
    }

    private function makeSigningMaterial(): SigningMaterial
    {
        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $csr = openssl_csr_new(['commonName' => 'TEST'], $keyPair, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $keyPair, 1, ['digest_alg' => 'sha256']);
        $certPem = '';
        $privatePem = '';
        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($keyPair, $privatePem);
        return new SigningMaterial($certPem, $privatePem);
    }

    private function fakeSendBillSyncResponse(): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<s:Envelope xmlns:s="' . SoapNamespaces::SOAP_1_1 . '">'
            . '<s:Body>'
            . '<SendBillSyncResponse xmlns="' . SoapNamespaces::WCF_DIAN . '">'
            . '<SendBillSyncResult>'
            . '<IsValid>true</IsValid>'
            . '<StatusCode>00</StatusCode>'
            . '<StatusDescription>Procesado correctamente</StatusDescription>'
            . '</SendBillSyncResult>'
            . '</SendBillSyncResponse>'
            . '</s:Body></s:Envelope>';
    }

    private function fakeFault(): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<s:Envelope xmlns:s="' . SoapNamespaces::SOAP_1_1 . '">'
            . '<s:Body><s:Fault>'
            . '<faultcode>soap:Server</faultcode>'
            . '<faultstring>internal error</faultstring>'
            . '</s:Fault></s:Body></s:Envelope>';
    }
}
