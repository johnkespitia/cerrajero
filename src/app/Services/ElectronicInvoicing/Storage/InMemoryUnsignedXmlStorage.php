<?php

namespace App\Services\ElectronicInvoicing\Storage;

use App\Models\ElectronicDocument;

/**
 * Default unsigned XML storage: keeps payloads in memory only.
 *
 * This is intentionally the default so DocumentEmitter can produce documents
 * during tests and dry-runs without touching the host filesystem or carrying
 * fiscal payloads outside the request scope.
 *
 * Production deployments must replace this with a disk-backed implementation
 * (slice posterior) wired through service-provider binding.
 */
final class InMemoryUnsignedXmlStorage implements UnsignedXmlStorageInterface
{
    /** @var array<string, string> */
    private $contents = [];

    public function store(ElectronicDocument $document, string $xml): string
    {
        $companySegment = (int) $document->company_id;
        $reference = $document->reference_code !== null && $document->reference_code !== ''
            ? (string) $document->reference_code
            : 'doc-' . ($document->id ?? bin2hex(random_bytes(4)));

        $path = sprintf(
            'memory://fiscal/%d/unsigned/%s.xml',
            $companySegment,
            $reference
        );
        $this->contents[$path] = $xml;
        return $path;
    }

    public function retrieve(string $path): ?string
    {
        return $this->contents[$path] ?? null;
    }
}
