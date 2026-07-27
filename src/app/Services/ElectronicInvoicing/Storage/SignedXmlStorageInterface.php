<?php

namespace App\Services\ElectronicInvoicing\Storage;

use App\Models\ElectronicDocument;

/**
 * Persists XAdES-EPES signed UBL payloads emitted by `SigningCoordinator`.
 *
 * Production wiring should target the encrypted fiscal disk (see
 * `config('electronic-invoicing.storage_disk')`). Test wiring uses an
 * in-memory implementation so tests never touch the host filesystem.
 *
 * Implementations MUST return a deterministic, logical path that the
 * caller will persist on `ElectronicDocument.xml_signed_path`. They
 * MUST NOT log XML contents.
 */
interface SignedXmlStorageInterface
{
    public function store(ElectronicDocument $document, string $xml): string;

    public function retrieve(string $path): ?string;
}
