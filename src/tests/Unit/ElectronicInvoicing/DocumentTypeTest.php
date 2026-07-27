<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DocumentTypeTest extends TestCase
{
    public function test_all_documented_dian_document_types_are_supported(): void
    {
        $all = DocumentType::all();

        $this->assertContains(DocumentType::FEV, $all);
        $this->assertContains(DocumentType::DEE_POS, $all);
        $this->assertContains(DocumentType::NC, $all);
        $this->assertContains(DocumentType::ND, $all);
        $this->assertCount(4, $all);
    }

    public function test_is_valid_recognises_known_values(): void
    {
        $this->assertTrue(DocumentType::isValid('fev'));
        $this->assertTrue(DocumentType::isValid('dee_pos'));
        $this->assertTrue(DocumentType::isValid('nc'));
        $this->assertTrue(DocumentType::isValid('nd'));
    }

    public function test_is_valid_rejects_unknown_values(): void
    {
        $this->assertFalse(DocumentType::isValid('support_doc'));
        $this->assertFalse(DocumentType::isValid('FEV'));
        $this->assertFalse(DocumentType::isValid(''));
    }

    public function test_assert_throws_for_unknown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DocumentType::assert('payroll');
    }

    public function test_only_nc_and_nd_are_referencing(): void
    {
        $this->assertTrue(DocumentType::isReferencing(DocumentType::NC));
        $this->assertTrue(DocumentType::isReferencing(DocumentType::ND));
        $this->assertFalse(DocumentType::isReferencing(DocumentType::FEV));
        $this->assertFalse(DocumentType::isReferencing(DocumentType::DEE_POS));
    }
}
