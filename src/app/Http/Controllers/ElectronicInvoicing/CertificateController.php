<?php

namespace App\Http\Controllers\ElectronicInvoicing;

use App\Http\Controllers\Controller;
use App\Models\FiscalCertificate;
use App\Services\ElectronicInvoicing\Certificate\FiscalCertificateService;
use App\Services\ElectronicInvoicing\Exceptions\InvalidCertificateException;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Admin endpoints to manage `FiscalCertificate` records.
 *
 *   POST   /api/electronic-invoicing/admin/certificates                  upload
 *   GET    /api/electronic-invoicing/admin/certificates                  list (filtered by env)
 *   POST   /api/electronic-invoicing/admin/certificates/{id}/activate    activate
 *   DELETE /api/electronic-invoicing/admin/certificates/{id}             delete
 *
 * All endpoints require `electronic_invoicing.admin` permission (cabled
 * by route middleware). The serialised payload never includes the
 * `.p12` bytes, the password or the password reference.
 */
class CertificateController extends Controller
{
    use FiscalEnvironmentResolver;

    public function __construct(private readonly FiscalCertificateService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $environment = $this->resolveEnvironment($request, false);
        $companyId = (int) $request->input('company_id', 0);
        if ($companyId <= 0) {
            return response()->json([
                'electronic_document_error' => [
                    'code' => 'company_id_required',
                    'message' => 'company_id query parameter is required.',
                ],
            ], 422);
        }

        $items = $this->service->list($companyId, $environment);

        return response()->json([
            'environment' => $environment,
            'certificates' => array_map([$this, 'serialize'], $items),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $environment = $this->resolveEnvironment($request, true);

        $validator = Validator::make($request->all(), [
            'company_id' => 'required|integer',
            'password' => 'required|string|min:1',
            'p12' => 'required_without:p12_base64|file|mimetypes:application/x-pkcs12,application/octet-stream',
            'p12_base64' => 'required_without:p12|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'electronic_document_error' => [
                    'code' => 'certificate_payload_invalid',
                    'message' => 'Certificate upload payload failed validation.',
                    'errors' => $validator->errors()->all(),
                ],
            ], 422);
        }

        $bytes = '';
        if ($request->hasFile('p12')) {
            $bytes = (string) file_get_contents($request->file('p12')->getRealPath());
        } else {
            $decoded = base64_decode((string) $request->input('p12_base64'), true);
            $bytes = $decoded === false ? '' : $decoded;
        }

        try {
            $certificate = $this->service->upload(
                (int) $request->input('company_id'),
                $environment,
                $bytes,
                (string) $request->input('password')
            );
        } catch (InvalidCertificateException $e) {
            return response()->json([
                'electronic_document_error' => [
                    'code' => $e->errorCode(),
                    'message' => $e->getMessage(),
                ],
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'electronic_document_error' => [
                    'code' => 'company_not_found',
                    'message' => 'Company fiscal profile not found.',
                ],
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'electronic_document_error' => [
                    'code' => 'certificate_upload_failed',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }

        return response()->json([
            'certificate' => $this->serialize($certificate),
        ], 201);
    }

    public function activate(int $id): JsonResponse
    {
        try {
            $certificate = $this->service->activate($id);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'electronic_document_error' => [
                    'code' => 'certificate_not_found',
                    'message' => 'Certificate not found.',
                ],
            ], 404);
        } catch (InvalidCertificateException $e) {
            return response()->json([
                'electronic_document_error' => [
                    'code' => $e->errorCode(),
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }

        return response()->json(['certificate' => $this->serialize($certificate)]);
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->delete($id);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'electronic_document_error' => [
                    'code' => 'certificate_not_found',
                    'message' => 'Certificate not found.',
                ],
            ], 404);
        } catch (DomainException $e) {
            return response()->json([
                'electronic_document_error' => [
                    'code' => 'certificate_active',
                    'message' => $e->getMessage(),
                ],
            ], 409);
        }

        return response()->json(['deleted' => true]);
    }

    private function serialize(FiscalCertificate $certificate): array
    {
        return [
            'id' => $certificate->id,
            'company_id' => $certificate->company_id,
            'environment' => $certificate->environment,
            'subject_cn' => $certificate->subject_cn,
            'issuer_cn' => $certificate->issuer_cn,
            'serial_number' => $certificate->serial_number,
            'not_before' => optional($certificate->not_before)->toIso8601String(),
            'not_after' => optional($certificate->not_after)->toIso8601String(),
            'fingerprint_sha256' => $certificate->fingerprint_sha256,
            'active' => (bool) $certificate->active,
            'loaded_at' => optional($certificate->loaded_at)->toIso8601String(),
            'days_to_expiry' => $certificate->daysToExpiry(),
        ];
    }
}
