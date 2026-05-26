<?php

namespace App\Services\ElectronicInvoicing\LegacyPt;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Domain\ElectronicInvoicing\Ports\ElectronicInvoicingLoggerInterface;
use App\Domain\ElectronicInvoicing\Ports\MetricsRecorderInterface;
use App\Infrastructure\ElectronicInvoicing\Logging\ElectronicInvoicingLogger;
use App\Infrastructure\ElectronicInvoicing\Metrics\InMemoryMetricsRecorder;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\ElectronicDocument;
use App\Models\ElectronicDocumentEvent;
use App\Models\LegacyPtImport;
use App\Services\ElectronicInvoicing\Exceptions\LegacyPtImportException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports a batch of legacy PT documents into the new EI tables.
 *
 * Lifecycle:
 *
 *  1. The `LegacyPtImportController` resolves the bundle from the HTTP
 *     request and delegates here with a normalized array of documents.
 *  2. The importer creates a `LegacyPtImport` row in `status=running`,
 *     binds the bundle's SHA-256 hash, then runs the bundle through
 *     `LegacyPtBundleValidator`.
 *  3. For each validated document the importer:
 *      - reuses or creates a sentinel `DianResolution` of prefix=`LEGACY`
 *        for the (company, environment) tuple. This keeps the existing
 *        NOT NULL FK on `electronic_documents.resolution_id` without
 *        polluting the numbering allocator.
 *      - persists the XML (and optional PDF) through
 *        `LegacyPtArtifactStorageInterface`.
 *      - creates an `ElectronicDocument` row in status
 *        `LEGACY_IMPORTED` (consistent) or `LEGACY_IMPORT_INCONSISTENT`
 *        (CUFE/XML mismatch / artifact missing) with `source_type =
 *        legacy_pt_import` and `legacy_pt_id` populated.
 *      - appends a `legacy_imported` event to the audit log.
 *  4. The importer rolls up counts on the `LegacyPtImport` row and
 *     flips its status to `completed` (any row > 0 inconsistent does
 *     NOT fail the import: finance handles those manually).
 *
 * No DIAN traffic happens here: legacy documents are read-only history.
 */
class LegacyPtImporter
{
    public const SENTINEL_PREFIX = 'LEGACY';

    public function __construct(
        private readonly LegacyPtBundleValidator $validator,
        private readonly LegacyPtArtifactStorageInterface $storage,
        private readonly MetricsRecorderInterface $metrics = new InMemoryMetricsRecorder(),
        private readonly ElectronicInvoicingLoggerInterface $logger = new ElectronicInvoicingLogger(),
    ) {
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return LegacyPtImport
     */
    public function import(array $payload): LegacyPtImport
    {
        $company = $this->resolveCompany($payload);
        $sourcePtName = (string) ($payload['source_pt_name'] ?? 'unknown');
        $documents = is_array($payload['documents'] ?? null) ? $payload['documents'] : [];
        if ($documents === []) {
            throw LegacyPtImportException::emptyBundle();
        }
        $importedBy = isset($payload['imported_by']) ? (int) $payload['imported_by'] : null;
        $environment = (string) ($payload['environment'] ?? FiscalEnvironment::HABILITACION);
        FiscalEnvironment::assert($environment);

        $correlationId = (string) ($payload['correlation_id'] ?? Str::uuid());
        $scopedLogger = $this->logger->withCorrelationId($correlationId);

        $bundleHash = $this->bundleHash($documents);

        $import = LegacyPtImport::create([
            'company_id' => $company->id,
            'source_pt_name' => $sourcePtName,
            'bundle_path' => 'memory://legacy-pt/' . $bundleHash,
            'bundle_hash_sha256' => $bundleHash,
            'status' => 'running',
            'total_documents' => count($documents),
            'consistent_count' => 0,
            'inconsistent_count' => 0,
            'missing_count' => 0,
            'report' => [],
            'imported_by' => $importedBy,
            'started_at' => Carbon::now(),
        ]);

        $scopedLogger->info('legacy_pt.import.started', [
            'company_id' => $company->id,
            'source_pt_name' => $sourcePtName,
            'total_documents' => count($documents),
        ]);

        $rows = $this->validator->validate($documents);

        $resolution = $this->resolveSentinelResolution($company, $environment);

        $consistent = 0;
        $inconsistent = 0;
        $missing = 0;
        $report = [];

        return DB::transaction(function () use (
            $import, $documents, $rows, $company, $environment,
            $resolution, &$consistent, &$inconsistent, &$missing, &$report,
            $scopedLogger, $correlationId
        ): LegacyPtImport {
            foreach ($rows as $row) {
                $index = $row['index'];
                $source = $documents[$index] ?? [];

                if ($row['status'] === LegacyPtBundleValidator::RESULT_MISSING) {
                    $missing += 1;
                    $report[] = [
                        'index' => $index,
                        'legacy_pt_id' => $row['legacy_pt_id'],
                        'status' => 'missing',
                        'reason' => $row['reason'],
                        'details' => $row['details'],
                    ];
                    continue;
                }

                $isConsistent = $row['status'] === LegacyPtBundleValidator::RESULT_CONSISTENT;
                $status = $isConsistent
                    ? DocumentStatus::LEGACY_IMPORTED
                    : DocumentStatus::LEGACY_IMPORT_INCONSISTENT;

                $xmlPath = null;
                if (! empty($source['xml_base64'])) {
                    $decoded = base64_decode((string) $source['xml_base64'], true);
                    if ($decoded !== false && $decoded !== '') {
                        $xmlPath = $this->storage->storeXml(
                            (int) $company->id,
                            (string) ($row['legacy_pt_id'] ?? 'doc-' . $index),
                            $decoded
                        );
                    }
                }

                $pdfPath = null;
                if (! empty($source['pdf_base64'])) {
                    $decoded = base64_decode((string) $source['pdf_base64'], true);
                    if ($decoded !== false && $decoded !== '') {
                        $pdfPath = $this->storage->storePdf(
                            (int) $company->id,
                            (string) ($row['legacy_pt_id'] ?? 'doc-' . $index),
                            $decoded
                        );
                    }
                }

                $document = new ElectronicDocument();
                $document->company_id = (int) $company->id;
                $document->resolution_id = (int) $resolution->id;
                $document->document_type = (string) ($source['document_type'] ?? 'fev');
                $document->reference_code = (string) Str::uuid();
                $document->dian_number = (string) ($source['dian_number'] ?? '');
                $document->cufe_cude = isset($source['cufe_cude']) ? (string) $source['cufe_cude'] : null;
                $document->status = $status;
                $document->environment = $environment;
                $document->subtotal = $this->money($source['subtotal'] ?? $source['total'] ?? 0);
                $document->total_taxes = $this->money($source['total_taxes'] ?? 0);
                $document->total = $this->money($source['total'] ?? 0);
                $document->currency_code = (string) ($source['currency_code'] ?? 'COP');
                $document->issue_date = $this->parseIssueDate($source['issue_date'] ?? null);
                $document->notes = isset($source['notes']) ? (string) $source['notes'] : null;
                $document->source_type = 'legacy_pt_import';
                $document->source_id = (int) $import->id;
                $document->legacy_pt_id = (string) ($source['legacy_pt_id'] ?? '');
                $document->xml_signed_path = $xmlPath;
                $document->pdf_path = $pdfPath;
                $document->contingency = false;
                $document->attempts = 0;
                $document->save();

                ElectronicDocumentEvent::create([
                    'electronic_document_id' => $document->id,
                    'event_type' => 'legacy_imported',
                    'payload' => [
                        'consistent' => $isConsistent,
                        'reason' => $row['reason'],
                        'source_pt' => $import->source_pt_name,
                        'legacy_pt_id' => $document->legacy_pt_id,
                        'xml_signed_path' => $xmlPath,
                    ],
                    'actor' => 'system:legacy_pt_importer',
                    'correlation_id' => $correlationId,
                    'occurred_at' => Carbon::now(),
                ]);

                $report[] = [
                    'index' => $index,
                    'legacy_pt_id' => $row['legacy_pt_id'],
                    'electronic_document_id' => $document->id,
                    'status' => $isConsistent ? 'consistent' : 'inconsistent',
                    'reason' => $row['reason'],
                    'details' => $row['details'],
                ];

                if ($isConsistent) {
                    $consistent += 1;
                } else {
                    $inconsistent += 1;
                }
            }

            $import->consistent_count = $consistent;
            $import->inconsistent_count = $inconsistent;
            $import->missing_count = $missing;
            $import->status = 'completed';
            $import->finished_at = Carbon::now();
            $import->report = $report;
            $import->save();

            $this->metrics->increment('legacy_pt_imports_total', ['status' => 'completed']);
            $this->metrics->setGauge('legacy_pt_last_import_consistent_count', (float) $consistent);
            $this->metrics->setGauge('legacy_pt_last_import_inconsistent_count', (float) $inconsistent);
            $this->metrics->setGauge('legacy_pt_last_import_missing_count', (float) $missing);

            $scopedLogger->info('legacy_pt.import.completed', [
                'company_id' => $company->id,
                'source_pt_name' => $import->source_pt_name,
                'consistent' => $consistent,
                'inconsistent' => $inconsistent,
                'missing' => $missing,
            ]);

            return $import->refresh();
        });
    }

    private function resolveCompany(array $payload): CompanyFiscalProfile
    {
        if (! isset($payload['company_id'])) {
            throw LegacyPtImportException::missingCompany();
        }
        $company = CompanyFiscalProfile::find((int) $payload['company_id']);
        if ($company === null) {
            throw LegacyPtImportException::companyNotFound((int) $payload['company_id']);
        }

        return $company;
    }

    private function resolveSentinelResolution(CompanyFiscalProfile $company, string $environment): DianResolution
    {
        $existing = DianResolution::query()
            ->where('company_id', $company->id)
            ->where('environment', $environment)
            ->where('prefix', self::SENTINEL_PREFIX)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        return DianResolution::create([
            'company_id' => $company->id,
            'environment' => $environment,
            'document_type' => 'fev',
            'resolution_number' => 'LEGACY-PT-IMPORT',
            'resolution_date' => Carbon::create(2000, 1, 1)->toDateString(),
            'prefix' => self::SENTINEL_PREFIX,
            'from_number' => 1,
            'to_number' => 9999999,
            'technical_key' => null,
            'valid_from' => Carbon::create(2000, 1, 1)->toDateString(),
            'valid_to' => Carbon::create(2999, 12, 31)->toDateString(),
            'active' => false,
            'current_number' => 0,
        ]);
    }

    private function bundleHash(array $documents): string
    {
        return hash('sha256', json_encode([
            'count' => count($documents),
            'ids' => array_map(static fn ($doc) => $doc['legacy_pt_id'] ?? null, $documents),
            'cufes' => array_map(static fn ($doc) => $doc['cufe_cude'] ?? null, $documents),
        ], JSON_UNESCAPED_UNICODE));
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function parseIssueDate(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return Carbon::now();
    }
}
