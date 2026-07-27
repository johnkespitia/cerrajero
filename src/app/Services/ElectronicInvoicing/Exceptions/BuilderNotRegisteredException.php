<?php

namespace App\Services\ElectronicInvoicing\Exceptions;

use RuntimeException;

/**
 * Raised when DocumentEmitter is asked to emit a document type for which no
 * UblBuilderInterface is wired in the UblBuilderRegistry.
 */
final class BuilderNotRegisteredException extends RuntimeException
{
    public static function for(string $documentType): self
    {
        return new self(sprintf('No UBL builder registered for document type "%s".', $documentType));
    }
}
