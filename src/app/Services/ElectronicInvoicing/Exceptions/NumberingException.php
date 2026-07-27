<?php

namespace App\Services\ElectronicInvoicing\Exceptions;

use App\Models\DianResolution;
use RuntimeException;

/**
 * Raised by NumberingAllocator when it cannot hand out the next dian_number.
 *
 * The message exposes the (companyId, environment, documentType) triplet for
 * audit, but NEVER the technical_key of the resolution. The technical_key is
 * stored under DianResolution.technical_key and resolved exclusively through
 * the model (its $hidden array keeps it out of serialization).
 */
final class NumberingException extends RuntimeException
{
    public const REASON_MISSING = 'missing';
    public const REASON_INACTIVE = 'inactive';
    public const REASON_NOT_YET_VALID = 'not_yet_valid';
    public const REASON_EXPIRED = 'expired';
    public const REASON_EXHAUSTED = 'exhausted';

    /** @var string */
    private $reason;

    /** @var int|null */
    private $resolutionId;

    public function __construct(string $message, string $reason, ?int $resolutionId = null)
    {
        parent::__construct($message);
        $this->reason = $reason;
        $this->resolutionId = $resolutionId;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function resolutionId(): ?int
    {
        return $this->resolutionId;
    }

    public static function resolutionMissing(int $companyId, string $environment, string $documentType): self
    {
        return new self(
            sprintf(
                'No active DianResolution for company=%d, environment=%s, document_type=%s.',
                $companyId,
                $environment,
                $documentType
            ),
            self::REASON_MISSING
        );
    }

    public static function notYetValid(DianResolution $resolution): self
    {
        return new self(
            sprintf('DianResolution %d is not yet valid.', (int) $resolution->id),
            self::REASON_NOT_YET_VALID,
            (int) $resolution->id
        );
    }

    public static function expired(DianResolution $resolution): self
    {
        return new self(
            sprintf('DianResolution %d has expired.', (int) $resolution->id),
            self::REASON_EXPIRED,
            (int) $resolution->id
        );
    }

    public static function exhausted(DianResolution $resolution): self
    {
        return new self(
            sprintf(
                'DianResolution %d is exhausted (current=%d, to=%d).',
                (int) $resolution->id,
                (int) $resolution->current_number,
                (int) $resolution->to_number
            ),
            self::REASON_EXHAUSTED,
            (int) $resolution->id
        );
    }
}
