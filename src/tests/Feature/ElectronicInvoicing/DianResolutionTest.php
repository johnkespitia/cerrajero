<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DianResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolution_belongs_to_company_and_persists_range(): void
    {
        $company = $this->makeCompany();
        $resolution = DianResolution::create($this->payload($company->id));

        $this->assertSame($company->id, $resolution->company->id);
        $this->assertSame(990000000, $resolution->from_number);
        $this->assertSame(990010000, $resolution->to_number);
        $this->assertFalse($resolution->isExhausted());
    }

    public function test_is_exhausted_when_current_reaches_to_number(): void
    {
        $company = $this->makeCompany();
        $resolution = DianResolution::create($this->payload($company->id, [
            'current_number' => 990010000,
        ]));

        $this->assertTrue($resolution->isExhausted());
        $this->assertSame(0, $resolution->remaining());
    }

    public function test_unique_constraint_per_company_env_type_prefix_number(): void
    {
        $company = $this->makeCompany();
        DianResolution::create($this->payload($company->id));

        $this->expectException(QueryException::class);
        DianResolution::create($this->payload($company->id));
    }

    public function test_technical_key_is_hidden_from_serialisation(): void
    {
        $company = $this->makeCompany();
        $resolution = DianResolution::create($this->payload($company->id, [
            'technical_key' => 'fc8eac422eba16e22ffd8c6f',
        ]));

        $array = $resolution->toArray();
        $this->assertArrayNotHasKey('technical_key', $array);
        $this->assertSame('fc8eac422eba16e22ffd8c6f', $resolution->technical_key);
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

    private function payload(int $companyId, array $overrides = []): array
    {
        return array_merge([
            'company_id' => $companyId,
            'environment' => FiscalEnvironment::HABILITACION,
            'document_type' => DocumentType::FEV,
            'prefix' => 'SETP',
            'resolution_number' => '18760000001',
            'resolution_date' => '2026-01-01',
            'from_number' => 990000000,
            'to_number' => 990010000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2027-01-01',
            'technical_key' => null,
            'current_number' => 990000000,
            'active' => true,
        ], $overrides);
    }
}
