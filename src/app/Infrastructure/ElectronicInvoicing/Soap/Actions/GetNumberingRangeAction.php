<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap\Actions;

use DOMDocument;
use DOMElement;

/**
 * GetNumberingRange(accountCode, accountCodeT, softwareCode): retrieve the
 * authorized numbering ranges (and the per-resolution technical key) for the
 * billing entity.
 *
 *  - accountCode  : NIT del facturador (sin DV).
 *  - accountCodeT : NIT del proveedor tecnologico (sin DV).
 *  - softwareCode : DianSoftwareCredential.software_id (UUID).
 */
final class GetNumberingRangeAction extends AbstractDianAction
{
    public function operationName(): string
    {
        return 'GetNumberingRange';
    }

    public function requiredParams(): array
    {
        return ['accountCode', 'accountCodeT', 'softwareCode'];
    }

    protected function buildBodyContents(DOMDocument $doc, DOMElement $operationEl, array $params): void
    {
        $this->appendText($doc, $operationEl, 'accountCode', $params['accountCode']);
        $this->appendText($doc, $operationEl, 'accountCodeT', $params['accountCodeT']);
        $this->appendText($doc, $operationEl, 'softwareCode', $params['softwareCode']);
    }
}
