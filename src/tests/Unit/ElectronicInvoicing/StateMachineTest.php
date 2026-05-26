<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\StateMachine\StateTransitions;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class StateMachineTest extends TestCase
{
    public function test_happy_path_sync_emission_transitions_are_allowed(): void
    {
        $path = [
            DocumentStatus::DRAFT,
            DocumentStatus::UBL_BUILT,
            DocumentStatus::XADES_SIGNED,
            DocumentStatus::SENT_TO_DIAN,
            DocumentStatus::DIAN_ACCEPTED,
        ];

        for ($i = 0, $n = count($path) - 1; $i < $n; $i++) {
            $this->assertTrue(
                StateTransitions::canTransition($path[$i], $path[$i + 1]),
                "Expected transition {$path[$i]} -> {$path[$i + 1]} to be allowed."
            );
        }
    }

    public function test_async_path_with_track_received_is_allowed(): void
    {
        $this->assertTrue(StateTransitions::canTransition(
            DocumentStatus::SENT_TO_DIAN,
            DocumentStatus::DIAN_TRACK_RECEIVED
        ));
        $this->assertTrue(StateTransitions::canTransition(
            DocumentStatus::DIAN_TRACK_RECEIVED,
            DocumentStatus::DIAN_ACCEPTED
        ));
    }

    public function test_recoverable_rejection_can_recycle_back_to_draft(): void
    {
        $this->assertTrue(StateTransitions::canTransition(
            DocumentStatus::DIAN_REJECTED_RECOVERABLE,
            DocumentStatus::DRAFT
        ));
    }

    public function test_terminal_states_have_no_outgoing_transitions(): void
    {
        foreach (DocumentStatus::terminal() as $terminal) {
            $this->assertSame(
                [],
                StateTransitions::allowed($terminal),
                "Terminal state {$terminal} must not have outgoing transitions."
            );
        }
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $this->assertFalse(StateTransitions::canTransition(
            DocumentStatus::DRAFT,
            DocumentStatus::DIAN_ACCEPTED
        ));
        $this->assertFalse(StateTransitions::canTransition(
            DocumentStatus::DIAN_ACCEPTED,
            DocumentStatus::DRAFT
        ));
        $this->assertFalse(StateTransitions::canTransition(
            DocumentStatus::LEGACY_IMPORTED,
            DocumentStatus::DRAFT
        ));
    }

    public function test_assert_transition_throws_on_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        StateTransitions::assertTransition(
            DocumentStatus::DIAN_ACCEPTED,
            DocumentStatus::DRAFT
        );
    }

    public function test_contingency_path_can_sync_back_to_normal_flow(): void
    {
        $this->assertTrue(StateTransitions::canTransition(
            DocumentStatus::DRAFT,
            DocumentStatus::CONTINGENCY_EMITTED
        ));
        $this->assertTrue(StateTransitions::canTransition(
            DocumentStatus::CONTINGENCY_EMITTED,
            DocumentStatus::CONTINGENCY_PENDING_SYNC
        ));
        $this->assertTrue(StateTransitions::canTransition(
            DocumentStatus::CONTINGENCY_PENDING_SYNC,
            DocumentStatus::UBL_BUILT
        ));
    }

    public function test_header_is_only_mutable_in_draft(): void
    {
        $this->assertTrue(StateTransitions::isHeaderMutable(DocumentStatus::DRAFT));
        $this->assertFalse(StateTransitions::isHeaderMutable(DocumentStatus::UBL_BUILT));
        $this->assertFalse(StateTransitions::isHeaderMutable(DocumentStatus::DIAN_ACCEPTED));
    }
}
