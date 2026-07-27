<?php

namespace App\Domain\ElectronicInvoicing\Ports;

use App\Domain\ElectronicInvoicing\ValueObjects\Cufe;

/**
 * Computes CUFE (FEV) or CUDE (NC, ND, DEE POS, ApplicationResponse) per the
 * DIAN Anexo Tecnico (numerales 11.1.x).
 */
interface CufeCalculatorInterface
{
    /**
     * @param string $documentType One of DocumentType::*.
     * @param array  $fields Source fields per DIAN concatenation order.
     */
    public function calculate(string $documentType, array $fields): Cufe;
}
