<?php

namespace App\Services\ElectronicInvoicing\Storage;

use App\Models\ElectronicDocument;

/**
 * Persists unsigned UBL payloads emitted by DocumentEmitter.
 *
 * Production wiring should target the encrypted fiscal disk (see
 * config('electronic-invoicing.storage_disk')). Test wiring uses an in-memory
 * implementation so feature tests never touch the host filesystem.
 *
 * Implementations MUST return a logical, deterministic path that the caller
 * can persist into ElectronicDocument.xml_unsigned_path. Implementations MUST
 * NOT log XML contents.
 */
interface UnsignedXmlStorageInterface
{
    /**
     * Store the unsigned UBL XML for the given document and return its path.
     */
    public function store(ElectronicDocument $document, string $xml): string;

    /**
     * Read previously stored XML by path. Returns null when not found.
     */
    public function retrieve(string $path): ?string;
}
