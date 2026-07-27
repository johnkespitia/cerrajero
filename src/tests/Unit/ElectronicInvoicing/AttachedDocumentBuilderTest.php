<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Infrastructure\ElectronicInvoicing\Ubl\AttachedDocumentBuilder;
use App\Infrastructure\ElectronicInvoicing\Ubl\Exceptions\IncompleteUblPayloadException;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class AttachedDocumentBuilderTest extends TestCase
{
    /** @var AttachedDocumentBuilder */
    private $builder;

    /** @var string */
    private $cufe;

    /** @var string */
    private $originalXml;

    /** @var string */
    private $applicationResponseXml;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new AttachedDocumentBuilder();
        $this->cufe = str_repeat('a', 96);
        $this->originalXml = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>';
        $this->applicationResponseXml = '<ApplicationResponse xmlns="urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2"/>';
    }

    public function test_root_is_attached_document(): void
    {
        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML($this->builder->build($this->validPayload())));
        $this->assertSame('AttachedDocument', $doc->documentElement->localName);
        $this->assertSame(
            'urn:oasis:names:specification:ubl:schema:xsd:AttachedDocument-2',
            $doc->documentElement->namespaceURI
        );
    }

    public function test_main_attachment_contains_base64_of_original(): void
    {
        $xp = $this->xpath($this->builder->build($this->validPayload()));
        $description = $xp
            ->query('/ad:AttachedDocument/cac:Attachment/cac:ExternalReference/cbc:Description')
            ->item(0);
        $this->assertNotNull($description);
        $this->assertSame(base64_encode($this->originalXml), $description->textContent);
    }

    public function test_application_response_is_embedded_when_provided(): void
    {
        $xp = $this->xpath($this->builder->build($this->validPayload()));
        $arDescription = $xp
            ->query('//cac:ParentDocumentLineReference/cac:DocumentReference/cac:Attachment/cac:ExternalReference/cbc:Description')
            ->item(0);
        $this->assertNotNull($arDescription);
        $this->assertSame(base64_encode($this->applicationResponseXml), $arDescription->textContent);
    }

    public function test_no_application_response_falls_back_to_status_code(): void
    {
        $payload = $this->validPayload();
        unset($payload['application_response_xml_base64']);

        $xp = $this->xpath($this->builder->build($payload));
        $this->assertSame(
            0,
            $xp
                ->query('//cac:ParentDocumentLineReference/cac:DocumentReference/cac:Attachment')
                ->length,
            'AttachedDocument must not fabricate a DIAN ApplicationResponse.'
        );
        $status = $xp
            ->query('//cac:ParentDocumentLineReference/cac:DocumentReference/cbc:DocumentStatusCode')
            ->item(0);
        $this->assertNotNull($status);
        $this->assertSame('pending_dian_response', $status->textContent);
    }

    public function test_invalid_base64_throws(): void
    {
        $payload = $this->validPayload();
        $payload['original_xml_base64'] = '@@@ not base64 @@@';

        $this->expectException(IncompleteUblPayloadException::class);
        $this->expectExceptionMessage('original_xml_base64');
        $this->builder->build($payload);
    }

    public function test_missing_parent_document_throws(): void
    {
        $payload = $this->validPayload();
        unset($payload['parent_document']['uuid']);

        $this->expectException(IncompleteUblPayloadException::class);
        $this->expectExceptionMessage('parent_document.uuid');
        $this->builder->build($payload);
    }

    private function validPayload(): array
    {
        return [
            'document' => [
                'id' => 'SETP990000001',
                'uuid' => $this->cufe,
                'issue_date' => '2026-03-26',
                'issue_time' => '10:30:45-05:00',
                'environment' => '2',
                'document_type_label' => 'Contenedor de Factura Electronica',
                'parent_document_id' => 'SETP990000001',
            ],
            'sender' => [
                'id' => '900000000',
                'id_type' => '31',
                'name' => 'CAMPO VERDE S.A.S.',
            ],
            'receiver' => [
                'id' => '800197268',
                'id_type' => '31',
                'name' => 'DIAN',
            ],
            'parent_document' => [
                'id' => 'SETP990000001',
                'uuid' => $this->cufe,
                'issue_date' => '2026-03-26',
                'document_type_label' => 'Factura Electronica de Venta',
            ],
            'original_xml_base64' => base64_encode($this->originalXml),
            'application_response_xml_base64' => base64_encode($this->applicationResponseXml),
        ];
    }

    private function xpath(string $xml): DOMXPath
    {
        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('ad', 'urn:oasis:names:specification:ubl:schema:xsd:AttachedDocument-2');
        $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xp->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        return $xp;
    }
}
