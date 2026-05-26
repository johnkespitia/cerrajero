<?php

namespace App\Http\Controllers\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\RadianEventCode;
use App\Http\Controllers\Controller;
use App\Models\ElectronicDocument;
use App\Services\ElectronicInvoicing\Exceptions\RadianEventException;
use App\Services\ElectronicInvoicing\Radian\RadianEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HTTP entry point for RADIAN events.
 *
 * Endpoints:
 *  - `POST /api/electronic-invoicing/documents/{id}/radian/{event}` ->
 *    emits a single RADIAN event (codes 030, 031, 032, 033, 034) for
 *    the given parent FEV. Requires permission `electronic_invoicing.radian`.
 *  - `GET  /api/electronic-invoicing/documents/{id}/radian` -> returns
 *    the list of RADIAN events persisted for the document. Requires
 *    permission `electronic_invoicing.list`.
 *
 * The controller stays thin: validation, error mapping (HTTP status +
 * error code envelope), and delegation to `RadianEventService`.
 */
class RadianEventController extends Controller
{
    public function __construct(private readonly RadianEventService $service)
    {
    }

    public function index(int $id): JsonResponse
    {
        $document = ElectronicDocument::query()->findOrFail($id);
        return response()->json([
            'document_id' => $document->id,
            'events' => $this->service->listForDocument($document->id),
        ]);
    }

    public function store(Request $request, int $id, string $event): JsonResponse
    {
        $document = ElectronicDocument::query()->findOrFail($id);
        $event = (string) $event;
        if (! in_array($event, RadianEventCode::ALL, true)) {
            return response()->json([
                'error_code' => 'radian_invalid_code',
                'message' => "Unknown RADIAN event code [{$event}].",
            ], 422);
        }

        $validated = $request->validate([
            'actor' => 'sometimes|string|max:120',
            'actor_nit' => 'sometimes|string|max:20',
            'actor_name' => 'sometimes|string|max:200',
        ]);

        try {
            $dianEvent = $this->service->emit($document, $event, $validated);
        } catch (RadianEventException $e) {
            $status = $this->mapErrorToStatus($e->errorCode());
            return response()->json([
                'error_code' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }

        return response()->json([
            'document_id' => $document->id,
            'event' => [
                'id' => $dianEvent->id,
                'event_code' => $dianEvent->event_code,
                'status' => $dianEvent->status,
                'cude' => $dianEvent->cude,
                'dian_track_id' => $dianEvent->dian_track_id,
                'dian_status_code' => $dianEvent->dian_status_code,
                'dian_is_valid' => $dianEvent->dian_is_valid,
                'dian_error_messages' => $dianEvent->dian_error_messages,
                'emitted_at' => optional($dianEvent->emitted_at)->toIso8601String(),
                'resolved_at' => optional($dianEvent->resolved_at)->toIso8601String(),
            ],
        ], 201);
    }

    private function mapErrorToStatus(string $code): int
    {
        return match ($code) {
            'radian_unsupported_document',
            'radian_document_not_accepted',
            'radian_missing_cufe',
            'radian_invalid_code',
            'radian_already_accepted' => 422,
            'radian_soap_failed' => 502,
            'radian_signing_failed' => 500,
            default => 500,
        };
    }
}
