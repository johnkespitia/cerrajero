<?php

namespace App\Domain\ElectronicInvoicing\ValueObjects;

use InvalidArgumentException;

final class Money
{
    public const DEFAULT_CURRENCY = 'COP';

    /** @var string Decimal serialized with 2 places. */
    private $amount;

    /** @var string */
    private $currency;

    public function __construct(string $amount, string $currency = self::DEFAULT_CURRENCY)
    {
        if (!preg_match('/^-?\d+(\.\d{1,4})?$/', $amount)) {
            throw new InvalidArgumentException("Invalid money amount: {$amount}");
        }
        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException("Currency must be ISO-4217 3-letter code, got {$currency}");
        }
        $this->amount = number_format((float) $amount, 2, '.', '');
        $this->currency = strtoupper($currency);
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function __toString(): string
    {
        return $this->amount . ' ' . $this->currency;
    }
}
