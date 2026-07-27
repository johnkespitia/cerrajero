<?php

namespace App\Infrastructure\ElectronicInvoicing\Ubl;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Infrastructure\ElectronicInvoicing\Ubl\Exceptions\IncompleteUblPayloadException;
use DOMDocument;
use DOMElement;

/**
 * UBL 2.1 builder for Nota Credito (NC) per Anexo Tecnico NC v1.6.
 *
 * Root element: CreditNote. Requires at least one referenced document under
 * payload['references'] with cufe + number + issue_date. Optional
 * DiscrepancyResponse code per DIAN catalog (tabla 13.2.4).
 */
final class Ubl21CreditNoteBuilder extends AbstractUblInvoiceBuilder
{
    public function documentType(): string
    {
        return DocumentType::NC;
    }

    public function anexoVersion(): string
    {
        return '1.6';
    }

    protected function rootElementName(): string
    {
        return 'CreditNote';
    }

    protected function rootNamespace(): string
    {
        return UblNamespaces::CREDIT_NOTE;
    }

    protected function lineElementName(): string
    {
        return 'CreditNoteLine';
    }

    protected function lineQuantityElementName(): string
    {
        return 'CreditedQuantity';
    }

    protected function documentTypeCode(): string
    {
        return '91';
    }

    protected function documentTypeCodeElementName(): string
    {
        return 'CreditNoteTypeCode';
    }

    protected function customizationId(): string
    {
        return '20';
    }

    protected function requiredPaths(): array
    {
        $base = parent::requiredPaths();
        $base[] = 'references.0.cufe';
        $base[] = 'references.0.number';
        $base[] = 'references.0.issue_date';
        return $base;
    }

    protected function addAfterHeader(DOMDocument $doc, DOMElement $root, array $payload): void
    {
        if (empty($payload['references']) || !is_array($payload['references'])) {
            throw IncompleteUblPayloadException::for('references', 'missing or not a non-empty list');
        }

        $reference = $payload['references'][0];

        if (!empty($reference['discrepancy_code'])) {
            $discrepancy = $this->cac($doc, 'DiscrepancyResponse');
            $discrepancy->appendChild($this->cbc($doc, 'ReferenceID', $reference['number']));
            $discrepancy->appendChild($this->cbc($doc, 'ResponseCode', $reference['discrepancy_code']));
            if (!empty($reference['discrepancy_description'])) {
                $discrepancy->appendChild(
                    $this->cbc($doc, 'Description', $reference['discrepancy_description'])
                );
            }
            $root->appendChild($discrepancy);
        }

        $billing = $this->cac($doc, 'BillingReference');
        $invoiceRef = $this->cac($doc, 'InvoiceDocumentReference');
        $invoiceRef->appendChild($this->cbc($doc, 'ID', $reference['number']));
        $invoiceRef->appendChild(
            $this->cbc($doc, 'UUID', $reference['cufe'], [
                'schemeID' => $reference['scheme_id'] ?? '2',
                'schemeName' => $reference['scheme_name'] ?? 'CUFE-SHA384',
            ])
        );
        $invoiceRef->appendChild($this->cbc($doc, 'IssueDate', $reference['issue_date']));
        $billing->appendChild($invoiceRef);
        $root->appendChild($billing);
    }
}
