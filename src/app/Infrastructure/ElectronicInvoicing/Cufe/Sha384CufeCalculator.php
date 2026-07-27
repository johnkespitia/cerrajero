<?php

namespace App\Infrastructure\ElectronicInvoicing\Cufe;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Ports\CufeCalculatorInterface;
use App\Domain\ElectronicInvoicing\ValueObjects\Cufe;
use InvalidArgumentException;

/**
 * Computes CUFE (FEV) and CUDE (NC, ND, DEE POS) by concatenating the DIAN
 * fields in the order defined by Anexo Tecnico numerals 11.1.x and applying
 * SHA-384. Returns a lowercase 96-char hex string wrapped in a Cufe VO.
 *
 * This implementation is intentionally string-in / string-out: the caller is
 * responsible for formatting each field per DIAN (NumFac as full prefixed
 * number, FecFac yyyy-MM-dd, HoraFac HH:mm:ss-05:00, monetary values with two
 * decimal places when applicable, etc). The calculator never reformats data.
 */
final class Sha384CufeCalculator implements CufeCalculatorInterface
{
    /**
     * Per-document-type ordered list of payload keys.
     *
     * The last key differs by document type:
     *  - FEV uses "clave_tecnica" (from the DIAN numbering resolution).
     *  - NC, ND, DEE POS use "pin" (from the DianSoftwareCredential).
     *
     * Pending oficial vectors for ApplicationResponse / RADIAN events: those
     * variants will be added in a later slice once we receive xmlfixtures.
     */
    private const FIELD_SCHEMAS = [
        DocumentType::FEV => [
            'num_doc',
            'fec_doc',
            'hora_doc',
            'val_doc',
            'cod_imp_1', 'val_imp_1',
            'cod_imp_2', 'val_imp_2',
            'cod_imp_3', 'val_imp_3',
            'val_imp_total',
            'nit_ofe',
            'num_adq',
            'clave_tecnica',
            'tipo_ambiente',
        ],
        DocumentType::NC => [
            'num_doc',
            'fec_doc',
            'hora_doc',
            'val_doc',
            'cod_imp_1', 'val_imp_1',
            'cod_imp_2', 'val_imp_2',
            'cod_imp_3', 'val_imp_3',
            'val_imp_total',
            'nit_ofe',
            'num_adq',
            'pin',
            'tipo_ambiente',
        ],
        DocumentType::ND => [
            'num_doc',
            'fec_doc',
            'hora_doc',
            'val_doc',
            'cod_imp_1', 'val_imp_1',
            'cod_imp_2', 'val_imp_2',
            'cod_imp_3', 'val_imp_3',
            'val_imp_total',
            'nit_ofe',
            'num_adq',
            'pin',
            'tipo_ambiente',
        ],
        DocumentType::DEE_POS => [
            'num_doc',
            'fec_doc',
            'hora_doc',
            'val_doc',
            'cod_imp_1', 'val_imp_1',
            'cod_imp_2', 'val_imp_2',
            'cod_imp_3', 'val_imp_3',
            'val_imp_total',
            'nit_ofe',
            'num_adq',
            'pin',
            'tipo_ambiente',
        ],
    ];

    public function calculate(string $documentType, array $fields): Cufe
    {
        DocumentType::assert($documentType);

        $schema = self::FIELD_SCHEMAS[$documentType] ?? null;
        if ($schema === null) {
            throw new InvalidArgumentException(sprintf(
                'No CUFE field schema registered for document type "%s".',
                $documentType
            ));
        }

        $payload = $this->buildPayload($schema, $fields, $documentType);
        $hex = hash('sha384', $payload);

        return new Cufe($hex);
    }

    /**
     * Returns the canonical DIAN concatenation that will be hashed. Exposed for
     * audit/log purposes and for assertion in tests; callers MUST NOT include
     * this value in user-facing responses.
     */
    public function payload(string $documentType, array $fields): string
    {
        DocumentType::assert($documentType);

        $schema = self::FIELD_SCHEMAS[$documentType] ?? null;
        if ($schema === null) {
            throw new InvalidArgumentException(sprintf(
                'No CUFE field schema registered for document type "%s".',
                $documentType
            ));
        }

        return $this->buildPayload($schema, $fields, $documentType);
    }

    public function fieldsFor(string $documentType): array
    {
        DocumentType::assert($documentType);
        return self::FIELD_SCHEMAS[$documentType] ?? [];
    }

    private function buildPayload(array $schema, array $fields, string $documentType): string
    {
        $payload = '';
        foreach ($schema as $key) {
            if (!array_key_exists($key, $fields)) {
                throw new InvalidArgumentException(sprintf(
                    'Missing CUFE field "%s" for document type "%s".',
                    $key,
                    $documentType
                ));
            }

            $value = $fields[$key];
            if ($value === null) {
                throw new InvalidArgumentException(sprintf(
                    'CUFE field "%s" must not be null for document type "%s".',
                    $key,
                    $documentType
                ));
            }

            if (!is_scalar($value)) {
                throw new InvalidArgumentException(sprintf(
                    'CUFE field "%s" must be scalar, %s given.',
                    $key,
                    gettype($value)
                ));
            }

            $payload .= (string) $value;
        }

        return $payload;
    }
}
