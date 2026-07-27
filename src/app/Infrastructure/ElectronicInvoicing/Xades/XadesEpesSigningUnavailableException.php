<?php

namespace App\Infrastructure\ElectronicInvoicing\Xades;

use RuntimeException;

/**
 * Thrown when XAdES-EPES signing cannot proceed in the current environment.
 *
 * The message MUST stay generic (no certificate, no key material, no password,
 * no PIN). Sensitive details belong only in audit metadata kept by the caller.
 */
final class XadesEpesSigningUnavailableException extends RuntimeException
{
}
