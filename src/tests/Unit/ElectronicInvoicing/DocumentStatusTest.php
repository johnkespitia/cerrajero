<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DocumentStatusTest extends TestCase
{
    public function test_includes_all_states_from_state_machine(): void
    {
        $expected = [
            'draft',
            'ubl_built',
            'xades_signed',
            'sent_to_dian',
            'dian_validating',
            'dian_track_received',
            'dian_accepted',
            'dian_rejected_recoverable',
            'dian_rejected_terminal',
            'dead_letter',
            'contingency_emitted',
            'contingency_pending_sync',
            'legacy_imported',
            'legacy_import_inconsistent',
        ];

        $this->assertEqualsCanonicalizing($expected, DocumentStatus::all());
    }

    public function test_terminal_states_are_well_defined(): void
    {
        $this->assertTrue(DocumentStatus::isTerminal(DocumentStatus::DIAN_ACCEPTED));
        $this->assertTrue(DocumentStatus::isTerminal(DocumentStatus::DIAN_REJECTED_TERMINAL));
        $this->assertTrue(DocumentStatus::isTerminal(DocumentStatus::DEAD_LETTER));
        $this->assertTrue(DocumentStatus::isTerminal(DocumentStatus::LEGACY_IMPORTED));
        $this->assertTrue(DocumentStatus::isTerminal(DocumentStatus::LEGACY_IMPORT_INCONSISTENT));

        $this->assertFalse(DocumentStatus::isTerminal(DocumentStatus::DRAFT));
        $this->assertFalse(DocumentStatus::isTerminal(DocumentStatus::CONTINGENCY_EMITTED));
        $this->assertFalse(DocumentStatus::isTerminal(DocumentStatus::DIAN_REJECTED_RECOVERABLE));
    }

    public function test_initial_states_match_spec(): void
    {
        $this->assertTrue(DocumentStatus::isInitial(DocumentStatus::DRAFT));
        $this->assertTrue(DocumentStatus::isInitial(DocumentStatus::CONTINGENCY_EMITTED));
        $this->assertTrue(DocumentStatus::isInitial(DocumentStatus::LEGACY_IMPORTED));
        $this->assertFalse(DocumentStatus::isInitial(DocumentStatus::SENT_TO_DIAN));
    }

    public function test_assert_throws_for_unknown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DocumentStatus::assert('signed_but_not_sent');
    }
}
