<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Models\ElectronicDocument;
use App\Services\ElectronicInvoicing\Dispatch\DianZipPackager;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

class DianZipPackagerTest extends TestCase
{
    public function test_package_builds_zip_with_signed_xml(): void
    {
        $packager = new DianZipPackager();
        $document = $this->makeDocument(DocumentType::FEV, '990000123', '900123456');

        $output = $packager->package($document, '<?xml version="1.0"?><Invoice/>');

        $this->assertSame('fv900123456-990000123.zip', $output['file_name']);
        $this->assertSame('fv900123456-990000123.xml', $output['xml_name']);
        $this->assertNotEmpty($output['zip_bytes']);
        $this->assertSame(base64_encode($output['zip_bytes']), $output['zip_base64']);
        $this->assertSame(hash('sha256', $output['zip_bytes']), $output['zip_sha256']);

        $tmp = tempnam(sys_get_temp_dir(), 'zip-asserts-');
        file_put_contents($tmp, $output['zip_bytes']);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        $this->assertSame(1, $zip->numFiles);
        $this->assertSame('fv900123456-990000123.xml', $zip->getNameIndex(0));
        $this->assertStringContainsString('<Invoice/>', (string) $zip->getFromIndex(0));
        $zip->close();
        @unlink($tmp);
    }

    public function test_prefix_changes_per_document_type(): void
    {
        $packager = new DianZipPackager();
        $cases = [
            DocumentType::FEV => 'fv',
            DocumentType::DEE_POS => 'dp',
            DocumentType::NC => 'nc',
            DocumentType::ND => 'nd',
        ];
        foreach ($cases as $type => $prefix) {
            $doc = $this->makeDocument($type, '1', '900');
            $out = $packager->package($doc, '<?xml/>');
            $this->assertStringStartsWith($prefix . '900-1', $out['file_name']);
        }
    }

    public function test_rejects_empty_signed_xml(): void
    {
        $this->expectException(RuntimeException::class);
        (new DianZipPackager())->package($this->makeDocument(DocumentType::FEV, '1', '900'), '');
    }

    private function makeDocument(string $documentType, string $dianNumber, string $nit): ElectronicDocument
    {
        $document = new ElectronicDocument();
        $document->document_type = $documentType;
        $document->dian_number = $dianNumber;
        $document->setRelation('company', (object) ['nit' => $nit]);

        return $document;
    }
}
