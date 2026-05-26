<?php

namespace App\Http\Controllers\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Http\Controllers\Controller;
use App\Models\ElectronicDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only endpoints used by the Electronic Documents dashboard.
 *
 *  GET /api/electronic-invoicing/documents
 *  GET /api/electronic-invoicing/documents/{id}
 *
 * Lists and detail responses intentionally drop XML/PDF/AttachedDocument
 * paths so the dashboard cannot accidentally leak signed payloads. PDF /
 * XML download endpoints will live in a later slice with explicit
 * authorisation checks.
 *
 * Supported filters:
 *  - environment       (defaults to habilitacion)
 *  - document_type     (fev | dee_pos | nc | nd)
 *  - status            (DocumentStatus::*)
 *  - source_type       (kiosk_invoice | reservation | credit_note | debit_note ...)
 *  - source_id
 *  - search            (matches dian_number or cufe_cude)
 *  - per_page          (1..200, defaults to 50)
 *  - page              (>=1)
 *  - order             (created_at | id | issue_date), prefix with `-` for DESC
 */
class DocumentLookupController extends Controller
{
    use FiscalEnvironmentResolver;

    private const DEFAULT_PER_PAGE = 50;
    private const MAX_PER_PAGE = 200;

    public function index(Request $request): JsonResponse
    {
        $environment = $this->resolveEnvironment($request, false);

        $filters = $request->validate([
            'document_type' => 'nullable|string|max:20',
            'status' => 'nullable|string|max:40',
            'source_type' => 'nullable|string|max:40',
            'source_id' => 'nullable|integer',
            'search' => 'nullable|string|max:120',
            'per_page' => 'nullable|integer|min:1|max:' . self::MAX_PER_PAGE,
            'page' => 'nullable|integer|min:1',
            'order' => 'nullable|string|max:30',
        ]);

        $perPage = (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);
        $page = (int) ($filters['page'] ?? 1);
        $order = $filters['order'] ?? '-issue_date';

        $query = ElectronicDocument::query()->where('environment', $environment);

        if (!empty($filters['document_type'])) {
            $type = (string) $filters['document_type'];
            if (!DocumentType::isValid($type)) {
                return response()->json([
                    'message' => 'Invalid document_type filter.',
                    'errors' => ['document_type' => ['Unknown document type "' . $type . '".']],
                ], 422);
            }
            $query->where('document_type', $type);
        }

        if (!empty($filters['status'])) {
            $status = (string) $filters['status'];
            if (!DocumentStatus::isValid($status)) {
                return response()->json([
                    'message' => 'Invalid status filter.',
                    'errors' => ['status' => ['Unknown status "' . $status . '".']],
                ], 422);
            }
            $query->where('status', $status);
        }

        if (!empty($filters['source_type'])) {
            $query->where('source_type', (string) $filters['source_type']);
        }
        if (isset($filters['source_id']) && $filters['source_id'] !== '') {
            $query->where('source_id', (int) $filters['source_id']);
        }
        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('dian_number', 'like', '%' . $search . '%')
                        ->orWhere('cufe_cude', 'like', '%' . $search . '%');
                });
            }
        }

        $this->applyOrder($query, $order);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'environment' => $environment,
            'documents' => array_map(
                fn ($doc) => $this->toSummary($doc),
                $paginator->items()
            ),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $environment = $this->resolveEnvironment($request, false);

        $document = ElectronicDocument::query()
            ->where('environment', $environment)
            ->find($id);
        if ($document === null) {
            return response()->json([
                'message' => 'Electronic document not found.',
                'errors' => ['id' => ['Document #' . $id . ' not found in environment "' . $environment . '".']],
            ], 404);
        }

        $reference = null;
        if ($document->references_document_id) {
            $parent = ElectronicDocument::query()->find($document->references_document_id);
            if ($parent !== null) {
                $reference = $this->toSummary($parent);
            }
        }

        return response()->json([
            'environment' => $environment,
            'document' => array_merge($this->toSummary($document), [
                'reference' => $reference,
            ]),
        ]);
    }

    private function applyOrder($query, string $order): void
    {
        $direction = 'asc';
        $field = $order;
        if (strlen($order) > 0 && $order[0] === '-') {
            $direction = 'desc';
            $field = substr($order, 1);
        }
        $allowed = ['id', 'created_at', 'issue_date', 'dian_number', 'status'];
        if (!in_array($field, $allowed, true)) {
            $field = 'issue_date';
            $direction = 'desc';
        }
        $query->orderBy($field, $direction);
        if ($field !== 'id') {
            $query->orderBy('id', $direction);
        }
    }

    /**
     * @param ElectronicDocument $document
     */
    private function toSummary($document): array
    {
        return [
            'id' => (int) $document->id,
            'document_type' => (string) $document->document_type,
            'status' => (string) $document->status,
            'environment' => (string) $document->environment,
            'dian_number' => $document->dian_number !== null ? (string) $document->dian_number : null,
            'cufe_cude' => $document->cufe_cude !== null ? (string) $document->cufe_cude : null,
            'qr_url' => $document->qr_url !== null ? (string) $document->qr_url : null,
            'subtotal' => $document->subtotal !== null ? (string) $document->subtotal : null,
            'total_taxes' => $document->total_taxes !== null ? (string) $document->total_taxes : null,
            'total' => $document->total !== null ? (string) $document->total : null,
            'currency_code' => $document->currency_code !== null ? (string) $document->currency_code : null,
            'issue_date' => $document->issue_date ? $document->issue_date->toIso8601String() : null,
            'source_type' => $document->source_type !== null ? (string) $document->source_type : null,
            'source_id' => $document->source_id !== null ? (int) $document->source_id : null,
            'references_document_id' => $document->references_document_id !== null
                ? (int) $document->references_document_id
                : null,
            'has_unsigned_xml' => $document->xml_unsigned_path !== null && $document->xml_unsigned_path !== '',
            'has_signed_xml' => $document->xml_signed_path !== null && $document->xml_signed_path !== '',
            'has_pdf' => $document->pdf_path !== null && $document->pdf_path !== '',
            'notes' => $document->notes !== null ? (string) $document->notes : null,
        ];
    }
}
