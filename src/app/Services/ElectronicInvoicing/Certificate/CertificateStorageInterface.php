<?php

namespace App\Services\ElectronicInvoicing\Certificate;

/**
 * Stores raw `.p12` payloads on the encrypted fiscal disk.
 *
 * Production wiring should target `config('electronic-invoicing.storage_disk')`
 * (`storage/app/fiscal/**` is the default), which must live on an encrypted
 * filesystem in deployments. Tests use an in-memory implementation so the
 * payload (which can contain a private key) never leaves the test process.
 *
 * Implementations MUST return a deterministic path that the caller will
 * persist on `fiscal_certificates.storage_path`. Implementations MUST NOT
 * log the payload nor any derived metadata.
 */
interface CertificateStorageInterface
{
    /**
     * Persist the raw `.p12` bytes for a given (company, environment, fingerprint)
     * tuple. Returns the logical path.
     */
    public function store(int $companyId, string $environment, string $fingerprintSha256, string $bytes): string;

    /**
     * Retrieve previously stored bytes by path. Returns null when not found.
     */
    public function retrieve(string $path): ?string;

    /**
     * Remove the artifact tied to the given path. No-op when the path is missing.
     */
    public function delete(string $path): void;
}
