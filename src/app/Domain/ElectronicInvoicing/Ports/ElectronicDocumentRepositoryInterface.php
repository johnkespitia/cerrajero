<?php

namespace App\Domain\ElectronicInvoicing\Ports;

use App\Models\ElectronicDocument;

interface ElectronicDocumentRepositoryInterface
{
    public function findById(int $id): ?ElectronicDocument;

    public function findByReferenceCode(int $companyId, string $referenceCode): ?ElectronicDocument;

    public function findByDianTrackId(string $trackId): ?ElectronicDocument;

    /**
     * Documents in non-terminal status older than a given threshold.
     *
     * @return iterable<ElectronicDocument>
     */
    public function pendingOlderThan(\DateTimeImmutable $threshold): iterable;
}
