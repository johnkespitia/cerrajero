<?php

namespace App\Infrastructure\ElectronicInvoicing\Ubl;

use App\Infrastructure\ElectronicInvoicing\Ubl\Exceptions\IncompleteUblPayloadException;

/**
 * Tiny dotted-path assertion helper used by every UBL builder.
 *
 * Keeps the failure mode uniform: if a required key is missing, the builder
 * raises IncompleteUblPayloadException with the dotted path of the offending
 * field. The helper never reports the payload value itself.
 */
final class UblPayloadValidator
{
    /**
     * @param array<string, mixed> $payload
     * @param array<int, string>   $required Dotted paths that must exist and be non-empty scalars.
     */
    public static function assertRequired(array $payload, array $required): void
    {
        foreach ($required as $path) {
            $value = self::dig($payload, $path);
            if ($value === null || $value === '') {
                throw IncompleteUblPayloadException::for($path);
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function assertNonEmptyList(array $payload, string $path): void
    {
        $value = self::dig($payload, $path);
        if (!is_array($value) || $value === []) {
            throw IncompleteUblPayloadException::for($path, 'missing or not a non-empty list');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return mixed|null
     */
    public static function dig(array $payload, string $path)
    {
        $segments = explode('.', $path);
        $cursor = $payload;
        foreach ($segments as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }
        return $cursor;
    }

    /**
     * @param array<string, mixed> $payload
     * @param mixed                $default
     * @return mixed
     */
    public static function value(array $payload, string $path, $default = null)
    {
        $found = self::dig($payload, $path);
        return $found ?? $default;
    }

    private function __construct()
    {
    }
}
