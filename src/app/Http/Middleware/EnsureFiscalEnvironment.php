<?php

namespace App\Http\Middleware;

use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\FiscalCertificate;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Guard against PROD-vs-HAB environment mismatches (R-18).
 *
 * The middleware asserts that the configured environment
 * (`electronic-invoicing.environment`) matches every fiscal artifact
 * that any emission request may touch:
 *
 *   - `CompanyFiscalProfile.environment`   (active profile)
 *   - `FiscalCertificate.environment`      (active certificate)
 *   - `DianResolution.environment`         (active resolutions)
 *
 * `DianSoftwareCredential` is intentionally *not* checked here: it
 * lacks an `active` flag and is queried by `environment` at runtime,
 * so a mismatch surfaces as `software_credentials_missing` in
 * `CutoverReadinessService` rather than a 409 from this middleware.
 *
 * Any mismatch is fatal: the request never reaches the controller and
 * a `409 Conflict` is returned with a structured `fiscal_environment_mismatch`
 * payload so the caller (and ops) can correct it.
 *
 * The check is intentionally cheap: a handful of `where`/`exists`
 * queries scoped to active records only. It is skipped when the
 * facturacion electronica is globally disabled to avoid blocking
 * normal hospitality traffic during development:
 *
 *   - `electronic-invoicing.enabled = false` -> pass through.
 *
 * Tests live at:
 *   `tests/Feature/ElectronicInvoicing/EnsureFiscalEnvironmentTest.php`.
 */
class EnsureFiscalEnvironment
{
    public function handle(Request $request, Closure $next)
    {
        $enabled = (bool) (function_exists('config') ? config('electronic-invoicing.enabled', false) : false);
        if (! $enabled) {
            return $next($request);
        }

        $configured = (string) config('electronic-invoicing.environment', FiscalEnvironment::HABILITACION);
        $blockers = $this->collectMismatches($configured);
        if ($blockers !== []) {
            return $this->mismatchResponse($configured, $blockers);
        }

        return $next($request);
    }

    /**
     * @return array<int, array{kind: string, expected: string, actual: string, id: int}>
     */
    private function collectMismatches(string $configured): array
    {
        $mismatches = [];

        $company = CompanyFiscalProfile::query()->where('active', true)->first();
        if ($company !== null && (string) $company->environment !== $configured) {
            $mismatches[] = $this->mismatch('company_fiscal_profile', $configured, $company);
        }
        $certificate = FiscalCertificate::query()->where('active', true)->first();
        if ($certificate !== null && (string) $certificate->environment !== $configured) {
            $mismatches[] = $this->mismatch('fiscal_certificate', $configured, $certificate);
        }
        $resolution = DianResolution::query()->where('active', true)->first();
        if ($resolution !== null && (string) $resolution->environment !== $configured) {
            $mismatches[] = $this->mismatch('dian_resolution', $configured, $resolution);
        }

        return $mismatches;
    }

    private function mismatch(string $kind, string $expected, $model): array
    {
        return [
            'kind' => $kind,
            'expected' => $expected,
            'actual' => (string) $model->environment,
            'id' => (int) $model->id,
        ];
    }

    private function mismatchResponse(string $configured, array $blockers): JsonResponse
    {
        return response()->json([
            'error_code' => 'fiscal_environment_mismatch',
            'message' => sprintf(
                'Configured environment is [%s] but at least one active fiscal artifact does not match.',
                $configured
            ),
            'configured_environment' => $configured,
            'mismatches' => $blockers,
        ], 409);
    }
}
