<?php

namespace App\Http\Controllers\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Http\Controllers\Controller;
use App\Models\CompanyFiscalProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Singleton-per-environment CRUD for `CompanyFiscalProfile`.
 *
 * The fiscal admin slice only supports one CompanyFiscalProfile per
 * environment. The endpoint returns `null` when nothing has been configured
 * yet (so the frontend can render an empty form) and uses `updateOrCreate`
 * with `(environment, nit)` as the natural key on writes.
 */
class CompanyProfileController extends Controller
{
    use FiscalEnvironmentResolver;

    public function show(Request $request): JsonResponse
    {
        $environment = $this->resolveEnvironment($request, false);
        $profile = CompanyFiscalProfile::query()
            ->where('environment', $environment)
            ->orderByDesc('active')
            ->orderBy('id')
            ->first();

        return response()->json([
            'environment' => $environment,
            'profile' => $profile,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $environment = $this->resolveEnvironment($request, true);

        $data = $request->validate([
            'legal_name' => 'required|string|max:200',
            'trade_name' => 'nullable|string|max:200',
            'nit' => 'required|string|max:20',
            'dv' => 'required|integer|min:0|max:9',
            'tax_regime_code' => 'required|string|max:4',
            'tax_responsibilities' => 'nullable|array',
            'tax_responsibilities.*' => 'string|max:20',
            'economic_activity_code' => 'nullable|string|max:4',
            'address_line' => 'required|string|max:255',
            'city_code_dian' => 'required|string|max:5',
            'country_code' => 'nullable|string|size:2',
            'email' => 'required|email|max:200',
            'phone' => 'nullable|string|max:30',
            'migration_cutoff_date' => 'nullable|date',
            'legacy_pt_name' => 'nullable|string|max:100',
            'active' => 'nullable|boolean',
        ]);

        $data['environment'] = $environment;
        $data['country_code'] = strtoupper($data['country_code'] ?? 'CO');
        $active = (bool) ($data['active'] ?? true);
        $data['active'] = $active;
        if ($active) {
            $data['activated_at'] = now();
        }

        $profile = CompanyFiscalProfile::updateOrCreate(
            [
                'environment' => $environment,
                'nit' => $data['nit'],
            ],
            $data
        );

        // Deactivate sibling profiles in the same environment if this one is active.
        if ($active) {
            CompanyFiscalProfile::query()
                ->where('environment', $environment)
                ->where('id', '!=', $profile->id)
                ->update(['active' => false]);
        }

        return response()->json([
            'environment' => $environment,
            'profile' => $profile->fresh(),
        ], 200);
    }
}
