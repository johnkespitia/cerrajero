<?php

namespace App\Http\Controllers\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CRUD for `DianResolution`. Resolutions are environment-scoped and DIAN
 * requires `technical_key` for FEV; for DEE POS / NC / ND the key is null.
 *
 * The controller validates:
 *  - document_type ∈ DocumentType::all()
 *  - from_number <= to_number
 *  - current_number ∈ [from_number - 1, to_number]
 *  - technical_key required iff document_type=FEV
 *  - active flag is exclusive per (environment, document_type)
 */
class ResolutionController extends Controller
{
    use FiscalEnvironmentResolver;

    public function index(Request $request): JsonResponse
    {
        $environment = $this->resolveEnvironment($request, false);
        $resolutions = DianResolution::query()
            ->where('environment', $environment)
            ->orderBy('document_type')
            ->orderBy('valid_to', 'desc')
            ->get();

        return response()->json([
            'environment' => $environment,
            'resolutions' => $resolutions,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $environment = $this->resolveEnvironment($request, true);
        $data = $this->validateData($request, $environment);
        $this->assertCompanyEnvironment($data['company_id'], $environment);

        $resolution = DianResolution::create(array_merge($data, ['environment' => $environment]));

        $this->enforceActiveExclusivity($resolution);

        return response()->json([
            'environment' => $environment,
            'resolution' => $resolution->fresh(),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $environment = $this->resolveEnvironment($request, true);
        $resolution = DianResolution::findOrFail($id);
        $data = $this->validateData($request, $environment, $resolution);
        $this->assertCompanyEnvironment($data['company_id'], $environment);

        $resolution->fill(array_merge($data, ['environment' => $environment]));
        $resolution->save();

        $this->enforceActiveExclusivity($resolution);

        return response()->json([
            'environment' => $environment,
            'resolution' => $resolution->fresh(),
        ], 200);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->resolveEnvironment($request, true);
        $resolution = DianResolution::findOrFail($id);
        if ($resolution->current_number > 0) {
            throw ValidationException::withMessages([
                'resolution' => ['Cannot delete a resolution that has already issued documents.'],
            ]);
        }
        $resolution->delete();
        return response()->json(['deleted' => true], 200);
    }

    private function validateData(Request $request, string $environment, ?DianResolution $existing = null): array
    {
        $data = $request->validate([
            'company_id' => 'required|integer|exists:company_fiscal_profiles,id',
            'document_type' => ['required', 'string', Rule::in(DocumentType::all())],
            'prefix' => 'required|string|max:8',
            'resolution_number' => 'required|string|max:50',
            'resolution_date' => 'required|date',
            'from_number' => 'required|integer|min:1',
            'to_number' => 'required|integer|min:1',
            'valid_from' => 'required|date',
            'valid_to' => 'required|date|after_or_equal:valid_from',
            'technical_key' => 'nullable|string|max:100',
            'current_number' => 'nullable|integer|min:0',
            'active' => 'nullable|boolean',
        ]);

        if ($data['from_number'] > $data['to_number']) {
            throw ValidationException::withMessages([
                'to_number' => ['to_number must be greater than or equal to from_number.'],
            ]);
        }

        $current = (int) ($data['current_number'] ?? ($existing->current_number ?? 0));
        if ($current !== 0 && ($current < $data['from_number'] - 1 || $current > $data['to_number'])) {
            throw ValidationException::withMessages([
                'current_number' => ['current_number must fall inside the resolution range.'],
            ]);
        }

        if ($data['document_type'] === DocumentType::FEV) {
            if (empty($data['technical_key'])) {
                throw ValidationException::withMessages([
                    'technical_key' => ['technical_key is required for FEV resolutions.'],
                ]);
            }
        } else {
            $data['technical_key'] = null;
        }

        // Unique per (company, environment, document_type, prefix, resolution_number)
        $duplicate = DianResolution::query()
            ->where('company_id', $data['company_id'])
            ->where('environment', $environment)
            ->where('document_type', $data['document_type'])
            ->where('prefix', $data['prefix'])
            ->where('resolution_number', $data['resolution_number'])
            ->when($existing, function ($query) use ($existing) {
                $query->where('id', '!=', $existing->id);
            })
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'resolution_number' => ['Another resolution with the same prefix/number already exists for this company.'],
            ]);
        }

        $data['current_number'] = $current;
        $data['active'] = (bool) ($data['active'] ?? true);
        return $data;
    }

    private function assertCompanyEnvironment(int $companyId, string $environment): void
    {
        $company = CompanyFiscalProfile::find($companyId);
        if (!$company || $company->environment !== $environment) {
            throw ValidationException::withMessages([
                'company_id' => ['Company does not belong to the requested environment.'],
            ]);
        }
    }

    private function enforceActiveExclusivity(DianResolution $resolution): void
    {
        if (!$resolution->active) {
            return;
        }
        DianResolution::query()
            ->where('environment', $resolution->environment)
            ->where('document_type', $resolution->document_type)
            ->where('company_id', $resolution->company_id)
            ->where('id', '!=', $resolution->id)
            ->update(['active' => false]);
    }
}
