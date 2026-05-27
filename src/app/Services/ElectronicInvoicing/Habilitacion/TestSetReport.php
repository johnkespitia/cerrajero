<?php

namespace App\Services\ElectronicInvoicing\Habilitacion;

/**
 * Immutable snapshot of a single habilitacion test set run.
 *
 * Serialisable to JSON for archival (`storage/electronic-invoicing/test-sets/`)
 * and to Markdown for console / Slack output. Pure data: no
 * side-effects.
 */
final class TestSetReport
{
    /**
     * @param array{
     *     environment: string,
     *     test_set_id: string,
     *     generated_at: string,
     *     total: int,
     *     accepted: int,
     *     rejected: int,
     *     errors: int,
     *     expectation_failures: int,
     *     acceptance_rate: float,
     *     cases: array<int, array<string, mixed>>
     * } $payload
     */
    public function __construct(private readonly array $payload)
    {
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function total(): int
    {
        return (int) $this->payload['total'];
    }

    public function accepted(): int
    {
        return (int) $this->payload['accepted'];
    }

    public function rejected(): int
    {
        return (int) $this->payload['rejected'];
    }

    public function errors(): int
    {
        return (int) $this->payload['errors'];
    }

    public function expectationFailures(): int
    {
        return (int) $this->payload['expectation_failures'];
    }

    public function acceptanceRate(): float
    {
        return (float) $this->payload['acceptance_rate'];
    }

    public function isCutoverReady(): bool
    {
        return $this->total() > 0 && $this->accepted() === $this->total();
    }

    public function toJson(): string
    {
        return (string) json_encode($this->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function toMarkdown(): string
    {
        $lines = [];
        $lines[] = '# Reporte set de pruebas DIAN';
        $lines[] = '';
        $lines[] = sprintf('- Ambiente: `%s`', $this->payload['environment']);
        $lines[] = sprintf('- TestSetId: `%s`', $this->payload['test_set_id']);
        $lines[] = sprintf('- Generado: `%s`', $this->payload['generated_at']);
        $lines[] = sprintf('- Total: **%d**', $this->total());
        $lines[] = sprintf('- Aceptados: **%d**', $this->accepted());
        $lines[] = sprintf('- Rechazados: **%d**', $this->rejected());
        $lines[] = sprintf('- Errores tecnicos: **%d**', $this->errors());
        $lines[] = sprintf('- Falla de expectativa: **%d**', $this->expectationFailures());
        $lines[] = sprintf('- Tasa de aceptacion: **%.2f%%**', $this->acceptanceRate());
        $lines[] = sprintf('- Cutover ready: **%s**', $this->isCutoverReady() ? 'si' : 'no');
        $lines[] = '';
        $lines[] = '| Codigo | Categoria | Estado | DIAN code | Descripcion |';
        $lines[] = '| --- | --- | --- | --- | --- |';
        foreach ($this->payload['cases'] as $row) {
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s |',
                $row['code'],
                $row['category'],
                $row['status'],
                (string) ($row['dian_status_code'] ?? '—'),
                $row['description']
            );
        }

        return implode("\n", $lines) . "\n";
    }
}
