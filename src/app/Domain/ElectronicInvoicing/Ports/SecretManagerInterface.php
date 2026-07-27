<?php

namespace App\Domain\ElectronicInvoicing\Ports;

/**
 * Resolves secrets (PINs, certificate passwords) by reference, without ever
 * storing the literal value in DB or repo.
 */
interface SecretManagerInterface
{
    public function get(string $ref): string;
}
