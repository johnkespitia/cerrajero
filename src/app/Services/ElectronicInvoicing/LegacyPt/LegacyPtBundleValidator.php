<?php

namespace App\Services\ElectronicInvoicing\LegacyPt;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;

/**
 * Validates a Legacy PT bundle document-by-document before persistence.
 *
 * The contract of a "consistent" legacy document is:
 *
 *  1. Required header fields are present (`legacy_pt_id`, `document_type`,
 *     `dian_number`, `cufe_cude`, `issue_date`, `total`, `xml_base64`).
 *  2. `document_type` matches one of `DocumentType::all()`.
 *  3. The decoded XML parses as valid XML.
 *  4. The XML's top-level `cbc:UUID` element (or `cufe`/`cude` attribute on
 *     fallback) matches the declared `cufe_cude` value (case-insensitive).
 *
 * If any of those conditions fail the document is classified as
 * `inconsistent` with an explicit `reason` code. Documents missing
 * entirely (e.g. `xml_base64` not present and `pdf_path` empty) end up
 * as `missing` so finance can request the original artifact from the
 * legacy PT.
 *
 * No DB or filesystem calls happen here: the validator is pure so it can
 * be exercised cheaply by `php artisan electronic-invoicing:legacy-pt:dry-run`
 * (later slice) without booting the application kernel.
 */
class LegacyPtBundleValidator
{
    public const RESULT_CONSISTENT = 'consistent';
    public const RESULT_INCONSISTENT = 'inconsistent';
    public const RESULT_MISSING = 'missing';

    public const REASON_MISSING_FIELDS = 'missing_fields';
    public const REASON_INVALID_DOCUMENT_TYPE = 'invalid_document_type';
    public const REASON_INVALID_XML = 'invalid_xml';
    public const REASON_CUFE_MISMATCH = 'cufe_mismatch';
    public const REASON_MISSING_ARTIFACT = 'missing_artifact';

    private const REQUIRED_FIELDS = [
        'legacy_pt_id',
        'document_type',
        'dian_number',
        'cufe_cude',
        'issue_date',
        'total',
    ];

    /**
     * Validate the entire bundle.
     *
     * @param  array<int, array<string,mixed>>  $documents
     * @return array<int, array{
     *     index: int,
     *     legacy_pt_id: string|null,
     *     status: string,
     *     reason: string|null,
     *     details: array<string,mixed>
     * }>
     */
    public function validate(array $documents): array
    {
        $rows = [];
        foreach ($documents as $index => $document) {
            $rows[] = $this->validateOne((int) $index, $document);
        }

        return $rows;
    }

    /**
     * @param  array<string,mixed>  $document
     * @return array{index:int, legacy_pt_id: string|null, status: string, reason: string|null, details: array<string,mixed>}
     */
    private function validateOne(int $index, array $document): array
    {
        $legacyPtId = isset($document['legacy_pt_id']) ? (string) $document['legacy_pt_id'] : null;

        $missingFields = $this->requiredFieldsMissing($document);
        if ($missingFields !== []) {
            return $this->result($index, $legacyPtId, self::RESULT_INCONSISTENT, self::REASON_MISSING_FIELDS, [
                'missing_fields' => $missingFields,
            ]);
        }

        $documentType = (string) $document['document_type'];
        if (! in_array($documentType, DocumentType::all(), true)) {
            return $this->result($index, $legacyPtId, self::RESULT_INCONSISTENT, self::REASON_INVALID_DOCUMENT_TYPE, [
                'document_type' => $documentType,
            ]);
        }

        $xmlBase64 = isset($document['xml_base64']) ? (string) $document['xml_base64'] : '';
        $hasPdfFallback = isset($document['pdf_path']) && trim((string) $document['pdf_path']) !== '';
        if ($xmlBase64 === '') {
            if (! $hasPdfFallback) {
                return $this->result($index, $legacyPtId, self::RESULT_MISSING, self::REASON_MISSING_ARTIFACT, [
                    'missing' => ['xml_base64', 'pdf_path'],
                ]);
            }
            // We can still record the document (status legacy_import_inconsistent)
            // for finance review, but we cannot validate CUFE.
            return $this->result($index, $legacyPtId, self::RESULT_INCONSISTENT, self::REASON_MISSING_ARTIFACT, [
                'missing' => ['xml_base64'],
                'pdf_path' => $document['pdf_path'],
            ]);
        }

        $xml = $this->decodeXml($xmlBase64);
        if ($xml === null) {
            return $this->result($index, $legacyPtId, self::RESULT_INCONSISTENT, self::REASON_INVALID_XML, [
                'hint' => 'xml_base64 could not be base64-decoded or parsed as XML',
            ]);
        }

        $declaredCufe = strtolower(trim((string) $document['cufe_cude']));
        $extractedCufe = $this->extractCufe($xml);
        if ($extractedCufe === null || strtolower($extractedCufe) !== $declaredCufe) {
            return $this->result($index, $legacyPtId, self::RESULT_INCONSISTENT, self::REASON_CUFE_MISMATCH, [
                'declared_cufe' => $document['cufe_cude'],
                'extracted_cufe' => $extractedCufe,
            ]);
        }

        return $this->result($index, $legacyPtId, self::RESULT_CONSISTENT, null, [
            'document_type' => $documentType,
            'dian_number' => $document['dian_number'],
        ]);
    }

    /**
     * @param  array<string,mixed>  $document
     * @return array<int, string>
     */
    private function requiredFieldsMissing(array $document): array
    {
        $missing = [];
        foreach (self::REQUIRED_FIELDS as $field) {
            if (! array_key_exists($field, $document) || $document[$field] === null || $document[$field] === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    private function decodeXml(string $base64): ?\SimpleXMLElement
    {
        $decoded = base64_decode($base64, true);
        if ($decoded === false || $decoded === '') {
            return null;
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($decoded);

            return $xml !== false ? $xml : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function extractCufe(\SimpleXMLElement $xml): ?string
    {
        $cbc = $xml->getDocNamespaces(true)['cbc']
            ?? 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
        $xml->registerXPathNamespace('cbc', $cbc);

        $matches = $xml->xpath('//cbc:UUID');
        if (is_array($matches) && isset($matches[0])) {
            $value = trim((string) $matches[0]);
            if ($value !== '') {
                return $value;
            }
        }

        // Fallback: some legacy PT bundles dump the CUFE in attributes
        // like `cufe="..."` or `cude="..."` on the root element.
        foreach (['cufe', 'cude'] as $attribute) {
            $attr = $xml->attributes();
            if ($attr !== null && isset($attr[$attribute])) {
                $value = trim((string) $attr[$attribute]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @return array{index:int, legacy_pt_id: string|null, status: string, reason: string|null, details: array<string,mixed>}
     */
    private function result(int $index, ?string $legacyPtId, string $status, ?string $reason, array $details): array
    {
        return [
            'index' => $index,
            'legacy_pt_id' => $legacyPtId,
            'status' => $status,
            'reason' => $reason,
            'details' => $details,
        ];
    }
}
