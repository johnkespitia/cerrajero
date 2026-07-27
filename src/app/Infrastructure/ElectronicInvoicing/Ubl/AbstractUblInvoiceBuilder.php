<?php

namespace App\Infrastructure\ElectronicInvoicing\Ubl;

use App\Domain\ElectronicInvoicing\Ports\UblBuilderInterface;
use App\Infrastructure\ElectronicInvoicing\Ubl\Exceptions\IncompleteUblPayloadException;
use DOMDocument;
use DOMElement;

/**
 * Shared scaffold for Invoice / CreditNote / DebitNote UBL 2.1 builders.
 *
 * Subclasses only declare:
 *  - the root element (Invoice / CreditNote / DebitNote) and its namespace,
 *  - the line element name and quantity element name,
 *  - the document type code (DIAN catalog),
 *  - the customization id (Anexo Tecnico table value),
 *  - and may extend the body with BillingReference (NC / ND).
 *
 * Determinism:
 *  - Elements are appended in the order declared by DIAN's UBL profile.
 *  - DOMDocument with formatOutput=true gives stable pretty-printed output.
 *  - Numeric fields are passed through as strings; the caller is responsible
 *    for normalising decimals (use Money / DocumentNumber upstream).
 *
 * The base class never writes secrets to the XML: PINs, certificate passwords
 * and software IDs are sourced from payload['dian_extensions'] when present
 * (the caller already resolved them via SecretManagerInterface).
 */
abstract class AbstractUblInvoiceBuilder implements UblBuilderInterface
{
    protected const SCHEMA_AGENCY_ID_DIAN = '195';
    protected const SCHEMA_AGENCY_NAME_DIAN = 'CO, DIAN (Direccion de Impuestos y Aduanas Nacionales)';

    abstract public function documentType(): string;

    abstract public function anexoVersion(): string;

    abstract protected function rootElementName(): string;

    abstract protected function rootNamespace(): string;

    abstract protected function lineElementName(): string;

    abstract protected function lineQuantityElementName(): string;

    /**
     * DIAN tipoDocumento code (Anexo Tecnico, tabla 13.1.3 / equivalent).
     */
    abstract protected function documentTypeCode(): string;

    /**
     * Name of the cbc:*TypeCode element placed under the document header.
     */
    abstract protected function documentTypeCodeElementName(): string;

    /**
     * UBL customization ID for this document type per DIAN Anexo Tecnico.
     */
    abstract protected function customizationId(): string;

    /**
     * Whether a customer party is mandatory for this document type.
     */
    protected function requiresCustomer(): bool
    {
        return true;
    }

    /**
     * Subclass hook to inject BillingReference / DiscrepancyResponse etc.
     */
    protected function addAfterHeader(DOMDocument $doc, DOMElement $root, array $payload): void
    {
    }

    /** @return array<int, string> */
    protected function requiredPaths(): array
    {
        $base = [
            'document.number',
            'document.prefix',
            'document.sequence',
            'document.issue_date',
            'document.issue_time',
            'document.currency',
            'document.environment',
            'document.cufe',
            'supplier.nit',
            'supplier.name',
            'totals.line_extension_amount',
            'totals.tax_exclusive_amount',
            'totals.tax_inclusive_amount',
            'totals.payable_amount',
        ];
        if ($this->requiresCustomer()) {
            $base[] = 'customer.id';
            $base[] = 'customer.id_type';
            $base[] = 'customer.name';
        }
        return $base;
    }

    public function build(array $payload): string
    {
        $this->assertPayload($payload);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $root = $doc->createElementNS($this->rootNamespace(), $this->rootElementName());
        $this->declareRootNamespaces($root);
        $doc->appendChild($root);

        $this->addUblExtensions($doc, $root, $payload);
        $this->addDocumentHeader($doc, $root, $payload);
        $this->addAfterHeader($doc, $root, $payload);

        $this->addParty($doc, $root, $payload['supplier'], 'AccountingSupplierParty');
        if (!empty($payload['customer']) && is_array($payload['customer'])) {
            $this->addParty($doc, $root, $payload['customer'], 'AccountingCustomerParty');
        }

        $this->addPaymentMeans($doc, $root, $payload);
        $this->addTaxTotal($doc, $root, $payload);
        $this->addLegalMonetaryTotal($doc, $root, $payload);
        $this->addLines($doc, $root, $payload);

        $xml = $doc->saveXML();
        if ($xml === false) {
            throw new \RuntimeException('Could not serialize UBL XML document.');
        }
        return $xml;
    }

    protected function assertPayload(array $payload): void
    {
        UblPayloadValidator::assertRequired($payload, $this->requiredPaths());
        UblPayloadValidator::assertNonEmptyList($payload, 'lines');

        foreach ($payload['lines'] as $index => $line) {
            $prefix = sprintf('lines.%d', $index);
            if (!is_array($line)) {
                throw IncompleteUblPayloadException::for($prefix, 'not an object');
            }
            UblPayloadValidator::assertRequired($line, [
                'sequence',
                'description',
                'quantity',
                'unit_price',
                'line_total',
            ]);
        }
    }

    private function declareRootNamespaces(DOMElement $root): void
    {
        $xmlns = 'http://www.w3.org/2000/xmlns/';
        $root->setAttributeNS($xmlns, 'xmlns:cac', UblNamespaces::CAC);
        $root->setAttributeNS($xmlns, 'xmlns:cbc', UblNamespaces::CBC);
        $root->setAttributeNS($xmlns, 'xmlns:ext', UblNamespaces::EXT);
        $root->setAttributeNS($xmlns, 'xmlns:sts', UblNamespaces::STS);
        $root->setAttributeNS($xmlns, 'xmlns:xades', UblNamespaces::XADES);
        $root->setAttributeNS($xmlns, 'xmlns:ds', UblNamespaces::DS);
    }

    private function addUblExtensions(DOMDocument $doc, DOMElement $root, array $payload): void
    {
        $extensions = $this->ext($doc, 'UBLExtensions');

        $dianExtension = $this->ext($doc, 'UBLExtension');
        $dianContent = $this->ext($doc, 'ExtensionContent');
        $dianContent->appendChild($this->buildDianExtensions($doc, $payload));
        $dianExtension->appendChild($dianContent);
        $extensions->appendChild($dianExtension);

        // Reserved slot for ds:Signature. The XAdES-EPES signer will populate
        // it in a later slice; we leave the container empty so the envelope is
        // valid UBL today.
        $signatureExtension = $this->ext($doc, 'UBLExtension');
        $signatureExtension->appendChild($this->ext($doc, 'ExtensionContent'));
        $extensions->appendChild($signatureExtension);

        $root->appendChild($extensions);
    }

    private function buildDianExtensions(DOMDocument $doc, array $payload): DOMElement
    {
        $dian = $doc->createElementNS(UblNamespaces::STS, 'sts:DianExtensions');

        $extensionsPayload = isset($payload['dian_extensions']) && is_array($payload['dian_extensions'])
            ? $payload['dian_extensions']
            : [];

        $invoiceControl = $this->sts($doc, 'InvoiceControl');
        if (!empty($extensionsPayload['invoice_authorization'])) {
            $invoiceControl->appendChild(
                $this->sts($doc, 'InvoiceAuthorization', $extensionsPayload['invoice_authorization'])
            );
        }
        if (
            !empty($extensionsPayload['authorization_period_start'])
            || !empty($extensionsPayload['authorization_period_end'])
        ) {
            $period = $this->sts($doc, 'AuthorizationPeriod');
            if (!empty($extensionsPayload['authorization_period_start'])) {
                $period->appendChild(
                    $this->cbc($doc, 'StartDate', $extensionsPayload['authorization_period_start'])
                );
            }
            if (!empty($extensionsPayload['authorization_period_end'])) {
                $period->appendChild(
                    $this->cbc($doc, 'EndDate', $extensionsPayload['authorization_period_end'])
                );
            }
            $invoiceControl->appendChild($period);
        }
        if (
            !empty($extensionsPayload['authorized_prefix'])
            || !empty($extensionsPayload['authorized_from'])
            || !empty($extensionsPayload['authorized_to'])
        ) {
            $authorized = $this->sts($doc, 'AuthorizedInvoices');
            if (!empty($extensionsPayload['authorized_prefix'])) {
                $authorized->appendChild(
                    $this->sts($doc, 'Prefix', $extensionsPayload['authorized_prefix'])
                );
            }
            if (!empty($extensionsPayload['authorized_from'])) {
                $authorized->appendChild(
                    $this->sts($doc, 'From', $extensionsPayload['authorized_from'])
                );
            }
            if (!empty($extensionsPayload['authorized_to'])) {
                $authorized->appendChild(
                    $this->sts($doc, 'To', $extensionsPayload['authorized_to'])
                );
            }
            $invoiceControl->appendChild($authorized);
        }
        if ($invoiceControl->hasChildNodes()) {
            $dian->appendChild($invoiceControl);
        }

        $invoiceSource = $this->sts($doc, 'InvoiceSource');
        $invoiceSource->appendChild(
            $this->cbc(
                $doc,
                'IdentificationCode',
                $extensionsPayload['country_code'] ?? 'CO',
                [
                    'listAgencyID' => '6',
                    'listAgencyName' => 'United Nations Economic Commission for Europe',
                    'listSchemeURI' => 'urn:oasis:names:specification:ubl:codelist:gc:CountryIdentificationCode-2.0',
                ]
            )
        );
        $dian->appendChild($invoiceSource);

        if (!empty($extensionsPayload['software_id'])) {
            $provider = $this->sts($doc, 'SoftwareProvider');
            $provider->appendChild(
                $this->sts($doc, 'ProviderID', $extensionsPayload['provider_nit'] ?? $payload['supplier']['nit'])
            );
            $provider->appendChild(
                $this->sts($doc, 'SoftwareID', $extensionsPayload['software_id'])
            );
            $dian->appendChild($provider);
        }

        if (!empty($extensionsPayload['software_security_code'])) {
            $dian->appendChild(
                $this->sts($doc, 'SoftwareSecurityCode', $extensionsPayload['software_security_code'])
            );
        }

        if (!empty($extensionsPayload['qr_url'])) {
            $dian->appendChild(
                $this->sts($doc, 'QRCode', $extensionsPayload['qr_url'])
            );
        }

        return $dian;
    }

    private function addDocumentHeader(DOMDocument $doc, DOMElement $root, array $payload): void
    {
        $document = $payload['document'];

        $root->appendChild($this->cbc($doc, 'UBLVersionID', 'UBL 2.1'));
        $root->appendChild($this->cbc($doc, 'CustomizationID', $this->customizationId()));
        $root->appendChild($this->cbc($doc, 'ProfileID', 'DIAN 2.1'));
        $root->appendChild(
            $this->cbc($doc, 'ProfileExecutionID', $document['environment'])
        );
        $root->appendChild($this->cbc($doc, 'ID', $document['number']));

        $uuidAttributes = [
            'schemeID' => $document['scheme_id'] ?? '2',
            'schemeName' => $document['scheme_name'] ?? 'CUFE-SHA384',
        ];
        $root->appendChild($this->cbc($doc, 'UUID', $document['cufe'], $uuidAttributes));

        $root->appendChild($this->cbc($doc, 'IssueDate', $document['issue_date']));
        $root->appendChild($this->cbc($doc, 'IssueTime', $document['issue_time']));

        if (!empty($document['due_date'])) {
            $root->appendChild($this->cbc($doc, 'DueDate', $document['due_date']));
        }

        $root->appendChild(
            $this->cbc(
                $doc,
                $this->documentTypeCodeElementName(),
                $this->documentTypeCode()
            )
        );

        if (!empty($document['note'])) {
            $root->appendChild($this->cbc($doc, 'Note', $document['note']));
        }

        $root->appendChild(
            $this->cbc(
                $doc,
                'DocumentCurrencyCode',
                $document['currency']
            )
        );

        $root->appendChild(
            $this->cbc($doc, 'LineCountNumeric', (string) count($payload['lines']))
        );
    }

    private function addParty(DOMDocument $doc, DOMElement $root, array $party, string $wrapperName): void
    {
        $wrapper = $this->cac($doc, $wrapperName);
        $partyEl = $this->cac($doc, 'Party');

        $partyName = $this->cac($doc, 'PartyName');
        $partyName->appendChild(
            $this->cbc($doc, 'Name', $party['name'] ?? $party['commercial_name'] ?? '')
        );
        $partyEl->appendChild($partyName);

        if (!empty($party['address_line']) || !empty($party['city_name'])) {
            $physicalLocation = $this->cac($doc, 'PhysicalLocation');
            $address = $this->cac($doc, 'Address');
            if (!empty($party['city_code'])) {
                $address->appendChild($this->cbc($doc, 'ID', $party['city_code']));
            }
            if (!empty($party['city_name'])) {
                $address->appendChild($this->cbc($doc, 'CityName', $party['city_name']));
            }
            if (!empty($party['department_name'])) {
                $address->appendChild($this->cbc($doc, 'CountrySubentity', $party['department_name']));
            }
            if (!empty($party['address_line'])) {
                $addressLine = $this->cac($doc, 'AddressLine');
                $addressLine->appendChild($this->cbc($doc, 'Line', $party['address_line']));
                $address->appendChild($addressLine);
            }
            $country = $this->cac($doc, 'Country');
            $country->appendChild(
                $this->cbc($doc, 'IdentificationCode', $party['country_code'] ?? 'CO')
            );
            $address->appendChild($country);
            $physicalLocation->appendChild($address);
            $partyEl->appendChild($physicalLocation);
        }

        $taxScheme = $this->cac($doc, 'PartyTaxScheme');
        $taxScheme->appendChild(
            $this->cbc($doc, 'RegistrationName', $party['name'] ?? '')
        );
        $idAttrs = ['schemeName' => $party['id_type'] ?? $party['document_type'] ?? '31'];
        if (!empty($party['verification_digit'])) {
            $idAttrs['schemeID'] = $party['verification_digit'];
        }
        $taxScheme->appendChild(
            $this->cbc($doc, 'CompanyID', $party['id'] ?? $party['nit'] ?? '', $idAttrs)
        );
        $scheme = $this->cac($doc, 'TaxScheme');
        $scheme->appendChild(
            $this->cbc($doc, 'ID', $party['tax_scheme_code'] ?? '01')
        );
        $scheme->appendChild(
            $this->cbc($doc, 'Name', $party['tax_scheme_name'] ?? 'IVA')
        );
        $taxScheme->appendChild($scheme);
        $partyEl->appendChild($taxScheme);

        $wrapper->appendChild($partyEl);
        $root->appendChild($wrapper);
    }

    private function addPaymentMeans(DOMDocument $doc, DOMElement $root, array $payload): void
    {
        if (empty($payload['payment'])) {
            return;
        }
        $payment = $payload['payment'];
        $means = $this->cac($doc, 'PaymentMeans');
        $means->appendChild($this->cbc($doc, 'ID', $payment['terms_code'] ?? '1'));
        $means->appendChild($this->cbc($doc, 'PaymentMeansCode', $payment['means_code'] ?? '10'));
        if (!empty($payment['payment_due_date'])) {
            $means->appendChild($this->cbc($doc, 'PaymentDueDate', $payment['payment_due_date']));
        }
        $root->appendChild($means);
    }

    private function addTaxTotal(DOMDocument $doc, DOMElement $root, array $payload): void
    {
        if (empty($payload['taxes']) || !is_array($payload['taxes'])) {
            return;
        }

        $currency = $payload['document']['currency'];

        $totalTax = '0.00';
        foreach ($payload['taxes'] as $tax) {
            $totalTax = $this->addStrings($totalTax, $tax['tax_amount'] ?? '0');
        }

        $taxTotal = $this->cac($doc, 'TaxTotal');
        $taxTotal->appendChild(
            $this->cbc($doc, 'TaxAmount', $totalTax, ['currencyID' => $currency])
        );

        foreach ($payload['taxes'] as $tax) {
            $subtotal = $this->cac($doc, 'TaxSubtotal');
            $subtotal->appendChild(
                $this->cbc(
                    $doc,
                    'TaxableAmount',
                    $tax['taxable_amount'] ?? '0.00',
                    ['currencyID' => $currency]
                )
            );
            $subtotal->appendChild(
                $this->cbc(
                    $doc,
                    'TaxAmount',
                    $tax['tax_amount'] ?? '0.00',
                    ['currencyID' => $currency]
                )
            );
            $category = $this->cac($doc, 'TaxCategory');
            $category->appendChild(
                $this->cbc($doc, 'Percent', $tax['percent'] ?? '0.00')
            );
            $scheme = $this->cac($doc, 'TaxScheme');
            $scheme->appendChild($this->cbc($doc, 'ID', $tax['code'] ?? '01'));
            $scheme->appendChild($this->cbc($doc, 'Name', $tax['name'] ?? 'IVA'));
            $category->appendChild($scheme);
            $subtotal->appendChild($category);
            $taxTotal->appendChild($subtotal);
        }

        $root->appendChild($taxTotal);
    }

    private function addLegalMonetaryTotal(DOMDocument $doc, DOMElement $root, array $payload): void
    {
        $currency = $payload['document']['currency'];
        $totals = $payload['totals'];

        $totalEl = $this->cac($doc, 'LegalMonetaryTotal');
        $totalEl->appendChild(
            $this->cbc($doc, 'LineExtensionAmount', $totals['line_extension_amount'], ['currencyID' => $currency])
        );
        $totalEl->appendChild(
            $this->cbc($doc, 'TaxExclusiveAmount', $totals['tax_exclusive_amount'], ['currencyID' => $currency])
        );
        $totalEl->appendChild(
            $this->cbc($doc, 'TaxInclusiveAmount', $totals['tax_inclusive_amount'], ['currencyID' => $currency])
        );
        if (!empty($totals['allowance_total'])) {
            $totalEl->appendChild(
                $this->cbc($doc, 'AllowanceTotalAmount', $totals['allowance_total'], ['currencyID' => $currency])
            );
        }
        if (!empty($totals['charge_total'])) {
            $totalEl->appendChild(
                $this->cbc($doc, 'ChargeTotalAmount', $totals['charge_total'], ['currencyID' => $currency])
            );
        }
        $totalEl->appendChild(
            $this->cbc($doc, 'PayableAmount', $totals['payable_amount'], ['currencyID' => $currency])
        );

        $root->appendChild($totalEl);
    }

    private function addLines(DOMDocument $doc, DOMElement $root, array $payload): void
    {
        $currency = $payload['document']['currency'];

        foreach ($payload['lines'] as $line) {
            $lineEl = $this->cac($doc, $this->lineElementName());
            $lineEl->appendChild($this->cbc($doc, 'ID', $line['sequence']));

            $lineEl->appendChild(
                $this->cbc(
                    $doc,
                    $this->lineQuantityElementName(),
                    $line['quantity'],
                    ['unitCode' => $line['unit_code'] ?? 'NIU']
                )
            );

            $lineEl->appendChild(
                $this->cbc(
                    $doc,
                    'LineExtensionAmount',
                    $line['line_total'],
                    ['currencyID' => $currency]
                )
            );

            if (!empty($line['tax_amount']) || !empty($line['taxable_amount'])) {
                $taxTotal = $this->cac($doc, 'TaxTotal');
                $taxTotal->appendChild(
                    $this->cbc(
                        $doc,
                        'TaxAmount',
                        $line['tax_amount'] ?? '0.00',
                        ['currencyID' => $currency]
                    )
                );
                $subtotal = $this->cac($doc, 'TaxSubtotal');
                $subtotal->appendChild(
                    $this->cbc(
                        $doc,
                        'TaxableAmount',
                        $line['taxable_amount'] ?? $line['line_total'],
                        ['currencyID' => $currency]
                    )
                );
                $subtotal->appendChild(
                    $this->cbc(
                        $doc,
                        'TaxAmount',
                        $line['tax_amount'] ?? '0.00',
                        ['currencyID' => $currency]
                    )
                );
                $category = $this->cac($doc, 'TaxCategory');
                $category->appendChild(
                    $this->cbc($doc, 'Percent', $line['tax_percent'] ?? '0.00')
                );
                $scheme = $this->cac($doc, 'TaxScheme');
                $scheme->appendChild(
                    $this->cbc($doc, 'ID', $line['tax_scheme_code'] ?? '01')
                );
                $scheme->appendChild(
                    $this->cbc($doc, 'Name', $line['tax_scheme_name'] ?? 'IVA')
                );
                $category->appendChild($scheme);
                $subtotal->appendChild($category);
                $taxTotal->appendChild($subtotal);
                $lineEl->appendChild($taxTotal);
            }

            $item = $this->cac($doc, 'Item');
            $item->appendChild($this->cbc($doc, 'Description', $line['description']));
            $lineEl->appendChild($item);

            $price = $this->cac($doc, 'Price');
            $price->appendChild(
                $this->cbc($doc, 'PriceAmount', $line['unit_price'], ['currencyID' => $currency])
            );
            $price->appendChild(
                $this->cbc(
                    $doc,
                    'BaseQuantity',
                    $line['base_quantity'] ?? $line['quantity'],
                    ['unitCode' => $line['unit_code'] ?? 'NIU']
                )
            );
            $lineEl->appendChild($price);

            $root->appendChild($lineEl);
        }
    }

    protected function cbc(DOMDocument $doc, string $name, $text = null, array $attrs = []): DOMElement
    {
        $el = $doc->createElementNS(UblNamespaces::CBC, 'cbc:' . $name);
        if ($text !== null && $text !== '') {
            $el->appendChild($doc->createTextNode((string) $text));
        }
        foreach ($attrs as $key => $value) {
            $el->setAttribute($key, (string) $value);
        }
        return $el;
    }

    protected function cac(DOMDocument $doc, string $name): DOMElement
    {
        return $doc->createElementNS(UblNamespaces::CAC, 'cac:' . $name);
    }

    protected function ext(DOMDocument $doc, string $name): DOMElement
    {
        return $doc->createElementNS(UblNamespaces::EXT, 'ext:' . $name);
    }

    protected function sts(DOMDocument $doc, string $name, $text = null): DOMElement
    {
        $el = $doc->createElementNS(UblNamespaces::STS, 'sts:' . $name);
        if ($text !== null && $text !== '') {
            $el->appendChild($doc->createTextNode((string) $text));
        }
        return $el;
    }

    private function addStrings(string $a, string $b): string
    {
        $sum = (float) $a + (float) $b;
        return number_format($sum, 2, '.', '');
    }
}
