<?php

namespace App\Infrastructure\ElectronicInvoicing\Cufe;

use InvalidArgumentException;

/**
 * Computes the SoftwareSecurityCode that DIAN embeds in
 * sts:DianExtensions/sts:SoftwareSecurityCode of the signed UBL.
 *
 * Per the spec: software_security_code = SHA-384(idSoftware + identificador + pin).
 *
 * "identificador" carries the document number (NumFac / NumDoc) for per-document
 * codes. The same calculator is reused for the global software-level code by
 * passing an empty identifier; the formula is the same concatenation+SHA-384.
 *
 * The calculator never persists or logs any of its inputs.
 */
final class SoftwareSecurityCodeCalculator
{
    /**
     * @param string $softwareId DianSoftwareCredential.software_id (UUID-like, opaque to us).
     * @param string $identifier Document number when scope=per-document; "" for software-wide.
     * @param string $pin        DIAN software PIN, resolved via SecretManagerInterface.
     * @return string SHA-384 hex (96 chars, lowercase).
     */
    public function calculate(string $softwareId, string $identifier, string $pin): string
    {
        if ($softwareId === '') {
            throw new InvalidArgumentException('softwareId must not be empty.');
        }
        if ($pin === '') {
            throw new InvalidArgumentException('pin must not be empty.');
        }

        return hash('sha384', $softwareId . $identifier . $pin);
    }
}
