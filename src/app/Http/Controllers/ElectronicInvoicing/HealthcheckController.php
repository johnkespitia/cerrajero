<?php

namespace App\Http\Controllers\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Domain\ElectronicInvoicing\Ports\SecretManagerInterface;
use App\Http\Controllers\Controller;
use App\Infrastructure\ElectronicInvoicing\Secrets\ConfigSecretManager;
use App\Infrastructure\ElectronicInvoicing\Secrets\SecretUnavailableException;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\DianSoftwareCredential;
use App\Models\FiscalCertificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only configuration health summary for the fiscal admin UI.
 *
 * Every flag answers a single question that the kiosk emission flow asks
 * before producing a document: "do we have the minimum config to leave
 * `electronic_document_error` behind?". The endpoint never returns
 * certificate material nor literal PIN values.
 */
class HealthcheckController extends Controller
{
    use FiscalEnvironmentResolver;

    public function show(Request $request): JsonResponse
    {
        $environment = $this->resolveEnvironment($request, false);

        $profile = CompanyFiscalProfile::query()
            ->where('environment', $environment)
            ->where('active', true)
            ->orderBy('id')
            ->first();

        $credential = $profile
            ? DianSoftwareCredential::query()
                ->where('company_id', $profile->id)
                ->where('environment', $environment)
                ->first()
            : null;

        $certificate = $profile
            ? $profile->activeCertificate($environment)
            : null;

        $resolutionsByType = [];
        foreach (DocumentType::all() as $type) {
            $hasActive = $profile
                ? DianResolution::query()
                    ->where('company_id', $profile->id)
                    ->where('environment', $environment)
                    ->where('document_type', $type)
                    ->where('active', true)
                    ->exists()
                : false;
            $resolutionsByType[$type] = $hasActive;
        }

        $pinResolved = false;
        if ($credential !== null) {
            $pinResolved = $this->tryResolvePin($credential);
        }

        $configuredEnvironment = $this->defaultEnvironment();
        $eiEnabled = (bool) $this->configValue('electronic-invoicing.enabled', false);

        return response()->json([
            'environment' => $environment,
            'configured_environment' => $configuredEnvironment,
            'electronic_invoicing_enabled' => $eiEnabled,
            'production_writes_allowed' => $this->productionWritesAllowed(),
            'company_profile' => [
                'configured' => $profile !== null,
                'id' => $profile ? (int) $profile->id : null,
                'active' => $profile ? (bool) $profile->active : false,
                'legal_name' => $profile ? (string) $profile->legal_name : null,
                'nit' => $profile ? (string) $profile->nit : null,
            ],
            'software_credential' => [
                'configured' => $credential !== null,
                'pin_reference_present' => $credential !== null && !empty($credential->getAttribute('software_pin_secret_ref')),
                'pin_resolvable' => $pinResolved,
                'software_id' => $credential ? (string) $credential->software_id : null,
            ],
            'certificate' => [
                'configured' => $certificate !== null,
                'subject_cn' => $certificate ? (string) $certificate->subject_cn : null,
                'days_to_expiry' => $certificate ? $certificate->daysToExpiry() : null,
            ],
            'resolutions' => $resolutionsByType,
            'ready_to_emit' => $profile !== null
                && $credential !== null
                && (
                    !empty($resolutionsByType[DocumentType::FEV])
                    || !empty($resolutionsByType[DocumentType::DEE_POS])
                ),
            'available_environments' => [
                FiscalEnvironment::HABILITACION,
                FiscalEnvironment::PRODUCTION,
            ],
        ]);
    }

    private function tryResolvePin(DianSoftwareCredential $credential): bool
    {
        $ref = $credential->getAttribute('software_pin_secret_ref');
        if (!is_string($ref) || $ref === '') {
            return false;
        }
        try {
            $manager = $this->resolveSecretManager();
            $value = $manager->get($ref);
            return is_string($value) && $value !== '';
        } catch (SecretUnavailableException $e) {
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function resolveSecretManager(): SecretManagerInterface
    {
        if (function_exists('app')) {
            try {
                $resolved = app(SecretManagerInterface::class);
                if ($resolved instanceof SecretManagerInterface) {
                    return $resolved;
                }
            } catch (\Throwable $e) {
                // Fall through to default below.
            }
        }
        return new ConfigSecretManager();
    }
}
