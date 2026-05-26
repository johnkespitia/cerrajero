<?php

namespace App\Services\ElectronicInvoicing\Storage;

use App\Models\ElectronicDocument;

/**
 * Stores DIAN ApplicationResponse XML artifacts emitted by `SendBillSync`
 * and async polling. Used by `DianDispatcher` and `DocumentReconciler`.
 *
 * Production wiring should target the encrypted fiscal disk. Tests use an
 * in-memory implementation so audit payloads never hit the host filesystem.
 */
interface DianResponseStorageInterface
{
    public function store(ElectronicDocument $document, string $xml, string $variant = 'application-response'): string;

    public function retrieve(string $path): ?string;
}
