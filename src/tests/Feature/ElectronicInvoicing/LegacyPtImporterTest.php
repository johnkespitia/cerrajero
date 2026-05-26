<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\ElectronicDocument;
use App\Models\LegacyPtImport;
use App\Services\ElectronicInvoicing\Exceptions\LegacyPtImportException;
use App\Services\ElectronicInvoicing\LegacyPt\InMemoryLegacyPtArtifactStorage;
use App\Services\ElectronicInvoicing\LegacyPt\LegacyPtBundleValidator;
use App\Services\ElectronicInvoicing\LegacyPt\LegacyPtImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyPtImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_consistent_documents_as_legacy_imported(): void
    {
        $company = $this->seedCompany();
        $importer = $this->buildImporter();

        $cufe = '0123456789abcdef';
        $payload = [
            'company_id' => $company->id,
            'source_pt_name' => 'Soenac',
            'documents' => [[
                'legacy_pt_id' => 'PT-1',
                'document_type' => 'fev',
                'dian_number' => 'SETP990000001',
                'cufe_cude' => $cufe,
                'issue_date' => '2024-12-31',
                'total' => '119000.00',
                'currency_code' => 'COP',
                'xml_base64' => base64_encode($this->buildXmlWithUuid($cufe)),
            ]],
        ];

        $import = $importer->import($payload);

        $this->assertSame('completed', $import->status);
        $this->assertSame(1, $import->total_documents);
        $this->assertSame(1, $import->consistent_count);
        $this->assertSame(0, $import->inconsistent_count);
        $this->assertSame(0, $import->missing_count);
        $this->assertNotNull($import->finished_at);

        $document = ElectronicDocument::query()->where('legacy_pt_id', 'PT-1')->firstOrFail();
        $this->assertSame(DocumentStatus::LEGACY_IMPORTED, $document->status);
        $this->assertSame('legacy_pt_import', $document->source_type);
        $this->assertSame($cufe, $document->cufe_cude);
        $this->assertNotNull($document->xml_signed_path);

        $eventTypes = $document->events->pluck('event_type')->all();
        $this->assertContains('legacy_imported', $eventTypes);
    }

    public function test_imports_inconsistent_cufe_as_legacy_import_inconsistent(): void
    {
        $company = $this->seedCompany();
        $importer = $this->buildImporter();

        $payload = [
            'company_id' => $company->id,
            'source_pt_name' => 'Soenac',
            'documents' => [[
                'legacy_pt_id' => 'PT-2',
                'document_type' => 'fev',
                'dian_number' => 'SETP990000002',
                'cufe_cude' => 'declared-cufe',
                'issue_date' => '2024-12-31',
                'total' => '119000.00',
                'xml_base64' => base64_encode($this->buildXmlWithUuid('different-cufe')),
            ]],
        ];

        $import = $importer->import($payload);

        $this->assertSame(0, $import->consistent_count);
        $this->assertSame(1, $import->inconsistent_count);

        $document = ElectronicDocument::query()->where('legacy_pt_id', 'PT-2')->firstOrFail();
        $this->assertSame(DocumentStatus::LEGACY_IMPORT_INCONSISTENT, $document->status);

        $reportFirstRow = $import->report[0];
        $this->assertSame('inconsistent', $reportFirstRow['status']);
        $this->assertSame(LegacyPtBundleValidator::REASON_CUFE_MISMATCH, $reportFirstRow['reason']);
    }

    public function test_missing_artifact_is_counted_as_missing_without_persisting_document(): void
    {
        $company = $this->seedCompany();
        $importer = $this->buildImporter();

        $payload = [
            'company_id' => $company->id,
            'source_pt_name' => 'Soenac',
            'documents' => [[
                'legacy_pt_id' => 'PT-3',
                'document_type' => 'fev',
                'dian_number' => 'SETP990000003',
                'cufe_cude' => 'whatever',
                'issue_date' => '2024-12-31',
                'total' => '0.00',
                'xml_base64' => '',
                'pdf_path' => '',
            ]],
        ];

        $import = $importer->import($payload);

        $this->assertSame(0, $import->consistent_count);
        $this->assertSame(0, $import->inconsistent_count);
        $this->assertSame(1, $import->missing_count);

        $this->assertSame(0, ElectronicDocument::query()->where('legacy_pt_id', 'PT-3')->count());
    }

    public function test_throws_when_bundle_is_empty(): void
    {
        $company = $this->seedCompany();
        $importer = $this->buildImporter();

        $this->expectException(LegacyPtImportException::class);
        $importer->import([
            'company_id' => $company->id,
            'source_pt_name' => 'Soenac',
            'documents' => [],
        ]);
    }

    public function test_throws_when_company_not_found(): void
    {
        $importer = $this->buildImporter();

        try {
            $importer->import([
                'company_id' => 99999,
                'source_pt_name' => 'Soenac',
                'documents' => [[
                    'legacy_pt_id' => 'X',
                    'document_type' => 'fev',
                    'dian_number' => 'SETP990000001',
                    'cufe_cude' => 'abc',
                    'issue_date' => '2024-12-31',
                    'total' => '0',
                ]],
            ]);
            $this->fail('Expected LegacyPtImportException not thrown');
        } catch (LegacyPtImportException $e) {
            $this->assertSame(LegacyPtImportException::CODE_COMPANY_NOT_FOUND, $e->errorCode());
        }
    }

    public function test_reuses_sentinel_resolution_across_imports(): void
    {
        $company = $this->seedCompany();
        $importer = $this->buildImporter();

        $cufe = '0123456789abcdef';
        $payload = [
            'company_id' => $company->id,
            'source_pt_name' => 'Soenac',
            'documents' => [[
                'legacy_pt_id' => 'PT-A',
                'document_type' => 'fev',
                'dian_number' => 'SETP990000001',
                'cufe_cude' => $cufe,
                'issue_date' => '2024-12-31',
                'total' => '100.00',
                'xml_base64' => base64_encode($this->buildXmlWithUuid($cufe)),
            ]],
        ];

        $importer->import($payload);
        $payload['documents'][0]['legacy_pt_id'] = 'PT-B';
        $payload['documents'][0]['dian_number'] = 'SETP990000002';
        $importer->import($payload);

        $resolutions = \App\Models\DianResolution::query()
            ->where('company_id', $company->id)
            ->where('prefix', LegacyPtImporter::SENTINEL_PREFIX)
            ->count();

        $this->assertSame(1, $resolutions);
    }

    private function buildImporter(): LegacyPtImporter
    {
        return new LegacyPtImporter(
            new LegacyPtBundleValidator(),
            new InMemoryLegacyPtArtifactStorage()
        );
    }

    private function seedCompany(): CompanyFiscalProfile
    {
        return CompanyFiscalProfile::create([
            'legal_name' => 'Campo Verde S.A.S.',
            'trade_name' => 'Campo Verde',
            'nit' => '900123456',
            'dv' => 1,
            'tax_regime_code' => '48',
            'tax_responsibilities' => ['O-13'],
            'address_line' => 'Km 5',
            'city_code_dian' => '63190',
            'country_code' => 'co',
            'email' => 'fiscal@cv.local',
            'environment' => FiscalEnvironment::HABILITACION,
            'active' => true,
        ]);
    }

    private function buildXmlWithUuid(string $uuid): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cbc:UUID>{$uuid}</cbc:UUID>
</Invoice>
XML;
    }
}
