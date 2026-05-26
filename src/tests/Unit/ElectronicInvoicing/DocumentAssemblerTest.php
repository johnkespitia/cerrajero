<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\ElectronicDocumentAcquirer;
use App\Services\ElectronicInvoicing\DocumentAssembler;
use App\Services\ElectronicInvoicing\Exceptions\IncompleteEmissionPayloadException;
use Carbon\Carbon;
use Tests\TestCase;

class DocumentAssemblerTest extends TestCase
{
    /** @var DocumentAssembler */
    private $assembler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assembler = new DocumentAssembler();
    }

    public function test_assembles_canonical_fev_payload_with_cufe_fields(): void
    {
        $payload = $this->assembler->assemble($this->fevContext());

        $this->assertSame('SETP990000001', $payload['document']['number']);
        $this->assertSame('fev', $payload['document']['type']);
        $this->assertSame('2', $payload['document']['environment']);
        $this->assertSame('COP', $payload['document']['currency']);

        $this->assertArrayHasKey('supplier', $payload);
        $this->assertSame('900123456', $payload['supplier']['nit']);

        $this->assertArrayHasKey('customer', $payload);
        $this->assertSame('800111222', $payload['customer']['id']);

        $this->assertSame('100000.00', $payload['totals']['line_extension_amount']);
        $this->assertCount(1, $payload['lines']);
        $this->assertSame('NIU', $payload['lines'][0]['unit_code']);

        $this->assertArrayHasKey('cufe_fields', $payload);
        $cufeFields = $payload['cufe_fields'];

        $this->assertSame('SETP990000001', $cufeFields['num_doc']);
        $this->assertSame('CLAVE-TECNICA-PLACEHOLDER', $cufeFields['clave_tecnica']);
        $this->assertSame('2', $cufeFields['tipo_ambiente']);
        $this->assertSame('100000.00', $cufeFields['val_doc']);
        $this->assertSame('19000.00', $cufeFields['val_imp_1']);
        $this->assertSame('0.00', $cufeFields['val_imp_2']);
        $this->assertSame('0.00', $cufeFields['val_imp_3']);
        $this->assertSame('119000.00', $cufeFields['val_imp_total']);
        $this->assertSame('900123456', $cufeFields['nit_ofe']);
        $this->assertSame('800111222', $cufeFields['num_adq']);
        $this->assertArrayNotHasKey('pin', $cufeFields);

        $orderedKeys = array_keys($cufeFields);
        $this->assertSame([
            'num_doc', 'fec_doc', 'hora_doc', 'val_doc',
            'cod_imp_1', 'val_imp_1', 'cod_imp_2', 'val_imp_2', 'cod_imp_3', 'val_imp_3',
            'val_imp_total', 'nit_ofe', 'num_adq', 'clave_tecnica', 'tipo_ambiente',
        ], $orderedKeys);
    }

    public function test_assembles_dee_pos_payload_with_pin_and_default_acquirer(): void
    {
        $context = $this->deePosContext();
        $payload = $this->assembler->assemble($context);

        $this->assertSame('dee_pos', $payload['document']['type']);
        $this->assertArrayNotHasKey('customer', $payload);
        $this->assertSame('222222222222', $payload['cufe_fields']['num_adq']);
        $this->assertSame('PIN-PLACEHOLDER', $payload['cufe_fields']['pin']);
        $this->assertArrayNotHasKey('clave_tecnica', $payload['cufe_fields']);
    }

    public function test_fev_without_acquirer_is_rejected(): void
    {
        $context = $this->fevContext(['acquirer' => null]);

        $this->expectException(IncompleteEmissionPayloadException::class);
        $this->expectExceptionMessageMatches('/acquirer/');
        $this->assembler->assemble($context);
    }

    public function test_fev_without_clave_tecnica_is_rejected(): void
    {
        $context = $this->fevContext(['cufe_signing' => []]);

        $this->expectException(IncompleteEmissionPayloadException::class);
        $this->expectExceptionMessageMatches('/clave_tecnica/');
        $this->assembler->assemble($context);
    }

    public function test_missing_required_line_field_is_rejected(): void
    {
        $context = $this->fevContext([
            'lines' => [[
                'sequence' => 1,
                'description' => 'Item',
                'quantity' => '1',
                'unit_price' => '100000.00',
            ]],
        ]);

        $this->expectException(IncompleteEmissionPayloadException::class);
        $this->expectExceptionMessageMatches('/lines.0.line_total/');
        $this->assembler->assemble($context);
    }

    public function test_missing_total_is_rejected(): void
    {
        $context = $this->fevContext([
            'totals' => [
                'line_extension_amount' => '100000.00',
                'tax_exclusive_amount' => '100000.00',
                'tax_inclusive_amount' => '119000.00',
            ],
        ]);

        $this->expectException(IncompleteEmissionPayloadException::class);
        $this->expectExceptionMessageMatches('/totals.payable_amount/');
        $this->assembler->assemble($context);
    }

    private function fevContext(array $overrides = []): array
    {
        $base = [
            'company' => $this->company(),
            'document_type' => DocumentType::FEV,
            'environment' => FiscalEnvironment::HABILITACION,
            'numbering' => [
                'prefix' => 'SETP',
                'sequence' => 990000001,
                'number' => 'SETP990000001',
                'resolution_id' => 1,
            ],
            'acquirer' => $this->acquirer(),
            'issued_at' => Carbon::create(2026, 3, 26, 10, 30, 0),
            'currency' => 'COP',
            'lines' => [[
                'sequence' => 1,
                'description' => 'Reserva una noche habitacion 101',
                'quantity' => '1',
                'unit_price' => '100000.00',
                'line_total' => '100000.00',
                'tax_amount' => '19000.00',
                'taxable_amount' => '100000.00',
                'tax_percent' => '19.00',
            ]],
            'totals' => [
                'line_extension_amount' => '100000.00',
                'tax_exclusive_amount' => '100000.00',
                'tax_inclusive_amount' => '119000.00',
                'payable_amount' => '119000.00',
            ],
            'taxes' => [[
                'code' => '01',
                'name' => 'IVA',
                'percent' => '19.00',
                'taxable_amount' => '100000.00',
                'tax_amount' => '19000.00',
            ]],
            'payment' => [
                'means_code' => '10',
                'terms_code' => '1',
            ],
            'cufe_signing' => [
                'clave_tecnica' => 'CLAVE-TECNICA-PLACEHOLDER',
            ],
        ];

        return array_replace($base, $overrides);
    }

    private function deePosContext(array $overrides = []): array
    {
        $context = $this->fevContext();
        $context['document_type'] = DocumentType::DEE_POS;
        $context['acquirer'] = null;
        $context['cufe_signing'] = ['pin' => 'PIN-PLACEHOLDER'];
        $context['numbering'] = [
            'prefix' => 'POS',
            'sequence' => 1,
            'number' => 'POS1',
            'resolution_id' => 2,
        ];
        return array_replace($context, $overrides);
    }

    private function company(): CompanyFiscalProfile
    {
        $company = new CompanyFiscalProfile([
            'legal_name' => 'Campo Verde S.A.S.',
            'trade_name' => 'Campo Verde',
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
        $company->id = 1;
        return $company;
    }

    private function acquirer(): ElectronicDocumentAcquirer
    {
        return new ElectronicDocumentAcquirer([
            'document_type' => '31',
            'document_number' => '800111222',
            'dv' => 3,
            'legal_name' => 'Cliente B2B SAS',
            'tax_regime_code' => '48',
            'address_line' => 'Cra 50',
            'city_code_dian' => '11001',
            'country_code' => 'CO',
            'email' => 'b2b@cliente.local',
        ]);
    }
}
