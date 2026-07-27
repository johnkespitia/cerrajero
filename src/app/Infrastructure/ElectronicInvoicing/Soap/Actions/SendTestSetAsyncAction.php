<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap\Actions;

use DOMDocument;
use DOMElement;

/**
 * SendTestSetAsync(fileName, contentFile, testSetId): habilitacion-only
 * submission of the 60 FEV + 20 NC + 20 ND set required to enable production.
 */
final class SendTestSetAsyncAction extends AbstractDianAction
{
    public function operationName(): string
    {
        return 'SendTestSetAsync';
    }

    public function requiredParams(): array
    {
        return ['fileName', 'contentFile', 'testSetId'];
    }

    protected function buildBodyContents(DOMDocument $doc, DOMElement $operationEl, array $params): void
    {
        $this->assertBase64($params['contentFile'], 'contentFile');
        $this->appendText($doc, $operationEl, 'fileName', $params['fileName']);
        $this->appendText($doc, $operationEl, 'contentFile', $params['contentFile']);
        $this->appendText($doc, $operationEl, 'testSetId', $params['testSetId']);
    }
}
