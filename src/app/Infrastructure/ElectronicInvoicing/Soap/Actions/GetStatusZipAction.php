<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap\Actions;

use DOMDocument;
use DOMElement;

/**
 * GetStatusZip(trackId): poll status of a previously submitted batch (ZIP).
 */
final class GetStatusZipAction extends AbstractDianAction
{
    public function operationName(): string
    {
        return 'GetStatusZip';
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
