<?php

namespace App\Domain\ElectronicInvoicing\ValueObjects;

use InvalidArgumentException;

/**
 * CUFE / CUDE value object. Both share the same shape: SHA-384 hex (96 chars).
 *
 * The actual calculation differs per document type and lives in
 * CufeCalculatorInterface implementations.
 */
final class Cufe
{
    public const LENGTH = 96;

    /** @var string */
    private $value;

    public function __construct(string $value)
    {
        $value = strtolower($value);
        if (!preg_match('/^[0-9a-f]{' . self::LENGTH . '}$/', $value)) {
            throw new InvalidArgumentException(sprintf(
                'CUFE must be a %d-character lowercase hex string (SHA-384).',
                self::LENGTH
            ));
        }
        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(Cufe $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
