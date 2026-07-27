<?php

namespace App\Infrastructure\ElectronicInvoicing\Ubl;

/**
 * Canonical UBL 2.1 / DIAN namespace URIs.
 *
 * Concentrated here so builders never duplicate string literals. Updating any
 * of these constants is a regulated change (Anexo Tecnico DIAN) and must be
 * routed through the electronic-invoicing spec.
 */
final class UblNamespaces
{
    public const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    public const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    public const EXT = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';

    public const INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    public const CREDIT_NOTE = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2';
    public const DEBIT_NOTE = 'urn:oasis:names:specification:ubl:schema:xsd:DebitNote-2';
    public const ATTACHED_DOCUMENT = 'urn:oasis:names:specification:ubl:schema:xsd:AttachedDocument-2';

    public const STS = 'dian:gov:co:facturaelectronica:Structures-2-1';
    public const XADES = 'http://uri.etsi.org/01903/v1.3.2#';
    public const DS = 'http://www.w3.org/2000/09/xmldsig#';

    private function __construct()
    {
    }
}
