<?php

namespace App\Services\ElectronicInvoicing\LegacyPt;

/**
 * Persists artifacts (XML, optional PDF) of legacy PT documents.
 *
 * Production wiring targets the encrypted fiscal disk. Tests use an
 * in-memory implementation so feature suites stay hermetic.
 *
 * Paths returned by `storeXml` are persisted on
 * `ElectronicDocument.xml_signed_path` (legacy XMLs are already signed
 * by the previous provider). Paths returned by `storePdf` are persisted
 * on `ElectronicDocument.pdf_path` when present.
 */
interface LegacyPtArtifactStorageInterface
{
    /**
     * Store the legacy XML for a given company/legacy id. Implementations
     * MUST return a deterministic path and MUST NOT log XML contents.
     */
    public function storeXml(int $companyId, string $legacyPtId, string $xml): string;

    /**
     * Optionally store a copy of the legacy PDF artifact (binary payload).
     */
    public function storePdf(int $companyId, string $legacyPtId, string $pdf): string;

    /**
     * Read the stored XML by path. Returns null when not found.
     */
    public function retrieve(string $path): ?string;
}
