<?php

namespace App\Domain\ElectronicInvoicing\Enums;

/**
 * Lifecycle of a single RADIAN event (mirrors DocumentStatus but is
 * narrower since each event is a one-shot UBL ApplicationResponse).
 */
final class RadianEventStatus
{
    public const BUILT = 'built';
    public const SIGNED = 'signed';
    public const SENT_TO_DIAN = 'sent_to_dian';
    public const DIAN_ACCEPTED = 'dian_accepted';
    public const DIAN_REJECTED = 'dian_rejected';
    public const ERROR = 'error';

    public const ALL = [
        self::BUILT,
        self::SIGNED,
        self::SENT_TO_DIAN,
        self::DIAN_ACCEPTED,
        self::DIAN_REJECTED,
        self::ERROR,
    ];
}
