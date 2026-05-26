<?php

namespace App\Domain\ElectronicInvoicing\Ports;

interface QrEncoderInterface
{
    /**
     * Build the DIAN QR URL that depends on the CUFE/CUDE and the
     * environment-specific QR base URL.
     */
    public function buildUrl(string $cufeOrCude, string $environment): string;

    /**
     * Generate a PNG (binary) for the QR encoding of the given URL.
     */
    public function renderPng(string $url): string;
}
