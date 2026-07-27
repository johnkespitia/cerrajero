<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap\Transport;

use App\Infrastructure\ElectronicInvoicing\Soap\Exceptions\DianSoapTransportException;

/**
 * Real HTTP transport built on top of ext-curl.
 *
 * This class is intentionally NOT instantiated during CI tests (CI uses
 * RecordingTransport to keep tests hermetic). Production wiring goes through
 * a service-provider binding driven by config('electronic-invoicing').
 *
 * Hardening:
 *  - TLS 1.2+ enforced.
 *  - Peer verification on.
 *  - Configurable timeouts.
 *  - No body content is ever passed to a logger.
 */
final class CurlTransport implements TransportInterface
{
    /** @var int */
    private $timeoutSeconds;

    /** @var int */
    private $connectTimeoutSeconds;

    /** @var bool */
    private $verifyPeer;

    public function __construct(int $timeoutSeconds = 30, int $connectTimeoutSeconds = 8, bool $verifyPeer = true)
    {
        $this->timeoutSeconds = $timeoutSeconds;
        $this->connectTimeoutSeconds = $connectTimeoutSeconds;
        $this->verifyPeer = $verifyPeer;
    }

    public function post(string $url, string $body, array $headers): array
    {
        if (!function_exists('curl_init')) {
            throw new DianSoapTransportException('ext-curl is required to reach DIAN webservices.');
        }

        $handle = curl_init();
        if ($handle === false) {
            throw new DianSoapTransportException('Could not initialise the HTTP transport.');
        }

        $formattedHeaders = [];
        foreach ($headers as $name => $value) {
            $formattedHeaders[] = $name . ': ' . $value;
        }

        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => $this->verifyPeer,
            CURLOPT_SSL_VERIFYHOST => $this->verifyPeer ? 2 : 0,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
        ]);

        $raw = curl_exec($handle);
        if ($raw === false) {
            $errno = curl_errno($handle);
            curl_close($handle);
            throw new DianSoapTransportException(sprintf(
                'HTTP transport failed (errno=%d).',
                $errno
            ));
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        curl_close($handle);

        $rawHeaders = substr($raw, 0, $headerSize);
        $responseBody = substr($raw, $headerSize);

        return [
            'status' => $status,
            'headers' => $this->parseHeaders($rawHeaders),
            'body' => $responseBody === false ? '' : $responseBody,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $raw): array
    {
        $parsed = [];
        $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
        foreach ($lines as $line) {
            if ($line === '' || strpos($line, ':') === false) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $parsed[strtolower(trim($name))] = trim($value);
        }
        return $parsed;
    }
}
