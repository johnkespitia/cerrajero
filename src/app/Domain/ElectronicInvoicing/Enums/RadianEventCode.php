<?php

namespace App\Domain\ElectronicInvoicing\Enums;

use InvalidArgumentException;

/**
 * RADIAN event codes (Resolucion 000165, Anexo Tecnico RADIAN 1.0).
 *
 * - 030 acuse recibo de la factura (receipt acknowledged).
 * - 031 reclamo (express rejection).
 * - 032 acuse recibo del bien o servicio (good / service acknowledged).
 * - 033 aceptacion expresa (explicit acceptance).
 * - 034 aceptacion tacita (implicit acceptance after 3 business days).
 *
 * The codes are stored as 3-char strings (zero-padded) because DIAN
 * preserves the leading zero.
 */
final class RadianEventCode
{
    public const RECEIPT_ACKNOWLEDGED = '030';
    public const CLAIM = '031';
    public const GOOD_OR_SERVICE_ACKNOWLEDGED = '032';
    public const EXPRESS_ACCEPTANCE = '033';
    public const IMPLICIT_ACCEPTANCE = '034';

    public const ALL = [
        self::RECEIPT_ACKNOWLEDGED,
        self::CLAIM,
        self::GOOD_OR_SERVICE_ACKNOWLEDGED,
        self::EXPRESS_ACCEPTANCE,
        self::IMPLICIT_ACCEPTANCE,
    ];

    public static function assert(string $code): void
    {
        if (! in_array($code, self::ALL, true)) {
            throw new InvalidArgumentException("Unknown RADIAN event code [{$code}].");
        }
    }

    public static function label(string $code): string
    {
        return match ($code) {
            self::RECEIPT_ACKNOWLEDGED => 'Acuse de recibo (030)',
            self::CLAIM => 'Reclamo (031)',
            self::GOOD_OR_SERVICE_ACKNOWLEDGED => 'Recibo de bien o servicio (032)',
            self::EXPRESS_ACCEPTANCE => 'Aceptacion expresa (033)',
            self::IMPLICIT_ACCEPTANCE => 'Aceptacion tacita (034)',
            default => $code,
        };
    }
}
