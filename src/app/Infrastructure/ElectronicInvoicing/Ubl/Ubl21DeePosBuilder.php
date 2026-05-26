<?php

namespace App\Infrastructure\ElectronicInvoicing\Ubl;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;

/**
 * UBL 2.1 builder for DEE POS (Documento Equivalente Electronico tipo POS).
 *
 * Anexo Tecnico DEE v1.0. Root element: Invoice with DIAN InvoiceTypeCode "20"
 * (Documento equivalente electronico - POS) per the DIAN code list as of the
 * spec drafting. The exact code MUST be reviewed against the current Anexo
 * Tecnico DEE version before going live; treated as deuda explicita.
 *
 * Customer (adquiriente) is OPTIONAL for DEE POS: the documento POS often
 * applies to consumidor final no identificado. Builder skips the customer
 * party when payload['customer'] is absent.
 *
 * Limites del slice (deuda explicita):
 *  - InvoiceTypeCode "20" es valor candidato; debe confirmarse contra tabla
 *    13.1.3 vigente y tabla del Anexo DEE.
 *  - Falta TaxRepresentativeParty, PrepaidPayment y catalogos sector POS.
 */
final class Ubl21DeePosBuilder extends AbstractUblInvoiceBuilder
{
    public function documentType(): string
    {
        return DocumentType::DEE_POS;
    }

    public function anexoVersion(): string
    {
        return 'DEE-1.0';
    }

    protected function rootElementName(): string
    {
        return 'Invoice';
    }

    protected function rootNamespace(): string
    {
        return UblNamespaces::INVOICE;
    }

    protected function lineElementName(): string
    {
        return 'InvoiceLine';
    }

    protected function lineQuantityElementName(): string
    {
        return 'InvoicedQuantity';
    }

    protected function documentTypeCode(): string
    {
        return '20';
    }

    protected function documentTypeCodeElementName(): string
    {
        return 'InvoiceTypeCode';
    }

    protected function customizationId(): string
    {
        return '37';
    }

    protected function requiresCustomer(): bool
    {
        return false;
    }
}
