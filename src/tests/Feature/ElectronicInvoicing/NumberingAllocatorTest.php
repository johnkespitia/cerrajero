<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Services\ElectronicInvoicing\Exceptions\NumberingException;
use App\Services\ElectronicInvoicing\NumberingAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingAllocatorTest extends TestCase
{
    use RefreshDatabase;

    /** @var NumberingAllocator */
    private $allocator;

    /** @var CompanyFiscalProfile */
    private $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->allocator = new NumberingAllocator();
        $this->company = $this->makeCompany();
    }

    public function test_allocates_consecutive_numbers_without_gaps_or_duplicates(): void
    {
        $resolution = $this->makeResolution(['current_number' => 0, 'from_number' => 990000001, 'to_number' => 990000005]);

        $first = $this->allocator->allocate($this->company->id, FiscalEnvironment::HABILITACION, DocumentType::FEV);
        $second = $this->allocator->allocate($this->company->id, FiscalEnvironment::HABILITACION, DocumentType::FEV);
        $third = $this->allocator->allocate($this->company->id, FiscalEnvironment::HABILITACION, DocumentType::FEV);

        $this->assertSame(990000001, $first['sequence']);
        $this->assertSame(990000002, $second['sequence']);
        $this->assertSame(990000003, $third['sequence']);

        $this->assertSame('SETP990000001', $first['number']);
        $this->assertSame('SETP990000002', $second['number']);
        $this->assertSame('SETP990000003', $third['number']);

        $resolution->refresh();
        $this->assertSame(990000003, (int) $resolution->current_number);
    }

    public function test_throws_when_no_active_resolution_exists(): void
    {
        $this->expectException(NumberingException::class);
        $this->expectExceptionMessageMatches('/No active DianResolution/');

        $this->allocator->allocate(
            $this->company->id,
            FiscalEnvironment::HABILITACION,
            DocumentType::FEV
        );
    }

    public function test_throws_when_resolution_is_expired(): void
    {
        $this->makeResolution([
            'valid_from' => '2020-01-01',
            'valid_to' => '2020-12-31',
            'current_number' => 0,
        ]);

        $this->expectException(NumberingException::class);
        try {
            $this->allocator->allocate($this->company->id, FiscalEnvironment::HABILITACION, DocumentType::FEV);
            $this->fail('Expected NumberingException for expired resolution.');
        } catch (NumberingException $e) {
            $this->assertSame(NumberingException::REASON_EXPIRED, $e->reason());
            throw $e;
        }
    }

    public function test_throws_when_resolution_is_not_yet_valid(): void
    {
        $this->makeResolution([
            'valid_from' => '2099-01-01',
            'valid_to' => '2099-12-31',
            'current_number' => 0,
        ]);

        $this->expectException(NumberingException::class);
        try {
            $this->allocator->allocate($this->company->id, FiscalEnvironment::HABILITACION, DocumentType::FEV);
            $this->fail('Expected NumberingException for not-yet-valid resolution.');
        } catch (NumberingException $e) {
            $this->assertSame(NumberingException::REASON_NOT_YET_VALID, $e->reason());
            throw $e;
        }
    }

    public function test_throws_when_resolution_is_exhausted(): void
    {
        $this->makeResolution([
            'from_number' => 990000001,
            'to_number' => 990000002,
            'current_number' => 990000002,
        ]);

        $this->expectException(NumberingException::class);
        try {
            $this->allocator->allocate($this->company->id, FiscalEnvironment::HABILITACION, DocumentType::FEV);
            $this->fail('Expected NumberingException for exhausted resolution.');
        } catch (NumberingException $e) {
            $this->assertSame(NumberingException::REASON_EXHAUSTED, $e->reason());
            throw $e;
        }
    }

    public function test_inactive_resolution_is_ignored(): void
    {
        $this->makeResolution([
            'active' => false,
            'current_number' => 0,
        ]);

        $this->expectException(NumberingException::class);
        $this->allocator->allocate($this->company->id, FiscalEnvironment::HABILITACION, DocumentType::FEV);
    }

    public function test_environment_and_document_type_filter_correctly(): void
    {
        $this->makeResolution([
            'environment' => FiscalEnvironment::HABILITACION,
            'document_type' => DocumentType::FEV,
            'prefix' => 'SETP',
            'from_number' => 990000001,
            'to_number' => 990001000,
            'current_number' => 0,
        ]);
        $this->makeResolution([
            'environment' => FiscalEnvironment::PRODUCTION,
            'document_type' => DocumentType::FEV,
            'prefix' => 'FE',
            'resolution_number' => '18760000999',
            'from_number' => 100000001,
            'to_number' => 100001000,
            'current_number' => 0,
        ]);

        $hab = $this->allocator->allocate($this->company->id, FiscalEnvironment::HABILITACION, DocumentType::FEV);
        $prod = $this->allocator->allocate($this->company->id, FiscalEnvironment::PRODUCTION, DocumentType::FEV);

        $this->assertSame('SETP990000001', $hab['number']);
        $this->assertSame('FE100000001', $prod['number']);
    }

    private function makeCompany(): CompanyFiscalProfile
    {
        return CompanyFiscalProfile::create([
            'legal_name' => 'Campo Verde S.A.S.',
            'nit' => '900123456',
            'dv' => 1,
            'tax_regime_code' => '48',
            'address_line' => 'Km 5',
            'city_code_dian' => '63190',
            'country_code' => 'CO',
            'email' => 'fiscal@campoverde.local',
            'environment' => FiscalEnvironment::HABILITACION,
            'active' => true,
        ]);
    }

    private function makeResolution(array $overrides = []): DianResolution
    {
        return DianResolution::create(array_merge([
            'company_id' => $this->company->id,
            'environment' => FiscalEnvironment::HABILITACION,
            'document_type' => DocumentType::FEV,
            'prefix' => 'SETP',
            'resolution_number' => '18760000001',
            'resolution_date' => '2026-01-01',
            'from_number' => 990000001,
            'to_number' => 990010000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2099-12-31',
            'technical_key' => null,
            'current_number' => 0,
            'active' => true,
        ], $overrides));
    }
}
