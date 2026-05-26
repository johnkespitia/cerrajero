<?php

namespace App\Infrastructure\ElectronicInvoicing\Ubl\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a UBL builder receives a payload missing required fields.
 *
 * Message format references the missing dotted path (e.g. "supplier.nit") and
 * never echoes the actual values of neighboring fields.
 */
final class IncompleteUblPayloadException extends InvalidArgumentException
{
    /**
     * @param string $path Dotted payload path of the missing or invalid field.
     * @param string $reason Human reason ("missing", "empty", "not a list", ...).
     */
    public static function for(string $path, string $reason = 'missing'): self
    {
        return new self(sprintf('UBL payload field "%s" is %s.', $path, $reason));
    }
}
