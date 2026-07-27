<?php

namespace App\Services\ElectronicInvoicing\Certificate;

/**
 * Stores the certificate password outside of the database.
 *
 * The fiscal-admin slice never persists plain passwords on
 * `fiscal_certificates.password_secret_ref`: it stores an opaque reference
 * (e.g. an AWS Secrets Manager ARN or a Vault path) that downstream
 * signing slices use to fetch the password when actually opening the
 * `.p12` for XAdES signing.
 *
 * For tests and local development we ship an in-memory implementation.
 */
interface CertificateSecretStoreInterface
{
    /**
     * Persist a password and return an opaque reference. Implementations
     * MUST NOT log the password value.
     */
    public function put(int $companyId, string $environment, string $fingerprintSha256, string $password): string;

    /**
     * Retrieve the password from its opaque reference. Returns null when
     * the reference cannot be resolved.
     */
    public function get(string $reference): ?string;

    /**
     * Remove the entry by reference. No-op when missing.
     */
    public function forget(string $reference): void;
}
