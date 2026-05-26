<?php

namespace App\Http\Controllers\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Services\ElectronicInvoicing\CreditDebitNoteService;
use App\Services\ElectronicInvoicing\Exceptions\CreditDebitNoteException;
use App\Services\ElectronicInvoicing\Exceptions\CreditDebitNoteInvalidPayloadException;
use App\Services\ElectronicInvoicing\Exceptions\CreditDebitNoteUnavailableException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * HTTP endpoints to emit NC (Nota Crédito) and ND (Nota Débito) referencing
 * an existing FEV / DEE POS.
 *
 *  POST /api/electronic-invoicing/documents/{id}/credit-note
 *  POST /api/electronic-invoicing/documents/{id}/debit-note
 *
 * Both endpoints accept the same JSON shape:
 *  {
 *    "lines": [{ "description", "quantity", "unit_price", "line_total" }, ...],
 *    "totals": { "line_extension_amount", "tax_exclusive_amount", ... } | omitted,
 *    "reason": "Devolución parcial",
 *    "discrepancy_code": "01",            // required for NC
 *    "discrepancy_description": "...",     // optional
 *    "acquirer": { ...EMPTY_ACQUIRER },    // optional when parent has acquirer_id
 *  }
 *
 * Responses:
 *  200 { electronic_document: { ... } }
 *  422 { message, electronic_document_error: { code, message } }
 *  409 { message, electronic_document_error: { code, message } }
 *  502 { message, electronic_document_error: { code, message } }
 */
class CreditDebitNoteController extends Controller
{
    /** @var CreditDebitNoteService */
    private $service;

    public function __construct(CreditDebitNoteService $service)
    {
        $this->service = $service;
    }

    public function storeCreditNote(Request $request, int $id): JsonResponse
    {
        return $this->emit(DocumentType::NC, $id, $request);
    }

    public function storeDebitNote(Request $request, int $id): JsonResponse
    {
        return $this->emit(DocumentType::ND, $id, $request);
    }

    private function emit(string $documentType, int $parentId, Request $request): JsonResponse
    {
        $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string|max:255',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.unit_price' => 'required|numeric|gte:0',
            'lines.*.line_total' => 'nullable|numeric|gt:0',
            'totals' => 'nullable|array',
            'reason' => 'nullable|string|max:500',
            'discrepancy_code' => 'nullable|string|max:5',
            'discrepancy_description' => 'nullable|string|max:500',
            'acquirer' => 'nullable|array',
            'acquirer.document_type' => 'required_with:acquirer|string|max:10',
            'acquirer.document_number' => 'required_with:acquirer|string|max:20',
            'acquirer.legal_name' => 'required_with:acquirer|string|max:200',
        ]);

        try {
            $document = $this->service->emit($documentType, $parentId, $request->all());
        } catch (CreditDebitNoteInvalidPayloadException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'electronic_document_error' => [
                    'code' => $e->emissionCode(),
                    'message' => $e->getMessage(),
                ],
            ], 422);
        } catch (CreditDebitNoteUnavailableException $e) {
            $status = $e->emissionCode() === CreditDebitNoteUnavailableException::CODE_EMITTER_FAILURE
                ? 502
                : 409;
            return response()->json([
                'message' => $e->getMessage(),
                'electronic_document_error' => [
                    'code' => $e->emissionCode(),
                    'message' => $e->getMessage(),
                ],
            ], $status);
        } catch (CreditDebitNoteException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'electronic_document_error' => [
                    'code' => $e->emissionCode(),
                    'message' => $e->getMessage(),
                ],
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Unexpected error while emitting the credit/debit note.',
                'electronic_document_error' => [
                    'code' => CreditDebitNoteUnavailableException::CODE_EMITTER_FAILURE,
                    'message' => $e->getMessage(),
                ],
            ], 502);
        }

        return response()->json([
            'electronic_document' => $this->service->summarise($document),
        ]);
    }
}
