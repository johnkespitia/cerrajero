<?php

namespace App\Domain\ElectronicInvoicing\ValueObjects;

use InvalidArgumentException;

final class DocumentNumber
{
    /** @var string */
    private $prefix;

    /** @var int */
    private $number;

    public function __construct(string $prefix, int $number)
    {
        if ($prefix === '' || strlen($prefix) > 8) {
            throw new InvalidArgumentException(
                "DocumentNumber prefix must be 1..8 chars, got '{$prefix}'"
            );
        }
        if ($number <= 0) {
            throw new InvalidArgumentException("DocumentNumber number must be positive, got {$number}");
        }
        $this->prefix = $prefix;
        $this->number = $number;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function number(): int
    {
        return $this->number;
    }

    public function full(): string
    {
        return $this->prefix . $this->number;
    }

    public function __toString(): string
    {
        return $this->full();
    }
}
