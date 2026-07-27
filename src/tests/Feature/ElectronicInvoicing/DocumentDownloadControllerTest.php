<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\ElectronicDocument;
use App\Models\ElectronicDocumentEvent;
use App\Models\User;
use App\Services\ElectronicInvoicing\LegacyPt\LegacyPtArtifactStorageInterface;
use App\Services\ElectronicInvoicing\Storage\UnsignedXmlStorageInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsElectronicInvoicingPermissions;
use Tests\TestCase;

class DocumentDownloadControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsElectronicInvoicingPermissions;

    public function test_downloads_unsigned_xml_when_storage_has_it(): void
    {
        $this->actingAsViewer();
        $doc = $this->seedDocument(['xml_unsigned_path' => 'memory://fiscal/1/unsigned/doc-1.xml']);
        $this->putUnsignedXml($doc->xml_unsigned_path, '<unsigned-xml/>');

        $response = $this->get(sprintf('/api/electronic-invoicing/documents/%d/xml-unsigned', $doc->id));

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/xml')
            ->assertHeader('Content-Disposition', sprintf('attachment; filename="%s-unsigned.xml"', $doc->dian_number));
        $this->assertSame('<unsigned-xml/>', $response->getContent());
    }

    public function test_downloads_signed_xml_from_legacy_storage(): void
    {
        $this->actingAsViewer();
        $doc = $this->seedDocument([
            'status' => DocumentStatus::LEGACY_IMPORTED,
            'source_type' => 'legacy_pt_import',
            'xml_signed_path' => 'memory://fiscal/1/legacy/PT-1.xml',
        ]);
        $this->putLegacyArtifact($doc->xml_signed_path, '<signed-xml/>');

        $response = $this->get(sprintf('/api/electronic-invoicing/documents/%d/xml-signed', $doc->id));

        $response->assertStatus(200);
        $this->assertSame('<signed-xml/>', $response->getContent());
    }

    public function test_returns_404_when_artifact_path_is_empty(): void
    {
        $this->actingAsViewer();
        $doc = $this->seedDocument(['xml_unsigned_path' => null]);

        $response = $this->get(sprintf('/api/electronic-invoicing/documents/%d/xml-unsigned', $doc->id));

        $response->assertStatus(404)
            ->assertJsonPath('electronic_document_error.code', 'artifact_not_available');
    }

    public function test_returns_404_when_artifact_bytes_not_in_storage(): void
    {
        $this->actingAsViewer();
        $doc = $this->seedDocument([
            'xml_unsigned_path' => 'memory://fiscal/1/unsigned/orphan.xml',
        ]);

        $response = $this->get(sprintf('/api/electronic-invoicing/documents/%d/xml-unsigned', $doc->id));

        $response->assertStatus(404)
            ->assertJsonPath('electronic_document_error.code', 'artifact_not_available');
    }

    public function test_returns_404_when_document_not_found(): void
    {
        $this->actingAsViewer();

        $response = $this->get('/api/electronic-invoicing/documents/9999/xml-unsigned');

        $response->assertStatus(404)
            ->assertJsonPath('electronic_document_error.code', 'document_not_found');
    }

    public function test_lists_events_chronologically(): void
    {
        $this->actingAsViewer();
        $doc = $this->seedDocument();
        ElectronicDocumentEvent::create([
            'electronic_document_id' => $doc->id,
            'event_type' => 'document.queued',
            'payload' => ['note' => 'queued'],
            'actor' => 'system:DocumentEmitter',
            'correlation_id' => 'corr-1',
            'occurred_at' => now()->subMinutes(2),
        ]);
        ElectronicDocumentEvent::create([
            'electronic_document_id' => $doc->id,
            'event_type' => 'document.ubl_built',
            'payload' => ['note' => 'ubl'],
            'actor' => 'system:DocumentEmitter',
            'correlation_id' => 'corr-1',
            'occurred_at' => now(),
        ]);

        $response = $this->getJson(sprintf('/api/electronic-invoicing/documents/%d/events', $doc->id));

        $response->assertStatus(200)
            ->assertJsonCount(2, 'events')
            ->assertJsonPath('events.0.event_type', 'document.queued')
            ->assertJsonPath('events.1.event_type', 'document.ubl_built')
            ->assertJsonPath('events.0.correlation_id', 'corr-1');
    }

    public function test_endpoints_require_list_permission(): void
    {
        $user = $this->makeUser('nobody@test.local');
        $this->grantElectronicInvoicingPermissions($user, []);
        $this->actingAs($user, 'sanctum');

        $doc = $this->seedDocument();
        $this->get(sprintf('/api/electronic-invoicing/documents/%d/xml-unsigned', $doc->id))
            ->assertStatus(401);
    }

    private function actingAsViewer(): void
    {
        $user = $this->makeUser('viewer@test.local');
        $this->grantElectronicInvoicingPermissions($user, ['electronic_invoicing.list']);
        $this->actingAs($user, 'sanctum');
    }

    private function makeUser(string $email): User
    {
        return User::create([
            'name' => 'Test',
            'email' => $email,
            'password' => bcrypt('test1234'),
        ]);
    }

    private function seedDocument(array $overrides = []): ElectronicDocument
    {
        $company = CompanyFiscalProfile::create([
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
        $resolution = DianResolution::create([
            'company_id' => $company->id,
            'environment' => FiscalEnvironment::HABILITACION,
            'document_type' => 'fev',
            'resolution_number' => 'R-1',
            'resolution_date' => now()->toDateString(),
            'prefix' => 'SETP',
            'from_number' => 990000001,
            'to_number' => 999000000,
            'technical_key' => null,
            'valid_from' => now()->subYear()->toDateString(),
            'valid_to' => now()->addYear()->toDateString(),
            'active' => true,
            'current_number' => 990000001,
        ]);

        return ElectronicDocument::create(array_merge([
            'company_id' => $company->id,
            'resolution_id' => $resolution->id,
            'document_type' => 'fev',
            'reference_code' => 'ref-' . uniqid(),
            'dian_number' => 'SETP990000001',
            'cufe_cude' => str_repeat('a', 96),
            'status' => DocumentStatus::UBL_BUILT,
            'environment' => FiscalEnvironment::HABILITACION,
            'subtotal' => '100000.00',
            'total_taxes' => '19000.00',
            'total' => '119000.00',
            'currency_code' => 'COP',
            'issue_date' => now(),
            'source_type' => 'kiosk_invoice',
            'source_id' => 42,
        ], $overrides));
    }

    private function putUnsignedXml(string $path, string $bytes): void
    {
        $storage = $this->app->make(UnsignedXmlStorageInterface::class);
        $reflection = new \ReflectionClass($storage);
        if ($reflection->hasProperty('contents')) {
            $property = $reflection->getProperty('contents');
            $property->setAccessible(true);
            $property->setValue($storage, array_merge($property->getValue($storage) ?: [], [$path => $bytes]));
        }
    }

    private function putLegacyArtifact(string $path, string $bytes): void
    {
        $storage = $this->app->make(LegacyPtArtifactStorageInterface::class);
        $reflection = new \ReflectionClass($storage);
        if ($reflection->hasProperty('store')) {
            $property = $reflection->getProperty('store');
            $property->setAccessible(true);
            $property->setValue($storage, array_merge($property->getValue($storage) ?: [], [$path => $bytes]));
        }
    }
}
