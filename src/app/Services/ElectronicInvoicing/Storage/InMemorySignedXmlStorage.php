<?php

namespace App\Services\ElectronicInvoicing\Storage;

use App\Models\ElectronicDocument;

/**
 * Default in-memory signed XML storage. Production rebinds this to
 * a disk-backed implementation pointing at the encrypted fiscal disk.
 */
final class InMemorySignedXmlStorage implements SignedXmlStorageInterface
{
    /** @var array<string, string> */
    private array $contents = [];

    public function store(ElectronicDocument $document, string $xml): string
    {
        $reference = $document->reference_code !== null && $document->reference_code !== ''
            ? (string) $document->reference_code
            : 'doc-' . ($document->id ?? bin2hex(random_bytes(4)));

        $path = sprintf(
            'memory://fiscal/%d/signed/%s.xml',
            (int) $document->company_id,
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
