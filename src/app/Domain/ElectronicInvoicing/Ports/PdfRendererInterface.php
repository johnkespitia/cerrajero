<?php

namespace App\Domain\ElectronicInvoicing\Ports;

interface PdfRendererInterface
{
    /**
     * Render the "representacion grafica" of an electronic document. The PDF
     * must embed the DIAN QR + CUFE/CUDE + numero + leyenda fiscal.
     *
     * @return string Binary PDF.
     */
    public function render(array $payload): string;
}
