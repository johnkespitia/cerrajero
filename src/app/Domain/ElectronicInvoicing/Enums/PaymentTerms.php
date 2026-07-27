<?php

namespace App\Domain\ElectronicInvoicing\Enums;

use InvalidArgumentException;

final class PaymentTerms
{
    public const CASH = 'cash';
    public const CREDIT = 'credit';

    private const ALL = [
        self::CASH,
        self::CREDIT,
    ];

    public static function all(): array
    {
        return self::ALL;
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::ALL, true);
    }

    public static function assert(string $value): string
    {
        if (!self::isValid($value)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid PaymentTerms "%s". Allowed: %s',
                $value,
                implode(', ', self::ALL)
            ));
        }
        return $value;
    }
}
