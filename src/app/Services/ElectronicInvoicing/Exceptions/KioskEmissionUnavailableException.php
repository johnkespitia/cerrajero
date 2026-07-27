<?php

namespace App\Services\ElectronicInvoicing\Exceptions;

use Throwable;

/**
 * Raised when the kiosk emission cannot proceed because of fiscal
 * configuration gaps that are not the cashier's fault: no active
 * CompanyFiscalProfile, no active DianResolution for the requested
 * environment, unresolvable software PIN, etc.
 *
 * The controller MUST NOT crash caja in non-production environments when this
 * is thrown. Instead it should attach a structured `electronic_document_error`
 * to the response and continue committing the KioskInvoice transaction.
 */
final class KioskEmissionUnavailableException extends KioskEmissionException
{
    public const CODE_DISABLED = 'electronic_invoicing_disabled';
    public const CODE_FISCAL_PROFILE_MISSING = 'fiscal_profile_missing';
    public const CODE_RESOLUTION_MISSING = 'resolution_missing';
    public const CODE_RESOLUTION_EXHAUSTED = 'resolution_exhausted';
    public const CODE_RESOLUTION_EXPIRED = 'resolution_expired';
    public const CODE_RESOLUTION_NOT_YET_VALID = 'resolution_not_yet_valid';
    public const CODE_SOFTWARE_CREDENTIAL_MISSING = 'software_credential_missing';
    public const CODE_SOFTWARE_PIN_UNRESOLVABLE = 'software_pin_unresolvable';
    public const CODE_EMITTER_FAILURE = 'emitter_failure';

    public static function disabled(): self
    {
        return new self(self::CODE_DISABLED, 'Electronic invoicing is disabled for this environment.');
    }

    public static function fiscalProfileMissing(string $environment): self
    {
        return new self(
            self::CODE_FISCAL_PROFILE_MISSING,
            sprintf('No active CompanyFiscalProfile for environment "%s".', $environment)
        );
    }

    public static function resolutionMissing(string $environment, string $documentType): self
    {
        return new self(
            self::CODE_RESOLUTION_MISSING,
            sprintf('No active DianResolution for environment "%s" and type "%s".', $environment, $documentType)
        );
    }

    public static function resolutionExhausted(): self
    {
        return new self(self::CODE_RESOLUTION_EXHAUSTED, 'The active DianResolution has no available numbers.');
    }

    public static function resolutionExpired(): self
    {
        return new self(self::CODE_RESOLUTION_EXPIRED, 'The active DianResolution is expired.');
    }

    public static function resolutionNotYetValid(): self
    {
        return new self(self::CODE_RESOLUTION_NOT_YET_VALID, 'The active DianResolution is not yet valid.');
    }

    public static function softwareCredentialMissing(string $environment): self
    {
        return new self(
            self::CODE_SOFTWARE_CREDENTIAL_MISSING,
            sprintf('No DianSoftwareCredential for environment "%s".', $environment)
        );
    }

    public static function softwarePinUnresolvable(?Throwable $previous = null): self
    {
        return new self(
            self::CODE_SOFTWARE_PIN_UNRESOLVABLE,
            'DIAN software PIN reference cannot be resolved in this environment.',
            $previous
        );
    }

    public static function emitterFailure(string $message, ?Throwable $previous = null): self
    {
        return new self(self::CODE_EMITTER_FAILURE, $message, $previous);
    }
}
