<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap\Actions;

use App\Infrastructure\ElectronicInvoicing\Soap\Exceptions\InvalidSoapPayloadException;
use DOMDocument;
use DOMElement;

/**
 * GetXmlByDocumentKey(trackId): retrieve the DIAN-validated UBL XML for the
 * given CUFE/CUDE. The WCF method parameter is called trackId in the WSDL but
 * DIAN expects the document key (CUFE/CUDE) here.
 */
final class GetXmlByDocumentKeyAction extends AbstractDianAction
{
    public function operationName(): string
    {
        return 'GetXmlByDocumentKey';
    }

    public function requiredParams(): array
    {
        return ['trackId'];
    }

    protected function buildBodyContents(DOMDocument $doc, DOMElement $operationEl, array $params): void
    {
        $key = (string) $params['trackId'];
        if (preg_match('/^[0-9a-f]{96}$/i', $key) !== 1) {
            throw InvalidSoapPayloadException::for('trackId', 'not a 96-char hex document key');
        }
        $this->appendText($doc, $operationEl, 'trackId', strtolower($key));
    }
}
