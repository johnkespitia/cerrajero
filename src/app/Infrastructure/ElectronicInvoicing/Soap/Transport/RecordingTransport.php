<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap\Transport;

/**
 * Test-only transport that records every outbound request and returns
 * pre-configured responses without ever touching the network.
 *
 * NEVER wire this in production.
 */
final class RecordingTransport implements TransportInterface
{
    /** @var array<int, array{url:string, body:string, headers: array<string,string>}> */
    private $requests = [];

    /** @var array<int, array{status:int, headers: array<string,string>, body:string}> */
    private $responses = [];

    /** @var int */
    private $cursor = 0;

    public function enqueueResponse(int $status, string $body, array $headers = []): void
    {
        $this->responses[] = [
            'status' => $status,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    public function post(string $url, string $body, array $headers): array
    {
        $this->requests[] = [
            'url' => $url,
            'body' => $body,
            'headers' => $headers,
        ];

        if (!isset($this->responses[$this->cursor])) {
            return ['status' => 200, 'headers' => [], 'body' => ''];
        }

        $response = $this->responses[$this->cursor];
        $this->cursor++;
        return $response;
    }

    /**
     * @return array<int, array{url:string, body:string, headers: array<string,string>}>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    public function lastRequest(): ?array
    {
        if ($this->requests === []) {
            return null;
        }
        return $this->requests[count($this->requests) - 1];
    }

    public function reset(): void
    {
        $this->requests = [];
        $this->responses = [];
        $this->cursor = 0;
    }
}
