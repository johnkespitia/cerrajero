<?php

namespace App\Services\ElectronicInvoicing\LegacyPt;

/**
 * Default in-memory legacy artifact storage. Production rebinds this to
 * a disk-backed implementation pointing at `config('electronic-invoicing.storage_disk')`.
 */
final class InMemoryLegacyPtArtifactStorage implements LegacyPtArtifactStorageInterface
{
    /** @var array<string, string> */
    private array $store = [];

    public function storeXml(int $companyId, string $legacyPtId, string $xml): string
    {
        $path = sprintf(
            'memory://fiscal/%d/legacy/%s.xml',
            $companyId,
            $this->slug($legacyPtId)
        );
        $this->store[$path] = $xml;

        return $path;
    }

    public function storePdf(int $companyId, string $legacyPtId, string $pdf): string
    {
        $path = sprintf(
            'memory://fiscal/%d/legacy/%s.pdf',
            $companyId,
            $this->slug($legacyPtId)
        );
        $this->store[$path] = $pdf;

        return $path;
    }

    public function retrieve(string $path): ?string
    {
        return $this->store[$path] ?? null;
    }

    private function slug(string $value): string
    {
        $slug = preg_replace('/[^A-Za-z0-9_\-]/', '_', $value);

        return $slug !== '' ? (string) $slug : 'doc';
    }
}
