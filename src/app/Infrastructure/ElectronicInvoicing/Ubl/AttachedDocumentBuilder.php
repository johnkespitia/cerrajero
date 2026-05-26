<?php

namespace App\Infrastructure\ElectronicInvoicing\Ubl;

use App\Infrastructure\ElectronicInvoicing\Ubl\Exceptions\IncompleteUblPayloadException;
use DOMDocument;
use DOMElement;

/**
 * Wraps an already-signed UBL document plus (optionally) the DIAN
 * ApplicationResponse into an UBL AttachedDocument envelope.
 *
 * The wrapping format follows the DIAN profile:
 *   AttachedDocument
 *     -> cac:Attachment / cac:ExternalReference (BASE64 of the signed UBL)
 *     -> cac:ParentDocumentLineReference / cac:DocumentReference / cac:Attachment
 *        (BASE64 of the DIAN ApplicationResponse, if available)
 *
 * Acceptance is NEVER simulated: if the caller doesn't supply
 * application_response_xml_base64, the wrapper still produces a valid
 * envelope, marking the absence explicitly so downstream consumers can tell
 * "no DIAN response yet" apart from "we faked acceptance".
 */
final class AttachedDocumentBuilder
{
    public function build(array $payload): string
    {
        $this->assertPayload($payload);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $root = $doc->createElementNS(UblNamespaces::ATTACHED_DOCUMENT, 'AttachedDocument');
        $xmlns = 'http://www.w3.org/2000/xmlns/';
        $root->setAttributeNS($xmlns, 'xmlns:cac', UblNamespaces::CAC);
        $root->setAttributeNS($xmlns, 'xmlns:cbc', UblNamespaces::CBC);
        $root->setAttributeNS($xmlns, 'xmlns:ext', UblNamespaces::EXT);
        $doc->appendChild($root);

        $document = $payload['document'];

        $root->appendChild($this->cbc($doc, 'UBLVersionID', 'UBL 2.1'));
        $root->appendChild($this->cbc($doc, 'CustomizationID', $document['customization_id'] ?? 'computacion en la nube'));
        $root->appendChild($this->cbc($doc, 'ProfileID', 'DIAN 2.1'));
        $root->appendChild(
            $this->cbc($doc, 'ProfileExecutionID', $document['environment'] ?? '2')
        );
        $root->appendChild($this->cbc($doc, 'ID', $document['id']));
        $root->appendChild(
            $this->cbc($doc, 'UUID', $document['uuid'], [
                'schemeID' => $document['scheme_id'] ?? '2',
                'schemeName' => $document['scheme_name'] ?? 'CUFE-SHA384',
            ])
        );
        $root->appendChild($this->cbc($doc, 'IssueDate', $document['issue_date']));
        $root->appendChild($this->cbc($doc, 'IssueTime', $document['issue_time']));
        $root->appendChild(
            $this->cbc(
                $doc,
                'DocumentType',
                $document['document_type_label'] ?? 'Contenedor de Documento Electronico'
            )
        );
        $root->appendChild($this->cbc($doc, 'ParentDocumentID', $document['parent_document_id'] ?? $document['id']));

        if (!empty($payload['sender'])) {
            $root->appendChild($this->party($doc, $payload['sender'], 'SenderParty'));
        }
        if (!empty($payload['receiver'])) {
            $root->appendChild($this->party($doc, $payload['receiver'], 'ReceiverParty'));
        }

        $root->appendChild($this->mainAttachment($doc, $payload));
        $root->appendChild($this->parentLineReference($doc, $payload));

        $xml = $doc->saveXML();
        if ($xml === false) {
            throw new \RuntimeException('Could not serialize AttachedDocument XML.');
        }
        return $xml;
    }

    private function assertPayload(array $payload): void
    {
        UblPayloadValidator::assertRequired($payload, [
            'document.id',
            'document.uuid',
            'document.issue_date',
            'document.issue_time',
            'original_xml_base64',
            'parent_document.id',
            'parent_document.uuid',
            'parent_document.issue_date',
        ]);

        $b64 = $payload['original_xml_base64'];
        if (base64_decode($b64, true) === false) {
            throw IncompleteUblPayloadException::for('original_xml_base64', 'not valid base64');
        }

        if (!empty($payload['application_response_xml_base64'])) {
            if (base64_decode($payload['application_response_xml_base64'], true) === false) {
                throw IncompleteUblPayloadException::for(
                    'application_response_xml_base64',
                    'not valid base64'
                );
            }
        }
    }

    private function mainAttachment(DOMDocument $doc, array $payload): DOMElement
    {
        $attachment = $this->cac($doc, 'Attachment');
        $external = $this->cac($doc, 'ExternalReference');
        $external->appendChild($this->cbc($doc, 'MimeCode', 'text/xml'));
        $external->appendChild($this->cbc($doc, 'EncodingCode', 'BASE64'));
        $external->appendChild($this->cbc($doc, 'Description', $payload['original_xml_base64']));
        $attachment->appendChild($external);
        return $attachment;
    }

    private function parentLineReference(DOMDocument $doc, array $payload): DOMElement
    {
        $wrapper = $this->cac($doc, 'ParentDocumentLineReference');
        $wrapper->appendChild($this->cbc($doc, 'LineID', '1'));

        $reference = $this->cac($doc, 'DocumentReference');
        $parent = $payload['parent_document'];

        $reference->appendChild($this->cbc($doc, 'ID', $parent['id']));
        $reference->appendChild(
            $this->cbc($doc, 'UUID', $parent['uuid'], [
                'schemeID' => $parent['scheme_id'] ?? '2',
                'schemeName' => $parent['scheme_name'] ?? 'CUFE-SHA384',
            ])
        );
        $reference->appendChild($this->cbc($doc, 'IssueDate', $parent['issue_date']));
        $reference->appendChild(
            $this->cbc(
                $doc,
                'DocumentType',
                $parent['document_type_label'] ?? 'Documento Electronico Original'
            )
        );

        if (!empty($payload['application_response_xml_base64'])) {
            $arAttachment = $this->cac($doc, 'Attachment');
            $arExternal = $this->cac($doc, 'ExternalReference');
            $arExternal->appendChild($this->cbc($doc, 'MimeCode', 'text/xml'));
            $arExternal->appendChild($this->cbc($doc, 'EncodingCode', 'BASE64'));
            $arExternal->appendChild(
                $this->cbc($doc, 'Description', $payload['application_response_xml_base64'])
            );
            $arAttachment->appendChild($arExternal);
            $reference->appendChild($arAttachment);
        } else {
            $reference->appendChild(
                $this->cbc(
                    $doc,
                    'DocumentStatusCode',
                    $payload['parent_document']['status_code'] ?? 'pending_dian_response'
                )
            );
        }

        $wrapper->appendChild($reference);
        return $wrapper;
    }

    private function party(DOMDocument $doc, array $party, string $wrapperName): DOMElement
    {
        $wrapper = $this->cac($doc, $wrapperName);
        $partyEl = $this->cac($doc, 'Party');
        $partyName = $this->cac($doc, 'PartyName');
        $partyName->appendChild(
            $this->cbc($doc, 'Name', $party['name'] ?? $party['commercial_name'] ?? '')
        );
        $partyEl->appendChild($partyName);

        if (!empty($party['id']) || !empty($party['nit'])) {
            $taxScheme = $this->cac($doc, 'PartyTaxScheme');
            $taxScheme->appendChild(
                $this->cbc($doc, 'RegistrationName', $party['name'] ?? '')
            );
            $idAttrs = ['schemeName' => $party['id_type'] ?? '31'];
            $taxScheme->appendChild(
                $this->cbc($doc, 'CompanyID', $party['id'] ?? $party['nit'], $idAttrs)
            );
            $scheme = $this->cac($doc, 'TaxScheme');
            $scheme->appendChild($this->cbc($doc, 'ID', $party['tax_scheme_code'] ?? '01'));
            $scheme->appendChild($this->cbc($doc, 'Name', $party['tax_scheme_name'] ?? 'IVA'));
            $taxScheme->appendChild($scheme);
            $partyEl->appendChild($taxScheme);
        }

        $wrapper->appendChild($partyEl);
        return $wrapper;
    }

    private function cbc(DOMDocument $doc, string $name, $text = null, array $attrs = []): DOMElement
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

    private function cac(DOMDocument $doc, string $name): DOMElement
    {
        return $doc->createElementNS(UblNamespaces::CAC, 'cac:' . $name);
    }
}
