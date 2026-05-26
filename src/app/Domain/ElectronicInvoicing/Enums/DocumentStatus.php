<?php

namespace App\Domain\ElectronicInvoicing\Enums;

use InvalidArgumentException;

final class DocumentStatus
{
    public const DRAFT = 'draft';
    public const UBL_BUILT = 'ubl_built';
    public const XADES_SIGNED = 'xades_signed';
    public const SENT_TO_DIAN = 'sent_to_dian';
    public const DIAN_VALIDATING = 'dian_validating';
    public const DIAN_TRACK_RECEIVED = 'dian_track_received';
    public const DIAN_ACCEPTED = 'dian_accepted';
    public const DIAN_REJECTED_RECOVERABLE = 'dian_rejected_recoverable';
    public const DIAN_REJECTED_TERMINAL = 'dian_rejected_terminal';
    public const DEAD_LETTER = 'dead_letter';
    public const CONTINGENCY_EMITTED = 'contingency_emitted';
    public const CONTINGENCY_PENDING_SYNC = 'contingency_pending_sync';
    public const LEGACY_IMPORTED = 'legacy_imported';
    public const LEGACY_IMPORT_INCONSISTENT = 'legacy_import_inconsistent';

    private const ALL = [
        self::DRAFT,
        self::UBL_BUILT,
        self::XADES_SIGNED,
        self::SENT_TO_DIAN,
        self::DIAN_VALIDATING,
        self::DIAN_TRACK_RECEIVED,
        self::DIAN_ACCEPTED,
        self::DIAN_REJECTED_RECOVERABLE,
        self::DIAN_REJECTED_TERMINAL,
        self::DEAD_LETTER,
        self::CONTINGENCY_EMITTED,
        self::CONTINGENCY_PENDING_SYNC,
        self::LEGACY_IMPORTED,
        self::LEGACY_IMPORT_INCONSISTENT,
    ];

    private const TERMINAL = [
        self::DIAN_ACCEPTED,
        self::DIAN_REJECTED_TERMINAL,
        self::DEAD_LETTER,
        self::LEGACY_IMPORTED,
        self::LEGACY_IMPORT_INCONSISTENT,
    ];

    private const INITIAL = [
        self::DRAFT,
        self::CONTINGENCY_EMITTED,
        self::LEGACY_IMPORTED,
    ];

    public static function all(): array
    {
        return self::ALL;
    }

    public static function terminal(): array
    {
        return self::TERMINAL;
    }

    public static function initial(): array
    {
        return self::INITIAL;
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::ALL, true);
    }

    public static function isTerminal(string $value): bool
    {
        return in_array($value, self::TERMINAL, true);
    }

    public static function isInitial(string $value): bool
    {
        return in_array($value, self::INITIAL, true);
    }

    public static function assert(string $value): string
    {
        if (!self::isValid($value)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid DocumentStatus "%s". Allowed: %s',
                $value,
                implode(', ', self::ALL)
            ));
        }
        return $value;
    }
}
