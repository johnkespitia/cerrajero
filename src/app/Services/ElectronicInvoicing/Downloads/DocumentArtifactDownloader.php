<?php

namespace App\Services\ElectronicInvoicing\Downloads;

use App\Models\ElectronicDocument;
use App\Services\ElectronicInvoicing\LegacyPt\LegacyPtArtifactStorageInterface;
use App\Services\ElectronicInvoicing\Storage\UnsignedXmlStorageInterface;

/**
 * Reads the binary artifacts attached to an `ElectronicDocument` row.
 *
 * The downloader is purely composed of the storage adapters already
 * registered by `ElectronicInvoicingServiceProvider`. It does not
 * touch the host filesystem on its own: any future disk-backed
 * storage must wire its retriever through the corresponding storage
 * interface so signed XMLs and PDFs cannot leak through unexpected
 * code paths.
 *
 * Supported artifact kinds (matching the columns on `electronic_documents`):
 *  - `xml_unsigned` -> `xml_unsigned_path`
 *  - `xml_signed`   -> `xml_signed_path` (also covers legacy XMLs which
 *                       are already signed by the previous PT)
 *  - `attached`     -> `attached_document_path`
 *  - `pdf`          -> `pdf_path`
 *
 * Returns null when the document does not have the requested artifact
 * or when the storage adapter cannot resolve the path (so the
 * controller can serve a 404).
 */
class DocumentArtifactDownloader
{
    public const KIND_XML_UNSIGNED = 'xml_unsigned';
    public const KIND_XML_SIGNED = 'xml_signed';
    public const KIND_ATTACHED = 'attached';
    public const KIND_PDF = 'pdf';

    public function __construct(
        private readonly UnsignedXmlStorageInterface $unsignedXml,
        private readonly LegacyPtArtifactStorageInterface $legacyStorage,
    ) {
    }

    /**
     * @return array{bytes: string, mime: string, filename: string}|null
     */
    public function download(ElectronicDocument $document, string $kind): ?array
    {
        switch ($kind) {
            case self::KIND_XML_UNSIGNED:
                return $this->readUnsignedXml($document);
            case self::KIND_XML_SIGNED:
                return $this->readSignedXml($document);
            case self::KIND_ATTACHED:
                return $this->readAttachedDocument($document);
            case self::KIND_PDF:
                return $this->readPdf($document);
            default:
                return null;
        }
    }

    private function readUnsignedXml(ElectronicDocument $document): ?array
    {
        $path = (string) ($document->xml_unsigned_path ?? '');
        if ($path === '') {
            return null;
        }
        $bytes = $this->unsignedXml->retrieve($path);
        if ($bytes === null) {
            return null;
        }

        return [
            'bytes' => $bytes,
            'mime' => 'application/xml',
            'filename' => $this->fileName($document, 'unsigned.xml'),
        ];
    }

    private function readSignedXml(ElectronicDocument $document): ?array
    {
        $path = (string) ($document->xml_signed_path ?? '');
        if ($path === '') {
            return null;
        }
        $bytes = $this->resolveBytes($path);
        if ($bytes === null) {
            return null;
        }

        return [
            'bytes' => $bytes,
            'mime' => 'application/xml',
            'filename' => $this->fileName($document, 'signed.xml'),
        ];
    }

    private function readAttachedDocument(ElectronicDocument $document): ?array
    {
        $path = (string) ($document->attached_document_path ?? '');
        if ($path === '') {
            return null;
        }
        $bytes = $this->resolveBytes($path);
        if ($bytes === null) {
            return null;
        }

        return [
            'bytes' => $bytes,
            'mime' => 'application/xml',
            'filename' => $this->fileName($document, 'attached.xml'),
        ];
    }

    private function readPdf(ElectronicDocument $document): ?array
    {
        $path = (string) ($document->pdf_path ?? '');
        if ($path === '') {
            return null;
        }
        $bytes = $this->resolveBytes($path);
        if ($bytes === null) {
            return null;
        }

        return [
            'bytes' => $bytes,
            'mime' => 'application/pdf',
            'filename' => $this->fileName($document, 'document.pdf'),
        ];
    }

    /**
     * Try every registered storage in order. Returning the first hit
     * keeps the downloader oblivious to the prefix scheme.
     */
    private function resolveBytes(string $path): ?string
    {
        $candidates = [
            $this->unsignedXml->retrieve($path),
            $this->legacyStorage->retrieve($path),
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function fileName(ElectronicDocument $document, string $suffix): string
    {
        $base = $document->dian_number !== null && $document->dian_number !== ''
            ? (string) $document->dian_number
            : ('doc-' . (int) $document->id);

        return sprintf('%s-%s', $this->slug($base), $suffix);
    }

    private function slug(string $value): string
    {
        $slug = preg_replace('/[^A-Za-z0-9_\-]/', '_', $value);

        return $slug !== '' ? (string) $slug : 'document';
    }
}
