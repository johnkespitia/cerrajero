<?php

namespace App\Http\Controllers\ElectronicInvoicing;

use App\Http\Controllers\Controller;
use App\Models\ElectronicDocument;
use App\Services\ElectronicInvoicing\Downloads\DocumentArtifactDownloader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only endpoints that serve the binary artifacts attached to an
 * electronic document plus the audit-event timeline.
 *
 *  GET /api/electronic-invoicing/documents/{id}/xml-unsigned
 *  GET /api/electronic-invoicing/documents/{id}/xml-signed
 *  GET /api/electronic-invoicing/documents/{id}/attached-document
 *  GET /api/electronic-invoicing/documents/{id}/pdf
 *  GET /api/electronic-invoicing/documents/{id}/events
 *
 * All endpoints require `electronic_invoicing.list` permission (wired
 * at the route level). The download endpoints stream bytes through
 * `DocumentArtifactDownloader` so they never log the artifact body.
 *
 * Events are serialised with the `correlation_id`, `event_type`,
 * `actor` and a PII-aware `payload` view — payloads are returned as
 * stored (the writer is responsible for masking secrets before
 * persisting).
 */
class DocumentDownloadController extends Controller
{
    use FiscalEnvironmentResolver;

    public function __construct(private readonly DocumentArtifactDownloader $downloader)
    {
    }

    public function xmlUnsigned(Request $request, int $id): Response
    {
        return $this->serve($request, $id, DocumentArtifactDownloader::KIND_XML_UNSIGNED);
    }

    public function xmlSigned(Request $request, int $id): Response
    {
        return $this->serve($request, $id, DocumentArtifactDownloader::KIND_XML_SIGNED);
    }

    public function attachedDocument(Request $request, int $id): Response
    {
        return $this->serve($request, $id, DocumentArtifactDownloader::KIND_ATTACHED);
    }

    public function pdf(Request $request, int $id): Response
    {
        return $this->serve($request, $id, DocumentArtifactDownloader::KIND_PDF);
    }

    public function events(Request $request, int $id): JsonResponse
    {
        $environment = $this->resolveEnvironment($request, false);

        $document = ElectronicDocument::query()
            ->where('environment', $environment)
            ->find($id);
        if ($document === null) {
            return $this->notFound($id, $environment);
        }

        $events = $document->events()
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(fn ($event) => [
                'id' => (int) $event->id,
                'event_type' => (string) $event->event_type,
                'actor' => $event->actor !== null ? (string) $event->actor : null,
                'correlation_id' => $event->correlation_id !== null ? (string) $event->correlation_id : null,
                'occurred_at' => $event->occurred_at ? $event->occurred_at->toIso8601String() : null,
                'payload' => $event->payload,
            ])
            ->all();

        return response()->json([
            'environment' => $environment,
            'document_id' => $document->id,
            'events' => $events,
        ]);
    }

    private function serve(Request $request, int $id, string $kind): Response
    {
        $environment = $this->resolveEnvironment($request, false);

        $document = ElectronicDocument::query()
            ->where('environment', $environment)
            ->find($id);
        if ($document === null) {
            return $this->notFound($id, $environment);
        }

        $artifact = $this->downloader->download($document, $kind);
        if ($artifact === null) {
            return response()->json([
                'electronic_document_error' => [
                    'code' => 'artifact_not_available',
                    'message' => sprintf('Document #%d has no `%s` artifact available.', $id, $kind),
                ],
            ], 404);
        }

        return response($artifact['bytes'], 200, [
            'Content-Type' => $artifact['mime'],
            'Content-Disposition' => sprintf('attachment; filename="%s"', $artifact['filename']),
            'Content-Length' => (string) strlen($artifact['bytes']),
        ]);
    }

    private function notFound(int $id, string $environment): JsonResponse
    {
        return response()->json([
            'electronic_document_error' => [
                'code' => 'document_not_found',
                'message' => sprintf('Document #%d not found in environment "%s".', $id, $environment),
            ],
        ], 404);
    }
}
