<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap\Actions;

use DOMDocument;
use DOMElement;

/**
 * SendBillSync(fileName, contentFile): synchronous submission of a signed UBL
 * wrapped in a base64-encoded ZIP.
 *
 * Used for FEV / DEE POS / NC / ND in both HAB and PROD environments.
 */
final class SendBillSyncAction extends AbstractDianAction
{
    public function operationName(): string
    {
        return 'SendBillSync';
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
