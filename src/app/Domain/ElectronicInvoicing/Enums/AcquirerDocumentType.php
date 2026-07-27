<?php

namespace App\Domain\ElectronicInvoicing\Enums;

use InvalidArgumentException;

final class AcquirerDocumentType
{
    public const CC = 'cc';
    public const CE = 'ce';
    public const NIT = 'nit';
    public const PASSPORT = 'passport';
    public const PEP = 'pep';
    public const CONSUMIDOR_FINAL = 'consumidor_final';

    private const ALL = [
        self::CC,
        self::CE,
        self::NIT,
        self::PASSPORT,
        self::PEP,
        self::CONSUMIDOR_FINAL,
    ];

    private const IDENTIFIED = [
        self::CC,
        self::CE,
        self::NIT,
        self::PASSPORT,
        self::PEP,
    ];

    public static function all(): array
    {
        return self::ALL;
    }

    public static function identified(): array
    {
        return self::IDENTIFIED;
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::ALL, true);
    }

    public static function isIdentified(string $value): bool
    {
        return in_array($value, self::IDENTIFIED, true);
    }

    public static function assert(string $value): string
    {
        if (!self::isValid($value)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid AcquirerDocumentType "%s". Allowed: %s',
                $value,
                implode(', ', self::ALL)
            ));
        }
        return $value;
    }
}
