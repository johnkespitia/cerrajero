<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\RadianEventCode;
use App\Models\ElectronicDocument;
use App\Services\ElectronicInvoicing\Radian\RadianEventBuilder;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class RadianEventBuilderTest extends TestCase
{
    public function test_builds_application_response_with_expected_response_code_and_parent_cufe(): void
    {
        $builder = new RadianEventBuilder();
        $document = new ElectronicDocument(['cufe_cude' => 'abc123', 'dian_number' => 'SETP990000001']);
        // Bypass mass-assignment for unsaved attributes during a unit test:
        $document->issue_date = Carbon::create(2026, 3, 26);

        $xml = $builder->build($document, RadianEventCode::RECEIPT_ACKNOWLEDGED, [
            'cude' => 'cude-001',
            'document_id' => '42',
            'actor_nit' => '900123456',
            'actor_name' => 'Campo Verde',
            'issue_date' => Carbon::create(2026, 4, 1, 12, 0, 0),
            'issue_time' => Carbon::create(2026, 4, 1, 12, 0, 0),
        ]);

        $this->assertNotEmpty($xml);
        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        $this->assertSame('030', $xpath->evaluate('string(//cac:Response/cbc:ResponseCode)'));
        $this->assertSame('abc123', $xpath->evaluate('string(//cac:DocumentReference/cbc:UUID)'));
        $this->assertSame('cude-001', $xpath->evaluate('string(/*/cbc:UUID)'));
        $this->assertSame('800197268', $xpath->evaluate('string(//cac:ReceiverParty//cbc:CompanyID)'));
        $this->assertSame('900123456', $xpath->evaluate('string(//cac:SenderParty//cbc:CompanyID)'));
    }

    public function test_builder_supports_every_valid_event_code(): void
    {
        $builder = new RadianEventBuilder();
        $document = new ElectronicDocument(['cufe_cude' => 'abc', 'dian_number' => 'X']);

        foreach (RadianEventCode::ALL as $code) {
            $xml = $builder->build($document, $code, ['cude' => 'cd', 'document_id' => '1']);
            $dom = new DOMDocument();
            $this->assertTrue($dom->loadXML($xml), "Generated XML for {$code} is invalid");
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
            $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
            $this->assertSame($code, $xpath->evaluate('string(//cac:Response/cbc:ResponseCode)'));
        }
    }

    public function test_builder_rejects_unknown_event_code(): void
    {
        $builder = new RadianEventBuilder();
        $this->expectException(\InvalidArgumentException::class);
        $builder->build(new ElectronicDocument(['cufe_cude' => 'a']), '099');
    }
}
