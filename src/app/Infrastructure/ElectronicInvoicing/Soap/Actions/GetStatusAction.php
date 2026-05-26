<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap\Actions;

use DOMDocument;
use DOMElement;

/**
 * GetStatus(trackId): poll status of a previously submitted document.
 */
final class GetStatusAction extends AbstractDianAction
{
    public function operationName(): string
    {
        return 'GetStatus';
    }

    public function requiredParams(): array
    {
        return ['trackId'];
    }

    protected function buildBodyContents(DOMDocument $doc, DOMElement $operationEl, array $params): void
    {
        $this->appendText($doc, $operationEl, 'trackId', $params['trackId']);
    }
}
