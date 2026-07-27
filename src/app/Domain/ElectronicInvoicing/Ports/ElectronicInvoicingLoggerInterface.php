<?php

namespace App\Domain\ElectronicInvoicing\Ports;

/**
 * Port for emitting structured Electronic Invoicing log records.
 *
 * Domain code calls this interface; the concrete adapter decides the
 * sink (Laravel log channel, JSON to stdout, file, etc.). The contract
 * is:
 *
 * - Each log record carries `correlation_id`, `electronic_document_id`
 *   (when applicable) and `event_type` automatically.
 * - PII fields (acquirer document numbers, emails, names) are masked
 *   before serialization.
 *
 * Levels are `info`, `warning`, `error`. Use `error` for terminal
 * failures requiring operator attention; `warning` for recoverable
 * events that should still trigger investigation.
 */
interface ElectronicInvoicingLoggerInterface
{
    /**
     * Bind a correlation id for the lifetime of the calling request/job.
     * Subsequent `info|warning|error` calls automatically include it.
     */
    public function withCorrelationId(string $correlationId): self;

    /**
     * Bind an electronic document id for the lifetime of the calling
     * request/job. Subsequent calls automatically include it.
     */
    public function withElectronicDocument(int $electronicDocumentId): self;

    /**
     * @param  array<string,mixed>  $context
     */
    public function info(string $eventType, array $context = []): void;

    /**
     * @param  array<string,mixed>  $context
     */
    public function warning(string $eventType, array $context = []): void;

    /**
     * @param  array<string,mixed>  $context
     */
    public function error(string $eventType, array $context = []): void;
}
