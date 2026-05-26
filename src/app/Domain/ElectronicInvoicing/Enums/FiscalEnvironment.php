<?php

namespace App\Domain\ElectronicInvoicing\Enums;

use InvalidArgumentException;

final class FiscalEnvironment
{
    public const HABILITACION = 'habilitacion';
    public const PRODUCTION = 'production';

    private const ALL = [
        self::HABILITACION,
        self::PRODUCTION,
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
                'Invalid FiscalEnvironment "%s". Allowed: %s',
                $value,
                implode(', ', self::ALL)
            ));
        }
        return $value;
    }

    public static function isProduction(string $value): bool
    {
        return $value === self::PRODUCTION;
    }
}
