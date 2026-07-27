<?php

namespace App\Http\Controllers\ElectronicInvoicing;

use App\Http\Controllers\Controller;
use App\Models\CompanyFiscalProfile;
use App\Models\DianSoftwareCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Singleton-per-environment CRUD for `DianSoftwareCredential`.
 *
 * NEVER returns the literal `software_pin_secret_ref` content: the value is
 * already marked as `$hidden` in the model so all JSON outputs in this
 * controller naturally omit it. The endpoint accepts a `software_pin_secret_ref`
 * pointer string (e.g. `ref:hab/pin` or `env:DIAN_SOFTWARE_PIN_HAB`) and
 * stores only the reference. Resolving the actual PIN happens server-side
 * via `SecretManagerInterface`.
 */
class SoftwareCredentialController extends Controller
{
    use FiscalEnvironmentResolver;

    public function show(Request $request): JsonResponse
    {
        $environment = $this->resolveEnvironment($request, false);
        $credential = DianSoftwareCredential::query()
            ->where('environment', $environment)
            ->orderBy('id')
            ->first();

        return response()->json([
            'environment' => $environment,
            'credential' => $credential,
            'pin_reference_configured' => $credential
                ? $this->isReferenceConfigured($credential)
                : false,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $environment = $this->resolveEnvironment($request, true);

        $data = $request->validate([
            'company_id' => 'required|integer|exists:company_fiscal_profiles,id',
            'software_id' => 'required|string|regex:/^[0-9a-fA-F\-]{36}$/',
            'software_pin_secret_ref' => 'required|string|max:255',
            'test_set_id' => 'nullable|string|max:64',
            'production_url' => 'nullable|url|max:255',
            'habilitacion_url' => 'nullable|url|max:255',
        ]);

        $company = CompanyFiscalProfile::find($data['company_id']);
        if (!$company || $company->environment !== $environment) {
            throw ValidationException::withMessages([
                'company_id' => ['Company does not belong to the requested environment.'],
            ]);
        }

        // Reject literal PINs that look like raw secrets (>=8 chars and no ref prefix).
        $ref = trim($data['software_pin_secret_ref']);
        if (!preg_match('/^(ref:|env:|op:|sops:)/i', $ref) && strlen($ref) >= 8) {
            throw ValidationException::withMessages([
                'software_pin_secret_ref' => ['Use a secret reference (ref:..., env:..., op://..., sops://...). Literal PIN values are not accepted.'],
            ]);
        }

        $data['environment'] = $environment;

        $credential = DianSoftwareCredential::updateOrCreate(
            [
                'company_id' => $company->id,
                'environment' => $environment,
            ],
            $data
        );

        return response()->json([
            'environment' => $environment,
            'credential' => $credential->fresh(),
            'pin_reference_configured' => $this->isReferenceConfigured($credential),
        ], 200);
    }

    private function isReferenceConfigured(DianSoftwareCredential $credential): bool
    {
        $value = $credential->getAttribute('software_pin_secret_ref');
        return is_string($value) && trim($value) !== '';
    }
}
