<?php

namespace App\Domain\ElectronicInvoicing\Ports;

/**
 * Builds the canonical UBL 2.1 XML for a DIAN electronic document.
 *
 * Concrete implementations live in
 * App\Infrastructure\ElectronicInvoicing\Ubl\* (next slice).
 * The dominio depende del puerto, no del adapter.
 */
interface UblBuilderInterface
{
    /**
     * Build the unsigned UBL XML body for the given document payload.
     *
     * @param array $payload Canonical EmissionRequest payload assembled by
     *                       DocumentAssembler.
     * @return string Unsigned UBL 2.1 XML body.
     */
    public function build(array $payload): string;

    /**
     * Document type this builder is responsible for (fev, dee_pos, nc, nd).
     */
    public function documentType(): string;

    /**
     * Anexo Tecnico version this builder targets (e.g. "1.9").
     */
    public function anexoVersion(): string;
}
