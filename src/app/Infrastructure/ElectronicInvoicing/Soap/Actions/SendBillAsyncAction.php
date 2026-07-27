<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap\Actions;

use DOMDocument;
use DOMElement;

/**
 * SendBillAsync(fileName, contentFile): asynchronous batched submission of
 * one or more signed UBL documents inside a single base64-encoded ZIP.
 */
final class SendBillAsyncAction extends AbstractDianAction
{
    public function operationName(): string
    {
        return 'SendBillAsync';
    }

    public function requiredParams(): array
    {
        return ['fileName', 'contentFile'];
    }

    protected function buildBodyContents(DOMDocument $doc, DOMElement $operationEl, array $params): void
    {
        $this->assertBase64($params['contentFile'], 'contentFile');
        $this->appendText($doc, $operationEl, 'fileName', $params['fileName']);
        $this->appendText($doc, $operationEl, 'contentFile', $params['contentFile']);
    }
}
