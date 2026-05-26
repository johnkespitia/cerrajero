<?php

namespace App\Http\Controllers\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Shared helper for ElectronicInvoicing admin controllers.
 *
 * The fiscal admin endpoints are intentionally restricted to `habilitacion`
 * by default. Writes against `production` must be unlocked explicitly via
 * `config('electronic-invoicing.allow_production_writes')` so a misconfigured
 * request cannot accidentally rotate the live DIAN credentials.
 */
trait FiscalEnvironmentResolver
{
    protected function resolveEnvironment(Request $request, bool $forWrite = false): string
    {
        $env = (string) $request->input('environment', $this->defaultEnvironment());
        if (!FiscalEnvironment::isValid($env)) {
            throw ValidationException::withMessages([
                'environment' => ['environment must be one of: habilitacion, production.'],
            ]);
        }
        if ($forWrite && $env === FiscalEnvironment::PRODUCTION && !$this->productionWritesAllowed()) {
            throw ValidationException::withMessages([
                'environment' => ['Production writes are disabled. Set ELECTRONIC_INVOICING_ALLOW_PRODUCTION_WRITES=true to unlock.'],
            ]);
        }
        return $env;
    }

    protected function defaultEnvironment(): string
    {
        $value = $this->configValue('electronic-invoicing.environment');
        return is_string($value) && $value !== ''
            ? $value
            : FiscalEnvironment::HABILITACION;
    }

    protected function productionWritesAllowed(): bool
    {
        $value = $this->configValue('electronic-invoicing.allow_production_writes', false);
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }
        return (bool) $value;
    }

    protected function configValue(string $key, $default = null)
    {
        if (function_exists('config')) {
            return config($key, $default);
        }
        return $default;
    }
}
