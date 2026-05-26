<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\ValueObjects\Cufe;
use App\Infrastructure\ElectronicInvoicing\Cufe\Sha384CufeCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class Sha384CufeCalculatorTest extends TestCase
{
    /** @var Sha384CufeCalculator */
    private $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new Sha384CufeCalculator();
    }

    private function fevFields(): array
    {
        return [
            'num_doc' => 'SETP990000001',
            'fec_doc' => '2026-03-26',
            'hora_doc' => '10:30:45-05:00',
            'val_doc' => '100000.00',
            'cod_imp_1' => '01', 'val_imp_1' => '19000.00',
            'cod_imp_2' => '04', 'val_imp_2' => '0.00',
            'cod_imp_3' => '03', 'val_imp_3' => '0.00',
            'val_imp_total' => '119000.00',
            'nit_ofe' => '900000000',
            'num_adq' => '222222222222',
            'clave_tecnica' => 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c',
            'tipo_ambiente' => '2',
        ];
    }

    public function test_fev_payload_concatenates_fields_in_dian_order(): void
    {
        $fields = $this->fevFields();
        $expected = 'SETP990000001'
            . '2026-03-26'
            . '10:30:45-05:00'
            . '100000.00'
            . '01' . '19000.00'
            . '04' . '0.00'
            . '03' . '0.00'
            . '119000.00'
            . '900000000'
            . '222222222222'
            . 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c'
            . '2';

        $this->assertSame($expected, $this->calc->payload(DocumentType::FEV, $fields));
    }

    public function test_fev_cufe_is_sha384_hex_of_payload(): void
    {
        $fields = $this->fevFields();
        $payload = $this->calc->payload(DocumentType::FEV, $fields);

        $cufe = $this->calc->calculate(DocumentType::FEV, $fields);

        $this->assertInstanceOf(Cufe::class, $cufe);
        $this->assertSame(hash('sha384', $payload), $cufe->value());
    }

    public function test_fev_cufe_is_lowercase_hex_with_96_chars(): void
    {
        $cufe = $this->calc->calculate(DocumentType::FEV, $this->fevFields());
        $this->assertSame(96, strlen($cufe->value()));
        $this->assertSame(1, preg_match('/^[0-9a-f]{96}$/', $cufe->value()));
    }

    public function test_nc_uses_pin_instead_of_clave_tecnica(): void
    {
        $schema = $this->calc->fieldsFor(DocumentType::NC);
        $this->assertContains('pin', $schema);
        $this->assertNotContains('clave_tecnica', $schema);
    }

    public function test_nd_uses_pin_instead_of_clave_tecnica(): void
    {
        $schema = $this->calc->fieldsFor(DocumentType::ND);
        $this->assertContains('pin', $schema);
        $this->assertNotContains('clave_tecnica', $schema);
    }

    public function test_dee_pos_uses_pin_instead_of_clave_tecnica(): void
    {
        $schema = $this->calc->fieldsFor(DocumentType::DEE_POS);
        $this->assertContains('pin', $schema);
        $this->assertNotContains('clave_tecnica', $schema);
    }

    public function test_nc_payload_substitutes_clave_tecnica_with_pin(): void
    {
        $fields = $this->fevFields();
        unset($fields['clave_tecnica']);
        $fields['pin'] = '1234567890';

        $payload = $this->calc->payload(DocumentType::NC, $fields);

        $this->assertStringContainsString('1234567890', $payload);
        $this->assertStringNotContainsString('fc8eac422eba16e22ffd8c6f94b3f40a6e38162c', $payload);
        $this->assertSame(hash('sha384', $payload), $this->calc->calculate(DocumentType::NC, $fields)->value());
    }

    public function test_rejects_invalid_document_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->calculate('unknown_type', $this->fevFields());
    }

    public function test_rejects_missing_field(): void
    {
        $fields = $this->fevFields();
        unset($fields['nit_ofe']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing CUFE field "nit_ofe"');
        $this->calc->calculate(DocumentType::FEV, $fields);
    }

    public function test_rejects_null_field(): void
    {
        $fields = $this->fevFields();
        $fields['nit_ofe'] = null;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be null');
        $this->calc->calculate(DocumentType::FEV, $fields);
    }

    public function test_rejects_non_scalar_field(): void
    {
        $fields = $this->fevFields();
        $fields['nit_ofe'] = ['900000000'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be scalar');
        $this->calc->calculate(DocumentType::FEV, $fields);
    }

    public function test_fields_for_returns_schema_in_declared_order(): void
    {
        $fevSchema = $this->calc->fieldsFor(DocumentType::FEV);
        $this->assertSame('num_doc', $fevSchema[0]);
        $this->assertSame('tipo_ambiente', $fevSchema[count($fevSchema) - 1]);
        $this->assertSame('clave_tecnica', $fevSchema[count($fevSchema) - 2]);
    }

    public function test_payload_differs_when_environment_differs(): void
    {
        $hab = $this->fevFields();
        $hab['tipo_ambiente'] = '2';
        $prod = $this->fevFields();
        $prod['tipo_ambiente'] = '1';

        $this->assertNotSame(
            $this->calc->calculate(DocumentType::FEV, $hab)->value(),
            $this->calc->calculate(DocumentType::FEV, $prod)->value()
        );
    }
}
