<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap\Exceptions;

use RuntimeException;

/**
 * Thrown when the underlying HTTP transport cannot reach DIAN (DNS, TLS,
 * timeout, refused connection, ...). The message is intentionally generic and
 * never echoes certificates, tokens, request bodies or PII.
 */
final class DianSoapTransportException extends RuntimeException
{
}
