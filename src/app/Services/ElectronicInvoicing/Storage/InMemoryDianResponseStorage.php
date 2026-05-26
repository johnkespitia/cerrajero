<?php

namespace App\Services\ElectronicInvoicing\Storage;

use App\Models\ElectronicDocument;

final class InMemoryDianResponseStorage implements DianResponseStorageInterface
{
    /** @var array<string, string> */
    private array $contents = [];

    public function store(ElectronicDocument $document, string $xml, string $variant = 'application-response'): string
    {
        $reference = $document->reference_code !== null && $document->reference_code !== ''
            ? (string) $document->reference_code
            : 'doc-' . ($document->id ?? bin2hex(random_bytes(4)));

        $path = sprintf(
            'memory://fiscal/%d/dian-%s/%s.xml',
            (int) $document->company_id,
            $variant,
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
