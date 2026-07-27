<?php

namespace App\Infrastructure\ElectronicInvoicing\Certificates;

use App\Domain\ElectronicInvoicing\Ports\CertificateProviderInterface;
use App\Models\FiscalCertificate;
use App\Services\ElectronicInvoicing\Certificate\CertificateSecretStoreInterface;
use App\Services\ElectronicInvoicing\Certificate\CertificateStorageInterface;
use App\Services\ElectronicInvoicing\Exceptions\InvalidCertificateException;
use DateTimeImmutable;
use RuntimeException;

/**
 * Resolves the active `FiscalCertificate` for a (company, environment) tuple
 * and loads its PKCS#12 material in memory for the XAdES signer.
 *
 * The provider composes the persistence layers already wired by
 * `ElectronicInvoicingServiceProvider`:
 *
 *  - `FiscalCertificate` (DB row)             -> metadata + storage path + secret ref.
 *  - `CertificateStorageInterface`            -> raw `.p12` bytes.
 *  - `CertificateSecretStoreInterface`        -> password (opaque reference).
 *
 * `active()` is cheap: it only hits the DB. `load()` opens the PKCS#12
 * container with the resolved password, extracts the certificate and the
 * private key PEMs and returns them in memory. The returned array is the
 * minimal contract expected by `XadesEpesSigner::signWithMaterial()`:
 *
 *   ['certificate' => string PEM, 'private_key' => string PEM, 'chain_pem' => string|null]
 *
 * Implementations MUST NOT log the decrypted material; this provider
 * follows that rule by only re-raising structured `InvalidCertificateException`
 * codes without echoing the payload.
 */
final class P12CertificateProvider implements CertificateProviderInterface
{
    public function __construct(
        private readonly CertificateStorageInterface $storage,
        private readonly CertificateSecretStoreInterface $secrets,
    ) {
    }

    public function active(int $companyId, string $environment): array
    {
        $certificate = $this->resolveActive($companyId, $environment);

        return $this->toMetadata($certificate);
    }

    public function load(int $companyId, string $environment): array
    {
        $certificate = $this->resolveActive($companyId, $environment);

        $storagePath = (string) $certificate->getRawOriginal('storage_path');
        $secretRef = (string) $certificate->getRawOriginal('password_secret_ref');
        if ($storagePath === '' || $secretRef === '') {
            throw new RuntimeException(
                'Active fiscal certificate is missing storage or secret references.'
            );
        }

        $bytes = $this->storage->retrieve($storagePath);
        if ($bytes === null) {
            throw new RuntimeException(
                'Active fiscal certificate artifact is not available in storage.'
            );
        }
        $password = $this->secrets->get($secretRef);
        if ($password === null) {
            throw new RuntimeException(
                'Active fiscal certificate password is not available in the secret store.'
            );
        }

        $certs = [];
        $opened = @openssl_pkcs12_read($bytes, $certs, $password);
        if (! $opened || empty($certs['cert']) || empty($certs['pkey'])) {
            throw InvalidCertificateException::cannotOpen();
        }

        $chainPem = null;
        if (isset($certs['extracerts']) && is_array($certs['extracerts']) && $certs['extracerts'] !== []) {
            $chainPem = implode("\n", array_map(static fn ($pem) => (string) $pem, $certs['extracerts']));
        }

        return [
            'certificate' => (string) $certs['cert'],
            'private_key' => (string) $certs['pkey'],
            'chain_pem' => $chainPem,
        ];
    }

    private function resolveActive(int $companyId, string $environment): FiscalCertificate
    {
        $certificate = FiscalCertificate::query()
            ->where('company_id', $companyId)
            ->where('environment', $environment)
            ->where('active', true)
            ->orderByDesc('id')
            ->first();
        if ($certificate === null) {
            throw new RuntimeException(sprintf(
                'No active fiscal certificate found for company %d / environment %s.',
                $companyId,
                $environment
            ));
        }

        $now = new DateTimeImmutable('now');
        if ($certificate->not_after !== null
            && $certificate->not_after->toDateTimeImmutable() <= $now) {
            throw InvalidCertificateException::expired();
        }

        return $certificate;
    }

    private function toMetadata(FiscalCertificate $certificate): array
    {
        return [
            'alias' => sprintf(
                'cert-%d-%s',
                (int) $certificate->id,
                substr((string) $certificate->fingerprint_sha256, 0, 8)
            ),
            'subject_cn' => (string) $certificate->subject_cn,
            'issuer_cn' => (string) $certificate->issuer_cn,
            'serial_number' => (string) $certificate->serial_number,
            'not_before' => $certificate->not_before
                ? $certificate->not_before->toDateTimeImmutable()
                : new DateTimeImmutable('@0'),
            'not_after' => $certificate->not_after
                ? $certificate->not_after->toDateTimeImmutable()
                : new DateTimeImmutable('@0'),
            'fingerprint_sha256' => (string) $certificate->fingerprint_sha256,
        ];
    }
}
