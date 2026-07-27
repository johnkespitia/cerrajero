<?php

namespace App\Services\ElectronicInvoicing\Certificate;

/**
 * Default in-memory secret store for certificate passwords.
 */
final class InMemoryCertificateSecretStore implements CertificateSecretStoreInterface
{
    /** @var array<string, string> */
    private array $store = [];

    public function put(int $companyId, string $environment, string $fingerprintSha256, string $password): string
    {
        $ref = sprintf(
            'inmem-secret://%d/%s/%s',
            $companyId,
            $environment,
            substr($fingerprintSha256, 0, 16)
        );
        $this->store[$ref] = $password;

        return $ref;
    }

    public function get(string $reference): ?string
    {
        return $this->store[$reference] ?? null;
    }

    public function forget(string $reference): void
    {
        unset($this->store[$reference]);
    }
}
