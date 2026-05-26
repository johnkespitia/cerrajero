<?php

namespace App\Http\Controllers\ElectronicInvoicing;

use App\Http\Controllers\Controller;
use App\Services\ElectronicInvoicing\Exceptions\LegacyPtImportException;
use App\Services\ElectronicInvoicing\LegacyPt\LegacyPtImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class LegacyPtImportController extends Controller
{
    public function __construct(private readonly LegacyPtImporter $importer)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|integer',
            'source_pt_name' => 'required|string|max:100',
            'environment' => 'sometimes|string|in:habilitacion,production',
            'correlation_id' => 'sometimes|string|max:64',
            'documents' => 'required|array|min:1',
            'documents.*.legacy_pt_id' => 'required|string|max:100',
            'documents.*.document_type' => 'required|string',
            'documents.*.dian_number' => 'required|string|max:60',
            'documents.*.cufe_cude' => 'required|string|max:96',
            'documents.*.issue_date' => 'required|string',
            'documents.*.total' => 'required',
            'documents.*.currency_code' => 'sometimes|string|size:3',
            'documents.*.xml_base64' => 'sometimes|nullable|string',
            'documents.*.pdf_base64' => 'sometimes|nullable|string',
            'documents.*.pdf_path' => 'sometimes|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'electronic_document_error' => [
                    'code' => 'legacy_pt_import_payload_invalid',
                    'message' => 'The legacy PT import payload failed validation.',
                    'errors' => $validator->errors()->all(),
                ],
            ], 422);
        }

        $payload = $validator->validated();
        $payload['imported_by'] = $request->user()?->id;

        try {
            $import = $this->importer->import($payload);
        } catch (LegacyPtImportException $e) {
            return response()->json([
                'electronic_document_error' => [
                    'code' => $e->errorCode(),
                    'message' => $e->getMessage(),
                ],
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'electronic_document_error' => [
                    'code' => 'legacy_pt_import_failed',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }

        return response()->json([
            'legacy_pt_import' => [
                'id' => $import->id,
                'company_id' => $import->company_id,
                'source_pt_name' => $import->source_pt_name,
                'status' => $import->status,
                'total_documents' => $import->total_documents,
                'consistent_count' => $import->consistent_count,
                'inconsistent_count' => $import->inconsistent_count,
                'missing_count' => $import->missing_count,
                'started_at' => optional($import->started_at)->toIso8601String(),
                'finished_at' => optional($import->finished_at)->toIso8601String(),
                'report' => $import->report,
            ],
        ], 201);
    }
}
