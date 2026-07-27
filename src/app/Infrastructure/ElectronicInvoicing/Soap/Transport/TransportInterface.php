<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap\Transport;

/**
 * HTTP transport contract used by WsSecuritySoapClient. Abstracting the
 * transport lets tests exercise the client without any network call.
 *
 * Implementations MUST NOT log request/response bodies (they contain signed
 * UBL XML which contains PII and may carry the X.509 cert).
 */
interface TransportInterface
{
    /**
     * Perform a POST request.
     *
     * @param string                $url
     * @param string                $body Raw request body (text/xml or application/soap+xml).
     * @param array<string, string> $headers
     * @return array{status:int, headers: array<string,string>, body:string}
     */
    public function post(string $url, string $body, array $headers): array;
}
