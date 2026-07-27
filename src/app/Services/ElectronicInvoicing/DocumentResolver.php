<?php

namespace App\Services\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Models\ElectronicDocument;
use App\Models\KioskInvoice;
use App\Models\Reservation;
use InvalidArgumentException;

/**
 * Decide which DIAN document type applies for each business event.
 *
 * Decision matrix (spec sections "Flujo por tipo de documento" and
 * "Domain bounded context"):
 *
 *  - Reservation checkout                                  -> FEV (always).
 *  - KioskInvoice with electronic_invoice=true AND
 *    an identified acquirer (acquirer_id NOT NULL)        -> FEV.
 *  - KioskInvoice otherwise                                -> DEE POS.
 *  - Cancellation of an issued document                    -> NC.
 *  - Debit adjustment over an issued document              -> ND.
 *
 * The resolver is a pure decision component: it does not allocate numbers,
 * build XML or call DIAN. Side-effect-free, so it is trivially unit-testable.
 */
final class DocumentResolver
{
    public function forReservation(Reservation $reservation): string
    {
        return DocumentType::FEV;
    }

    public function forKioskInvoice(KioskInvoice $invoice): string
    {
        if ($this->kioskRequiresFev($invoice)) {
            return DocumentType::FEV;
        }
        return DocumentType::DEE_POS;
    }

    public function forCancellation(ElectronicDocument $original): string
    {
        $this->assertReferenceable($original);
        return DocumentType::NC;
    }

    public function forDebitAdjustment(ElectronicDocument $original): string
    {
        $this->assertReferenceable($original);
        return DocumentType::ND;
    }

    /**
     * @return array{
     *     type: string,
     *     requires_acquirer: bool,
     *     references_original: bool,
     * }
     */
    public function describe(string $documentType): array
    {
        DocumentType::assert($documentType);
        return [
            'type' => $documentType,
            'requires_acquirer' => $documentType !== DocumentType::DEE_POS,
            'references_original' => DocumentType::isReferencing($documentType),
        ];
    }

    private function kioskRequiresFev(KioskInvoice $invoice): bool
    {
        if (empty($invoice->electronic_invoice)) {
            return false;
        }
        if (empty($invoice->acquirer_id)) {
            return false;
        }
        return true;
    }

    private function assertReferenceable(ElectronicDocument $original): void
    {
        $type = (string) $original->document_type;
        if (!in_array($type, [DocumentType::FEV, DocumentType::DEE_POS], true)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot derive NC/ND from a document of type "%s".',
                $type
            ));
        }
    }
}
