<?php

namespace App\Domain\ElectronicInvoicing\StateMachine;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use InvalidArgumentException;

/**
 * Centralised whitelist of valid transitions for ElectronicDocument.
 *
 * Source of truth: section "Maquina de estados de ElectronicDocument" in
 * specs/features/electronic-invoicing-dian.spec.md.
 *
 * This is the canonical, side-effect-free implementation. The DocumentEmitter
 * service (next slice) will delegate state changes through this matrix.
 */
final class StateTransitions
{
    /**
     * Allowed transitions, keyed by source state.
     *
     * @var array<string, string[]>
     */
    private const TRANSITIONS = [
        DocumentStatus::DRAFT => [
            DocumentStatus::UBL_BUILT,
            DocumentStatus::CONTINGENCY_EMITTED,
            DocumentStatus::DEAD_LETTER,
        ],
        DocumentStatus::UBL_BUILT => [
            DocumentStatus::XADES_SIGNED,
            DocumentStatus::DRAFT,
        ],
        DocumentStatus::XADES_SIGNED => [
            DocumentStatus::SENT_TO_DIAN,
            DocumentStatus::DRAFT,
        ],
        DocumentStatus::SENT_TO_DIAN => [
            DocumentStatus::DIAN_VALIDATING,
            DocumentStatus::DIAN_TRACK_RECEIVED,
            DocumentStatus::DIAN_ACCEPTED,
            DocumentStatus::DIAN_REJECTED_RECOVERABLE,
            DocumentStatus::DIAN_REJECTED_TERMINAL,
            DocumentStatus::DEAD_LETTER,
        ],
        DocumentStatus::DIAN_VALIDATING => [
            DocumentStatus::DIAN_ACCEPTED,
            DocumentStatus::DIAN_REJECTED_RECOVERABLE,
            DocumentStatus::DIAN_REJECTED_TERMINAL,
            DocumentStatus::DEAD_LETTER,
        ],
        DocumentStatus::DIAN_TRACK_RECEIVED => [
            DocumentStatus::DIAN_VALIDATING,
            DocumentStatus::DIAN_ACCEPTED,
            DocumentStatus::DIAN_REJECTED_RECOVERABLE,
            DocumentStatus::DIAN_REJECTED_TERMINAL,
            DocumentStatus::DEAD_LETTER,
        ],
        DocumentStatus::DIAN_REJECTED_RECOVERABLE => [
            DocumentStatus::DRAFT,
            DocumentStatus::DIAN_REJECTED_TERMINAL,
        ],
        DocumentStatus::CONTINGENCY_EMITTED => [
            DocumentStatus::CONTINGENCY_PENDING_SYNC,
            DocumentStatus::DEAD_LETTER,
        ],
        DocumentStatus::CONTINGENCY_PENDING_SYNC => [
            DocumentStatus::UBL_BUILT,
            DocumentStatus::DIAN_ACCEPTED,
            DocumentStatus::DIAN_REJECTED_TERMINAL,
            DocumentStatus::DEAD_LETTER,
        ],
        DocumentStatus::DIAN_ACCEPTED => [],
        DocumentStatus::DIAN_REJECTED_TERMINAL => [],
        DocumentStatus::DEAD_LETTER => [],
        DocumentStatus::LEGACY_IMPORTED => [],
        DocumentStatus::LEGACY_IMPORT_INCONSISTENT => [],
    ];

    public static function allowed(string $from): array
    {
        DocumentStatus::assert($from);
        return self::TRANSITIONS[$from] ?? [];
    }

    public static function canTransition(string $from, string $to): bool
    {
        DocumentStatus::assert($from);
        DocumentStatus::assert($to);
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (!self::canTransition($from, $to)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid ElectronicDocument transition: "%s" -> "%s".',
                $from,
                $to
            ));
        }
    }

    /**
     * Document is considered "frozen header" once UBL is built (per spec).
     */
    public static function isHeaderMutable(string $status): bool
    {
        return $status === DocumentStatus::DRAFT;
    }
}
