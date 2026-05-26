<?php

namespace App\Services\ElectronicInvoicing\Exceptions;

use RuntimeException;

class LegacyPtImportException extends RuntimeException
{
    public const CODE_MISSING_COMPANY = 'company_missing';
    public const CODE_COMPANY_NOT_FOUND = 'company_not_found';
    public const CODE_EMPTY_BUNDLE = 'empty_bundle';

    private string $errorCode;

    public function __construct(string $code, string $message)
    {
        parent::__construct($message);
        $this->errorCode = $code;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public static function missingCompany(): self
    {
        return new self(self::CODE_MISSING_COMPANY, 'Legacy PT import payload is missing the `company_id` field.');
    }

    public static function companyNotFound(int $companyId): self
    {
        return new self(self::CODE_COMPANY_NOT_FOUND, sprintf('Company fiscal profile #%d was not found.', $companyId));
    }

    public static function emptyBundle(): self
    {
        return new self(self::CODE_EMPTY_BUNDLE, 'Legacy PT import bundle contains no documents.');
    }
}
