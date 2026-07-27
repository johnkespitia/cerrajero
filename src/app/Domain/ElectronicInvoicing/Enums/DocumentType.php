<?php

namespace App\Domain\ElectronicInvoicing\Enums;

use InvalidArgumentException;

final class DocumentType
{
    public const FEV = 'fev';
    public const DEE_POS = 'dee_pos';
    public const NC = 'nc';
    public const ND = 'nd';

    private const ALL = [
        self::FEV,
        self::DEE_POS,
        self::NC,
        self::ND,
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
                'Invalid DocumentType "%s". Allowed: %s',
                $value,
                implode(', ', self::ALL)
            ));
        }
        return $value;
    }

    public static function isReferencing(string $value): bool
    {
        return in_array($value, [self::NC, self::ND], true);
    }
}
