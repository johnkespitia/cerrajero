<?php

namespace App\Services\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\DianResolution;
use App\Services\ElectronicInvoicing\Exceptions\NumberingException;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Allocate the next dian_number atomically within a single transaction.
 *
 *  - Selects the active DianResolution for (company, environment, document_type).
 *  - On MySQL/PostgreSQL acquires a row-level pessimistic lock
 *    (SELECT ... FOR UPDATE).
 *  - On SQLite (tests) falls back to a full transaction; the in-memory DB is
 *    already serialised per-connection so no extra locking is needed.
 *  - Refuses to issue numbers when the resolution is missing, inactive, not
 *    yet valid, expired or exhausted.
 *  - Increments current_number persistently inside the same transaction so a
 *    concurrent allocator never observes a stale value.
 */
final class NumberingAllocator
{
    public function allocate(
        int $companyId,
        string $environment,
        string $documentType,
        ?DateTimeInterface $referenceDate = null
    ): array {
        FiscalEnvironment::assert($environment);
        DocumentType::assert($documentType);

        $today = $referenceDate
            ? Carbon::instance($referenceDate)->startOfDay()
            : Carbon::today();

        return DB::transaction(function () use ($companyId, $environment, $documentType, $today) {
            $query = DianResolution::query()
                ->where('company_id', $companyId)
                ->where('environment', $environment)
                ->where('document_type', $documentType)
                ->where('active', true)
                ->orderBy('id');

            if ($this->shouldLockRows()) {
                $query = $query->lockForUpdate();
            }

            $resolution = $query->first();
            if ($resolution === null) {
                throw NumberingException::resolutionMissing($companyId, $environment, $documentType);
            }

            if ($resolution->valid_from instanceof DateTimeInterface) {
                $validFrom = Carbon::instance($resolution->valid_from)->startOfDay();
                if ($validFrom->gt($today)) {
                    throw NumberingException::notYetValid($resolution);
                }
            }
            if ($resolution->valid_to instanceof DateTimeInterface) {
                $validTo = Carbon::instance($resolution->valid_to)->endOfDay();
                if ($validTo->lt($today)) {
                    throw NumberingException::expired($resolution);
                }
            }

            $current = (int) $resolution->current_number;
            $from = (int) $resolution->from_number;
            $to = (int) $resolution->to_number;

            $next = $current > 0 ? $current + 1 : $from;
            if ($next < $from) {
                $next = $from;
            }
            if ($next > $to) {
                throw NumberingException::exhausted($resolution);
            }

            $resolution->current_number = $next;
            $resolution->save();

            return [
                'resolution' => $resolution,
                'resolution_id' => (int) $resolution->id,
                'prefix' => (string) $resolution->prefix,
                'sequence' => $next,
                'number' => $resolution->prefix . $next,
            ];
        });
    }

    private function shouldLockRows(): bool
    {
        $driver = DB::connection()->getDriverName();
        return !in_array($driver, ['sqlite'], true);
    }
}
