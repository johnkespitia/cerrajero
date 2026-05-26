<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap\Exceptions;

use RuntimeException;

/**
 * Thrown when the client is asked to send a real DIAN request but the
 * WS-Security signing material (X.509 certificate + RSA private key) is not
 * available in the current environment.
 *
 * The message stays generic: it MUST NOT echo certificate PEM, private key,
 * password or PIN.
 */
final class DianSoapSigningUnavailableException extends RuntimeException
{
}
