<?php

namespace App\Infrastructure\ElectronicInvoicing\Logging;

use App\Domain\ElectronicInvoicing\Ports\ElectronicInvoicingLoggerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Default logger adapter for the EI pipeline.
 *
 * Wraps a PSR-3 logger (in Laravel that's the app's `LoggerInterface`,
 * typically the configured `electronic-invoicing` channel) and adds:
 *
 * - Correlation id, electronic_document_id and event_type in every record.
 * - PII masking for known sensitive fields (document numbers, names,
 *   emails, phones), including nested arrays.
 * - Recursive sanitization so callers can pass arbitrarily nested context
 *   without worrying about leakage.
 *
 * The logger is **immutable** in the sense that `withCorrelationId` and
 * `withElectronicDocument` return a new instance, which lets the same
 * controller hold a "base" logger and derive request-scoped ones safely.
 */
class ElectronicInvoicingLogger implements ElectronicInvoicingLoggerInterface
{
    /**
     * Field name fragments that are masked before reaching the sink.
     */
    public const SENSITIVE_KEYS = [
        'document_number',
        'document_id',
        'identification',
        'identification_number',
        'ident_number',
        'nit',
        'dni',
        'cc',
        'email',
        'phone',
        'phone_number',
        'mobile',
        'address',
        'address_line',
        'first_name',
        'last_name',
        'full_name',
        'legal_name',
        'trade_name',
        'name',
        'password',
        'pin',
        'pin_value',
        'secret',
        'authorization',
        'token',
        'access_token',
    ];

    private ?string $correlationId = null;
    private ?int $electronicDocumentId = null;

    public function __construct(private readonly LoggerInterface $logger = new NullLogger())
    {
    }

    public function withCorrelationId(string $correlationId): self
    {
        $clone = clone $this;
        $clone->correlationId = $correlationId;

        return $clone;
    }

    public function withElectronicDocument(int $electronicDocumentId): self
    {
        $clone = clone $this;
        $clone->electronicDocumentId = $electronicDocumentId;

        return $clone;
    }

    public function info(string $eventType, array $context = []): void
    {
        $this->logger->info($eventType, $this->enrich($eventType, $context));
    }

    public function warning(string $eventType, array $context = []): void
    {
        $this->logger->warning($eventType, $this->enrich($eventType, $context));
    }

    public function error(string $eventType, array $context = []): void
    {
        $this->logger->error($eventType, $this->enrich($eventType, $context));
    }

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function enrich(string $eventType, array $context): array
    {
        $context['event_type'] = $eventType;
        if ($this->correlationId !== null) {
            $context['correlation_id'] = $this->correlationId;
        }
        if ($this->electronicDocumentId !== null) {
            $context['electronic_document_id'] = $this->electronicDocumentId;
        }

        return $this->mask($context);
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private function mask(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $output = [];
        foreach ($value as $key => $inner) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $output[$key] = self::maskValue($inner);
                continue;
            }
            $output[$key] = is_array($inner) ? $this->mask($inner) : $inner;
        }

        return $output;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $needle) {
            if ($normalized === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a deterministic masked representation that keeps debugging
     * usefulness (first/last char + length) without exposing PII.
     */
    public static function maskValue(mixed $value): string
    {
        if ($value === null) {
            return '***';
        }
        if (is_array($value)) {
            return '***';
        }
        $string = (string) $value;
        $length = strlen($string);
        if ($length <= 2) {
            return str_repeat('*', max($length, 3));
        }
        $first = $string[0];
        $last = $string[$length - 1];

        return $first . str_repeat('*', max($length - 2, 1)) . $last;
    }
}
