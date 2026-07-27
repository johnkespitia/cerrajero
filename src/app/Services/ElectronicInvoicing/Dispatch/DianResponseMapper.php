<?php

namespace App\Services\ElectronicInvoicing\Dispatch;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;

/**
 * Maps a raw DIAN SOAP response into a structured outcome that
 * `DianDispatcher` can persist on an `ElectronicDocument`.
 *
 * Inputs:
 *  - `result`         array<string,mixed> Parsed `*Result` payload from
 *                     `WsSecuritySoapClient`. Typical keys:
 *                     `IsValid`, `StatusCode`, `StatusMessage`,
 *                     `StatusDescription`, `XmlBytes` (base64
 *                     ApplicationResponse), `XmlFileName`, `ErrorMessage`
 *                     (array of `string` nodes).
 *  - `operationName`  string Either `SendBillSync`, `SendBillAsync` or
 *                     `SendTestSetAsync`. Drives the default status
 *                     resolution when DIAN does not echo `IsValid`.
 *
 * Output (associative array):
 *  - `target_status`         string DocumentStatus::* the document
 *                            should transition to.
 *  - `is_valid`              bool|null Mirrors DIAN's `IsValid` flag.
 *  - `status_code`           string|null DIAN status code (e.g. `00`,
 *                            `89`).
 *  - `track_id`              string|null Async submissions only.
 *  - `application_response`  string|null Base64 ApplicationResponse XML
 *                            when DIAN returned `XmlBytes`.
 *  - `structured_errors`     array<int, array{code:string, message:string}>
 *                            Normalised list of error nodes.
 *
 * The mapper is intentionally side-effect free: it only inspects the
 * response payload and never persists data nor hits the network.
 */
final class DianResponseMapper
{
    /** @var array<int, string> */
    private const RECOVERABLE_CODES = [
        '89', // RUT / acquirer / numbering temporal
        '901', '902', '903', '904', '905', '906', '907', '908', '909',
    ];

    public function map(array $response, string $operationName): array
    {
        $result = is_array($response['result'] ?? null) ? (array) $response['result'] : [];
        $isValid = $this->parseIsValid($result);
        $statusCode = isset($result['StatusCode']) ? (string) $result['StatusCode'] : null;
        $trackId = isset($result['TrackId']) ? (string) $result['TrackId'] : null;
        $applicationResponse = $this->extractApplicationResponse($result);
        $errors = $this->extractErrors($result);

        $targetStatus = $this->resolveTargetStatus(
            $operationName,
            $isValid,
            $statusCode,
            $trackId,
            $errors
        );

        return [
            'target_status' => $targetStatus,
            'is_valid' => $isValid,
            'status_code' => $statusCode,
            'status_description' => isset($result['StatusDescription'])
                ? (string) $result['StatusDescription']
                : (isset($result['StatusMessage']) ? (string) $result['StatusMessage'] : null),
            'track_id' => $trackId,
            'application_response' => $applicationResponse,
            'structured_errors' => $errors,
        ];
    }

    private function parseIsValid(array $result): ?bool
    {
        if (! array_key_exists('IsValid', $result)) {
            return null;
        }
        $raw = $result['IsValid'];
        if (is_bool($raw)) {
            return $raw;
        }
        $normalized = strtolower(trim((string) $raw));

        return in_array($normalized, ['true', '1', 'yes', 'si'], true);
    }

    private function extractApplicationResponse(array $result): ?string
    {
        foreach (['XmlBytes', 'XmlBase64Bytes', 'XmlDocumentKey'] as $candidate) {
            if (! empty($result[$candidate])) {
                $value = (string) $result[$candidate];
                if (preg_match('/^[A-Za-z0-9+\/=]+$/', $value) === 1) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, array{code:string, message:string}>
     */
    private function extractErrors(array $result): array
    {
        $errors = [];
        foreach (['ErrorMessage', 'ErrorMessages', 'Errors'] as $key) {
            if (! isset($result[$key])) {
                continue;
            }
            $node = $result[$key];
            if (is_string($node)) {
                $errors[] = ['code' => '', 'message' => trim($node)];
                continue;
            }
            if (! is_array($node)) {
                continue;
            }
            foreach ($node as $entry) {
                if (is_string($entry)) {
                    $errors[] = ['code' => '', 'message' => trim($entry)];
                    continue;
                }
                if (is_array($entry)) {
                    $errors[] = [
                        'code' => (string) ($entry['code'] ?? $entry['Code'] ?? ''),
                        'message' => trim((string) (
                            $entry['message'] ?? $entry['Message'] ?? $entry['string'] ?? ''
                        )),
                    ];
                }
            }
        }
        // dedup empty entries
        return array_values(array_filter($errors, static fn ($e) => $e['message'] !== ''));
    }

    /**
     * @param array<int, array{code:string, message:string}> $errors
     */
    private function resolveTargetStatus(
        string $operationName,
        ?bool $isValid,
        ?string $statusCode,
        ?string $trackId,
        array $errors
    ): string {
        // Async submission: trackId implies the request was accepted and
        // moves to the "track received" state for polling.
        if (in_array($operationName, ['SendBillAsync', 'SendTestSetAsync'], true)) {
            if ($trackId !== null && $trackId !== '') {
                return DocumentStatus::DIAN_TRACK_RECEIVED;
            }
            return DocumentStatus::DIAN_REJECTED_RECOVERABLE;
        }

        // Sync submission.
        if ($isValid === true) {
            return DocumentStatus::DIAN_ACCEPTED;
        }
        if ($isValid === false) {
            if ($statusCode !== null && in_array($statusCode, self::RECOVERABLE_CODES, true)) {
                return DocumentStatus::DIAN_REJECTED_RECOVERABLE;
            }
            if ($errors !== [] && $this->errorsLookRecoverable($errors)) {
                return DocumentStatus::DIAN_REJECTED_RECOVERABLE;
            }
            return DocumentStatus::DIAN_REJECTED_TERMINAL;
        }

        // Unknown shape — assume the call reached DIAN but we cannot tell.
        return DocumentStatus::DIAN_VALIDATING;
    }

    /**
     * @param array<int, array{code:string, message:string}> $errors
     */
    private function errorsLookRecoverable(array $errors): bool
    {
        foreach ($errors as $entry) {
            $code = (string) ($entry['code'] ?? '');
            if ($code !== '' && in_array($code, self::RECOVERABLE_CODES, true)) {
                return true;
            }
            $msg = strtolower($entry['message']);
            if (str_contains($msg, 'timeout') || str_contains($msg, 'temporal') || str_contains($msg, 'temporary')) {
                return true;
            }
        }

        return false;
    }
}
