<?php

namespace App\Services\ElectronicInvoicing\Exceptions;

use InvalidArgumentException;

/**
 * Raised by DocumentAssembler / DocumentEmitter when the input does not carry
 * enough fiscal data to produce a UBL payload (e.g. KioskInvoice without
 * resolved lines/totals/acquirer when it requires FEV).
 *
 * Messages reference the missing field/path, never the values of neighbouring
 * fields (no PII leak).
 */
final class IncompleteEmissionPayloadException extends InvalidArgumentException
{
    public static function for(string $path, string $reason = 'missing'): self
    {
        return new self(sprintf('Emission payload field "%s" is %s.', $path, $reason));
    }
}
