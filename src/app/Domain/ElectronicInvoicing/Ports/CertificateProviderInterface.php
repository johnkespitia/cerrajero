<?php

namespace App\Domain\ElectronicInvoicing\Ports;

/**
 * Resolves the active fiscal certificate for a given company + environment.
 *
 * Concrete impl: App\Infrastructure\ElectronicInvoicing\Certificates\P12CertificateProvider.
 */
interface CertificateProviderInterface
{
    /**
     * @return array{
     *     alias: string,
     *     subject_cn: string,
     *     issuer_cn: string,
     *     serial_number: string,
     *     not_before: \DateTimeImmutable,
     *     not_after: \DateTimeImmutable,
     *     fingerprint_sha256: string,
     * }
     */
    public function active(int $companyId, string $environment): array;

    /**
     * Load the parsed certificate key material in memory for the signer.
     *
     * Implementations MUST never log or persist the decrypted material.
     *
     * @return array{certificate: string, private_key: string}
     */
    public function load(int $companyId, string $environment): array;
}
