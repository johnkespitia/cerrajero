<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Services\ElectronicInvoicing\LegacyPt\LegacyPtBundleValidator;
use PHPUnit\Framework\TestCase;

class LegacyPtBundleValidatorTest extends TestCase
{
    public function test_consistent_when_cufe_matches_cbc_uuid_in_xml(): void
    {
        $cufe = '0123456789abcdef';
        $xml = $this->buildXmlWithUuid($cufe);

        $validator = new LegacyPtBundleValidator();
        $rows = $validator->validate([
            $this->document(['cufe_cude' => $cufe, 'xml_base64' => base64_encode($xml)]),
        ]);

        $this->assertSame(LegacyPtBundleValidator::RESULT_CONSISTENT, $rows[0]['status']);
        $this->assertNull($rows[0]['reason']);
    }

    public function test_inconsistent_when_cufe_does_not_match_xml(): void
    {
        $xml = $this->buildXmlWithUuid('expected-uuid');

        $validator = new LegacyPtBundleValidator();
        $rows = $validator->validate([
            $this->document(['cufe_cude' => 'something-else', 'xml_base64' => base64_encode($xml)]),
        ]);

        $this->assertSame(LegacyPtBundleValidator::RESULT_INCONSISTENT, $rows[0]['status']);
        $this->assertSame(LegacyPtBundleValidator::REASON_CUFE_MISMATCH, $rows[0]['reason']);
        $this->assertSame('expected-uuid', $rows[0]['details']['extracted_cufe']);
    }

    public function test_inconsistent_when_xml_is_unparseable(): void
    {
        $validator = new LegacyPtBundleValidator();
        $rows = $validator->validate([
            $this->document(['cufe_cude' => 'abc', 'xml_base64' => base64_encode('<not-xml')]),
        ]);

        $this->assertSame(LegacyPtBundleValidator::RESULT_INCONSISTENT, $rows[0]['status']);
        $this->assertSame(LegacyPtBundleValidator::REASON_INVALID_XML, $rows[0]['reason']);
    }

    public function test_inconsistent_when_required_fields_missing(): void
    {
        $validator = new LegacyPtBundleValidator();
        $rows = $validator->validate([
            ['legacy_pt_id' => 'X-1'],
        ]);

        $this->assertSame(LegacyPtBundleValidator::RESULT_INCONSISTENT, $rows[0]['status']);
        $this->assertSame(LegacyPtBundleValidator::REASON_MISSING_FIELDS, $rows[0]['reason']);
        $this->assertContains('document_type', $rows[0]['details']['missing_fields']);
        $this->assertContains('cufe_cude', $rows[0]['details']['missing_fields']);
    }

    public function test_inconsistent_when_document_type_unknown(): void
    {
        $xml = $this->buildXmlWithUuid('abc');
        $validator = new LegacyPtBundleValidator();
        $rows = $validator->validate([
            $this->document([
                'document_type' => 'unknown_type',
                'cufe_cude' => 'abc',
                'xml_base64' => base64_encode($xml),
            ]),
        ]);

        $this->assertSame(LegacyPtBundleValidator::RESULT_INCONSISTENT, $rows[0]['status']);
        $this->assertSame(LegacyPtBundleValidator::REASON_INVALID_DOCUMENT_TYPE, $rows[0]['reason']);
    }

    public function test_missing_when_neither_xml_nor_pdf_present(): void
    {
        $validator = new LegacyPtBundleValidator();
        $rows = $validator->validate([
            $this->document(['xml_base64' => '', 'pdf_path' => '']),
        ]);

        $this->assertSame(LegacyPtBundleValidator::RESULT_MISSING, $rows[0]['status']);
        $this->assertSame(LegacyPtBundleValidator::REASON_MISSING_ARTIFACT, $rows[0]['reason']);
    }

    public function test_inconsistent_when_only_pdf_available_no_xml(): void
    {
        $validator = new LegacyPtBundleValidator();
        $rows = $validator->validate([
            $this->document([
                'xml_base64' => '',
                'pdf_path' => '/storage/legacy/abc.pdf',
            ]),
        ]);

        $this->assertSame(LegacyPtBundleValidator::RESULT_INCONSISTENT, $rows[0]['status']);
        $this->assertSame(LegacyPtBundleValidator::REASON_MISSING_ARTIFACT, $rows[0]['reason']);
        $this->assertSame('/storage/legacy/abc.pdf', $rows[0]['details']['pdf_path']);
    }

    private function document(array $overrides = []): array
    {
        return array_merge([
            'legacy_pt_id' => 'PT-1',
            'document_type' => 'fev',
            'dian_number' => 'SETP990000001',
            'cufe_cude' => '0123456789abcdef',
            'issue_date' => '2024-12-31',
            'total' => '119000.00',
            'currency_code' => 'COP',
            'xml_base64' => base64_encode($this->buildXmlWithUuid('0123456789abcdef')),
            'pdf_path' => null,
        ], $overrides);
    }

    private function buildXmlWithUuid(string $uuid): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cbc:UUID>{$uuid}</cbc:UUID>
  <cbc:ID>SETP990000001</cbc:ID>
</Invoice>
XML;
    }
}
