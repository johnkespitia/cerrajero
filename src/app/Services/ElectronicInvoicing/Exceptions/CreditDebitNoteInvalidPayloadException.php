<?php

namespace App\Services\ElectronicInvoicing\Exceptions;

use Throwable;

/**
 * Raised when the NC / ND request payload is structurally incompatible with
 * the emission flow (missing reason, invalid totals, missing acquirer, etc.).
 * The controller must map this to HTTP 422.
 */
final class CreditDebitNoteInvalidPayloadException extends CreditDebitNoteException
{
    public const CODE_PARENT_NOT_FOUND = 'parent_not_found';
    public const CODE_PARENT_TYPE_NOT_REFERENCEABLE = 'parent_type_not_referenceable';
    public const CODE_PARENT_STATUS_NOT_REFERENCEABLE = 'parent_status_not_referenceable';
    public const CODE_PARENT_HAS_NO_CUFE = 'parent_has_no_cufe';
    public const CODE_CROSS_COMPANY = 'cross_company_reference';
    public const CODE_MISSING_ACQUIRER = 'missing_acquirer';
    public const CODE_INVALID_ACQUIRER = 'invalid_acquirer';
    public const CODE_INVALID_LINES = 'invalid_lines';
    public const CODE_INVALID_TOTALS = 'invalid_totals';
    public const CODE_DISCREPANCY_CODE_REQUIRED = 'discrepancy_code_required';
    public const CODE_REASON_REQUIRED = 'reason_required';

    public static function parentNotFound(int $parentId): self
    {
        return new self(
            self::CODE_PARENT_NOT_FOUND,
            sprintf('Parent ElectronicDocument #%d not found.', $parentId)
        );
    }

    public static function parentTypeNotReferenceable(string $type): self
    {
        return new self(
            self::CODE_PARENT_TYPE_NOT_REFERENCEABLE,
            sprintf('Cannot derive NC/ND from a document of type "%s". Only fev and dee_pos can be referenced.', $type)
        );
    }

    public static function parentStatusNotReferenceable(string $status): self
    {
        return new self(
            self::CODE_PARENT_STATUS_NOT_REFERENCEABLE,
            sprintf(
                'Parent document is in status "%s" which is not referenceable. Production should require dian_accepted; this development build also allows ubl_built.',
                $status
            )
        );
    }

    public static function parentHasNoCufe(): self
    {
        return new self(
            self::CODE_PARENT_HAS_NO_CUFE,
            'Parent ElectronicDocument has no CUFE/CUDE yet; emit it first before referencing.'
        );
    }

    public static function crossCompany(): self
    {
        return new self(
            self::CODE_CROSS_COMPANY,
            'NC/ND must belong to the same CompanyFiscalProfile as the parent document.'
        );
    }

    public static function missingAcquirer(): self
    {
        return new self(
            self::CODE_MISSING_ACQUIRER,
            'NC/ND require an acquirer. Provide an acquirer block in the request or ensure the parent document already has acquirer_id.'
        );
    }

    public static function invalidAcquirer(string $field): self
    {
        return new self(
            self::CODE_INVALID_ACQUIRER,
            sprintf('acquirer.%s is missing or invalid.', $field)
        );
    }

    public static function invalidLines(string $message = 'NC/ND require at least one line with positive line_total.'): self
    {
        return new self(self::CODE_INVALID_LINES, $message);
    }

    public static function invalidTotals(string $message): self
    {
        return new self(self::CODE_INVALID_TOTALS, $message);
    }

    public static function discrepancyCodeRequired(): self
    {
        return new self(
            self::CODE_DISCREPANCY_CODE_REQUIRED,
            'discrepancy_code is required for NC (DIAN catalog tabla 13.2.4).'
        );
    }

    public static function reasonRequired(): self
    {
        return new self(
            self::CODE_REASON_REQUIRED,
            'reason is required for ND so the surcharge concept is documented.'
        );
    }
}
