<?php

namespace App\Services\ElectronicInvoicing\Radian;

use App\Domain\ElectronicInvoicing\Enums\RadianEventCode;
use App\Models\ElectronicDocument;
use DOMDocument;
use DOMElement;

/**
 * Produces a minimal UBL ApplicationResponse XML for a RADIAN event.
 *
 * The output is a *skeleton* compliant with the structure required by
 * the DIAN RADIAN Annex (root element, namespaces, references to the
 * parent invoice CUFE, event code), suitable for signing with
 * XAdES-EPES via the existing XadesEpesSigner. A full Annex-compliant
 * builder (line items, recipients, tax authority IDs) is tracked under
 * the production hardening backlog.
 *
 * No PII or full document amounts are embedded; only the parent CUFE,
 * the event code, and the actor identification are written. This keeps
 * the payload safe to log and easy to test.
 */
class RadianEventBuilder
{
    public function build(ElectronicDocument $parent, string $eventCode, array $context = []): string
    {
        RadianEventCode::assert($eventCode);

        $cufe = trim((string) ($parent->cufe_cude ?? ''));
        $issueDate = ($context['issue_date'] ?? now())->format('Y-m-d');
        $issueTime = ($context['issue_time'] ?? now())->format('H:i:s\Z');
        $cude = (string) ($context['cude'] ?? '');
        $actorNit = (string) ($context['actor_nit'] ?? '');
        $actorName = (string) ($context['actor_name'] ?? '');

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $root = $dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2', 'ApplicationResponse');
        $root->setAttribute('xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $root->setAttribute('xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $root->setAttribute('xmlns:ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        $dom->appendChild($root);

        $this->appendChild($dom, $root, 'cbc:UBLVersionID', '2.1');
        $this->appendChild($dom, $root, 'cbc:CustomizationID', $this->customizationIdFor($eventCode));
        $this->appendChild($dom, $root, 'cbc:ProfileID', 'DIAN 2.1: Eventos del Documento Electronico');
        $this->appendChild($dom, $root, 'cbc:ProfileExecutionID', '2');
        $this->appendChild($dom, $root, 'cbc:ID', (string) ($context['document_id'] ?? '1'));
        $this->appendChild($dom, $root, 'cbc:UUID', $cude, ['schemeName' => 'CUDE-SHA384']);
        $this->appendChild($dom, $root, 'cbc:IssueDate', $issueDate);
        $this->appendChild($dom, $root, 'cbc:IssueTime', $issueTime);

        // Sender (event emitter) -> we keep it as actor.
        $sender = $dom->createElement('cac:SenderParty');
        $partyTax = $dom->createElement('cac:PartyTaxScheme');
        $this->appendChild($dom, $partyTax, 'cbc:RegistrationName', $actorName ?: 'Sender');
        $this->appendChild($dom, $partyTax, 'cbc:CompanyID', $actorNit ?: 'NA', ['schemeID' => '0', 'schemeName' => '31']);
        $sender->appendChild($partyTax);
        $root->appendChild($sender);

        // Receiver -> DIAN factura electronica.
        $receiver = $dom->createElement('cac:ReceiverParty');
        $receiverTax = $dom->createElement('cac:PartyTaxScheme');
        $this->appendChild($dom, $receiverTax, 'cbc:RegistrationName', 'Unidad Administrativa Especial Direccion de Impuestos y Aduanas Nacionales');
        $this->appendChild($dom, $receiverTax, 'cbc:CompanyID', '800197268', ['schemeID' => '4', 'schemeName' => '31']);
        $receiver->appendChild($receiverTax);
        $root->appendChild($receiver);

        // DocumentResponse describes the actual RADIAN event.
        $documentResponse = $dom->createElement('cac:DocumentResponse');
        $response = $dom->createElement('cac:Response');
        $this->appendChild($dom, $response, 'cbc:ResponseCode', $eventCode);
        $this->appendChild($dom, $response, 'cbc:Description', RadianEventCode::label($eventCode));
        $documentResponse->appendChild($response);

        $documentReference = $dom->createElement('cac:DocumentReference');
        $this->appendChild($dom, $documentReference, 'cbc:ID', (string) ($parent->dian_number ?? $parent->id));
        $this->appendChild($dom, $documentReference, 'cbc:UUID', $cufe, ['schemeName' => 'CUFE-SHA384']);
        $this->appendChild($dom, $documentReference, 'cbc:IssueDate', $parent->issue_date?->format('Y-m-d') ?? $issueDate);
        $documentResponse->appendChild($documentReference);

        $root->appendChild($documentResponse);

        return (string) $dom->saveXML();
    }

    private function customizationIdFor(string $eventCode): string
    {
        // Mapping per DIAN RADIAN Annex Table 13.5 (subset).
        return match ($eventCode) {
            RadianEventCode::RECEIPT_ACKNOWLEDGED => '030',
            RadianEventCode::CLAIM => '031',
            RadianEventCode::GOOD_OR_SERVICE_ACKNOWLEDGED => '032',
            RadianEventCode::EXPRESS_ACCEPTANCE => '033',
            RadianEventCode::IMPLICIT_ACCEPTANCE => '034',
            default => $eventCode,
        };
    }

    private function appendChild(DOMDocument $dom, DOMElement $parent, string $name, string $value, array $attrs = []): DOMElement
    {
        $node = $dom->createElement($name, htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
        foreach ($attrs as $k => $v) {
            $node->setAttribute($k, (string) $v);
        }
        $parent->appendChild($node);

        return $node;
    }
}
