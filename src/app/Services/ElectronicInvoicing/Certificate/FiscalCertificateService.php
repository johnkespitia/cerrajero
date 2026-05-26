<?php

namespace App\Services\ElectronicInvoicing\Certificate;

use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\FiscalCertificate;
use App\Services\ElectronicInvoicing\Exceptions\InvalidCertificateException;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the upload, listing, activation and deletion of
 * `FiscalCertificate` records for the fiscal-admin slice.
 *
 * Upload flow:
 *  1. The HTTP controller hands over (companyId, environment,
 *     p12Bytes, password) plus optional rotation flags.
 *  2. The service delegates parsing to `P12CertificateParser`.
 *  3. The service refuses already-expired certificates.
 *  4. The service deduplicates by `fingerprint_sha256`.
 *  5. The raw `.p12` bytes flow through `CertificateStorageInterface`
 *     (typically the encrypted fiscal disk).
 *  6. The password flows through `CertificateSecretStoreInterface`.
 *  7. The metadata-only row is persisted on `fiscal_certificates`
 *     with `active=false`.
 *
 * Activation flow:
 *  - `activate($id)` flips the targeted row to `active=true` and
 *    flips every other row of the same (company, environment) tuple
 *    to `active=false`. The operation runs in a single DB transaction.
 *
 * Delete flow:
 *  - `delete($id)` removes the row, deletes the underlying artifact
 *    and forgets the secret reference. The active certificate cannot
 *    be deleted: callers must rotate first.
 */
class FiscalCertificateService
{
    public function __construct(
        private readonly P12CertificateParser $parser,
        private readonly CertificateStorageInterface $storage,
        private readonly CertificateSecretStoreInterface $secrets,
    ) {
    }

    public function upload(int $companyId, string $environment, string $p12Bytes, string $password): FiscalCertificate
    {
        $this->assertEnvironment($environment);
        $company = CompanyFiscalProfile::findOrFail($companyId);

        $parsed = $this->parser->parse($p12Bytes, $password);

        $now = new DateTimeImmutable('now');
        if ($parsed->notAfter <= $now) {
            throw InvalidCertificateException::expired();
        }

        $existing = FiscalCertificate::query()
            ->where('fingerprint_sha256', $parsed->fingerprintSha256)
            ->first();
        if ($existing !== null) {
            throw InvalidCertificateException::duplicateFingerprint($parsed->fingerprintSha256);
        }

        $storagePath = $this->storage->store($company->id, $environment, $parsed->fingerprintSha256, $p12Bytes);
        $secretRef = $this->secrets->put($company->id, $environment, $parsed->fingerprintSha256, $password);

        return FiscalCertificate::create([
            'company_id' => $company->id,
            'environment' => $environment,
            'subject_cn' => $parsed->subjectCn,
            'issuer_cn' => $parsed->issuerCn,
            'serial_number' => $parsed->serialNumber,
            'not_before' => Carbon::instance($parsed->notBefore),
            'not_after' => Carbon::instance($parsed->notAfter),
            'fingerprint_sha256' => $parsed->fingerprintSha256,
            'storage_path' => $storagePath,
            'password_secret_ref' => $secretRef,
            'active' => false,
            'loaded_at' => Carbon::now(),
        ]);
    }

    public function list(int $companyId, string $environment): array
    {
        $this->assertEnvironment($environment);

        return FiscalCertificate::query()
            ->where('company_id', $companyId)
            ->where('environment', $environment)
            ->orderByDesc('active')
            ->orderByDesc('not_after')
            ->get()
            ->all();
    }

    public function activate(int $certificateId): FiscalCertificate
    {
        return DB::transaction(function () use ($certificateId): FiscalCertificate {
            $certificate = FiscalCertificate::query()->findOrFail($certificateId);

            $now = new DateTimeImmutable('now');
            if ($certificate->not_after !== null && $certificate->not_after->toDateTimeImmutable() <= $now) {
                throw InvalidCertificateException::expired();
            }

            FiscalCertificate::query()
                ->where('company_id', $certificate->company_id)
                ->where('environment', $certificate->environment)
                ->where('id', '!=', $certificate->id)
                ->update(['active' => false]);

            $certificate->active = true;
            $certificate->save();

            return $certificate->refresh();
        });
    }

    public function delete(int $certificateId): void
    {
        $certificate = FiscalCertificate::query()->findOrFail($certificateId);

        if ($certificate->active) {
            throw new \DomainException('Cannot delete the active certificate. Activate a replacement first.');
        }

        $storagePath = $certificate->getRawOriginal('storage_path');
        $secretRef = $certificate->getRawOriginal('password_secret_ref');

        $certificate->delete();

        if (is_string($storagePath) && $storagePath !== '') {
            $this->storage->delete($storagePath);
        }
        if (is_string($secretRef) && $secretRef !== '') {
            $this->secrets->forget($secretRef);
        }
    }

    private function assertEnvironment(string $environment): void
    {
        FiscalEnvironment::assert($environment);
    }
}
