<?php

namespace App\Infrastructure\ElectronicInvoicing\Ubl;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;

/**
 * UBL 2.1 builder for FEV (Factura Electronica de Venta).
 *
 * Anexo Tecnico v1.9. Root element: Invoice.
 *
 * Limites del slice (deuda explicita):
 *  - InvoiceTypeCode usa el catalogo DIAN 13.1.3 con el valor "01" (factura nacional).
 *    Catalogos completos (exportacion, exenta, etc.) entran en el slice
 *    document-assembler.
 *  - Customization "10" corresponde a Estandar; valores especificos por
 *    sector (exportacion, mandato) seran parametrizables en el siguiente slice.
 *  - No se incluyen aun BillingReference / OrderReference / AdditionalDocumentReference.
 *  - QR url y software_security_code se incluyen en sts:DianExtensions solo si
 *    el payload los trae (calculados por los slices previos).
 */
final class Ubl21FevBuilder extends AbstractUblInvoiceBuilder
{
    public function documentType(): string
    {
        return DocumentType::FEV;
    }

    public function anexoVersion(): string
    {
        return '1.9';
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
        return '01';
    }

    protected function documentTypeCodeElementName(): string
    {
        return 'InvoiceTypeCode';
    }

    protected function customizationId(): string
    {
        return '10';
    }
}
