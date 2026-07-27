<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\ValueObjects\Cufe;
use App\Domain\ElectronicInvoicing\ValueObjects\DocumentNumber;
use App\Domain\ElectronicInvoicing\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyAndCufeTest extends TestCase
{
    public function test_money_normalises_amount_to_two_decimals(): void
    {
        $money = new Money('12345.6');
        $this->assertSame('12345.60', $money->amount());
        $this->assertSame('COP', $money->currency());
    }

    public function test_money_rejects_invalid_currency_length(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Money('10.00', 'COLP');
    }

    public function test_cufe_requires_96_hex_chars(): void
    {
        $cufe = new Cufe(str_repeat('a', 96));
        $this->assertSame(str_repeat('a', 96), $cufe->value());
    }

    public function test_cufe_rejects_wrong_length(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Cufe(str_repeat('a', 95));
    }

    public function test_cufe_rejects_non_hex_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Cufe(str_repeat('z', 96));
    }

    public function test_document_number_concatenates_prefix_and_number(): void
    {
        $dn = new DocumentNumber('SETP', 990000001);
        $this->assertSame('SETP', $dn->prefix());
        $this->assertSame(990000001, $dn->number());
        $this->assertSame('SETP990000001', $dn->full());
    }

    public function test_document_number_rejects_empty_prefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DocumentNumber('', 1);
    }

    public function test_document_number_rejects_non_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DocumentNumber('SETP', 0);
    }
}
