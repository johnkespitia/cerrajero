<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap\Actions;

use DOMDocument;
use DOMElement;

/**
 * SendEventUpdateStatus(fileName, contentFile): RADIAN events (030 / 032 /
 * 033 / 034). contentFile carries the base64-encoded ZIP with the signed
 * ApplicationResponse + AttachedDocument envelope.
 */
final class SendEventUpdateStatusAction extends AbstractDianAction
{
    public function operationName(): string
    {
        return 'SendEventUpdateStatus';
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
