<?php

namespace App\Services\ElectronicInvoicing\Certificate;

/**
 * Default in-memory certificate storage. Production rebinds this to a
 * disk-backed implementation pointing at the encrypted fiscal disk.
 */
final class InMemoryCertificateStorage implements CertificateStorageInterface
{
    /** @var array<string, string> */
    private array $store = [];

    public function store(int $companyId, string $environment, string $fingerprintSha256, string $bytes): string
    {
        $path = sprintf(
            'memory://fiscal/%d/%s/certificates/%s.p12',
            $companyId,
            $environment,
            substr($fingerprintSha256, 0, 16)
        );
        $this->store[$path] = $bytes;

        return $path;
    }

    public function retrieve(string $path): ?string
    {
        return $this->store[$path] ?? null;
    }

    public function delete(string $path): void
    {
        unset($this->store[$path]);
    }
}
