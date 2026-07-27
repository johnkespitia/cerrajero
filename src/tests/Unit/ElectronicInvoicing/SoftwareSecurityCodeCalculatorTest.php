<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Infrastructure\ElectronicInvoicing\Cufe\SoftwareSecurityCodeCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SoftwareSecurityCodeCalculatorTest extends TestCase
{
    /** @var SoftwareSecurityCodeCalculator */
    private $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new SoftwareSecurityCodeCalculator();
    }

    public function test_returns_sha384_of_concatenation(): void
    {
        $code = $this->calc->calculate('abc', '123', 'xyz');
        $this->assertSame(hash('sha384', 'abc123xyz'), $code);
    }

    public function test_returns_96_char_lowercase_hex(): void
    {
        $code = $this->calc->calculate(
            '11111111-2222-3333-4444-555555555555',
            'SETP990000001',
            'super-secret-pin'
        );

        $this->assertSame(96, strlen($code));
        $this->assertSame(1, preg_match('/^[0-9a-f]{96}$/', $code));
    }

    public function test_supports_empty_identifier_for_software_wide_code(): void
    {
        $code = $this->calc->calculate('software-id', '', 'pin-value');
        $this->assertSame(hash('sha384', 'software-idpin-value'), $code);
    }

    public function test_rejects_empty_software_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('softwareId');
        $this->calc->calculate('', 'identifier', 'pin');
    }

    public function test_rejects_empty_pin(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('pin');
        $this->calc->calculate('software-id', 'identifier', '');
    }

    public function test_different_inputs_produce_different_outputs(): void
    {
        $a = $this->calc->calculate('s1', 'NumFac-1', 'pin1');
        $b = $this->calc->calculate('s1', 'NumFac-2', 'pin1');
        $c = $this->calc->calculate('s1', 'NumFac-1', 'pin2');

        $this->assertNotSame($a, $b);
        $this->assertNotSame($a, $c);
    }
}
