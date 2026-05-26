<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap;

use App\Domain\ElectronicInvoicing\Ports\DianSoapClientInterface;
use App\Infrastructure\ElectronicInvoicing\Soap\Actions\AbstractDianAction;
use App\Infrastructure\ElectronicInvoicing\Soap\Actions\GetNumberingRangeAction;
use App\Infrastructure\ElectronicInvoicing\Soap\Actions\GetStatusAction;
use App\Infrastructure\ElectronicInvoicing\Soap\Actions\GetStatusZipAction;
use App\Infrastructure\ElectronicInvoicing\Soap\Actions\GetXmlByDocumentKeyAction;
use App\Infrastructure\ElectronicInvoicing\Soap\Actions\SendBillAsyncAction;
use App\Infrastructure\ElectronicInvoicing\Soap\Actions\SendBillSyncAction;
use App\Infrastructure\ElectronicInvoicing\Soap\Actions\SendEventUpdateStatusAction;
use App\Infrastructure\ElectronicInvoicing\Soap\Actions\SendTestSetAsyncAction;
use App\Infrastructure\ElectronicInvoicing\Soap\Exceptions\DianSoapResponseException;
use App\Infrastructure\ElectronicInvoicing\Soap\Exceptions\DianSoapSigningUnavailableException;
use App\Infrastructure\ElectronicInvoicing\Soap\Transport\TransportInterface;
use DOMDocument;

/**
 * WsSecuritySoapClient: implementacion concreta de DianSoapClientInterface.
 *
 * Modo de operacion:
 *  - dry_run=true (o cuando NO se inyecta SigningMaterial): el cliente
 *    construye el envelope (firmado si hay material, unsigned si no) y lo
 *    devuelve SIN enviar a la red. Esto es lo que CI y los tests usan.
 *
 *  - dry_run=false con SigningMaterial: el cliente firma el envelope WS-Security
 *    real con DOMNode::C14N(true) + openssl_sign RSA-SHA256 y dispatcha el
 *    POST via el TransportInterface. La integracion productiva con la URL HAB
 *    o PROD se inyecta por DI; CI nunca llega a esta rama.
 *
 *  - dry_run=false SIN SigningMaterial: lanza DianSoapSigningUnavailableException
 *    para evitar contacto inadvertido con DIAN sin firma.
 *
 * Hardening:
 *  - No se loggean envelopes ni respuestas. El llamador es responsable de
 *    persistir auditoria en ElectronicDocumentEvent sin payload sensible.
 *  - SigningMaterial nunca aparece en mensajes de excepcion.
 *  - SOAPAction se manda como HTTP header; los namespaces estan centralizados
 *    en SoapNamespaces para auditoria.
 */
final class WsSecuritySoapClient implements DianSoapClientInterface
{
    /** @var WsSecurityEnvelopeBuilder */
    private $envelopeBuilder;

    /** @var TransportInterface */
    private $transport;

    /** @var array<string, mixed> */
    private $config;

    /** @var SigningMaterial|null */
    private $signingMaterial;

    /** @var array<string, AbstractDianAction> */
    private $actions;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        WsSecurityEnvelopeBuilder $envelopeBuilder,
        TransportInterface $transport,
        array $config,
        ?SigningMaterial $signingMaterial = null
    ) {
        $this->envelopeBuilder = $envelopeBuilder;
        $this->transport = $transport;
        $this->config = $config;
        $this->signingMaterial = $signingMaterial;

        $this->actions = [
            'SendBillSync' => new SendBillSyncAction(),
            'SendBillAsync' => new SendBillAsyncAction(),
            'SendTestSetAsync' => new SendTestSetAsyncAction(),
            'GetStatus' => new GetStatusAction(),
            'GetStatusZip' => new GetStatusZipAction(),
            'GetNumberingRange' => new GetNumberingRangeAction(),
            'SendEventUpdateStatus' => new SendEventUpdateStatusAction(),
            'GetXmlByDocumentKey' => new GetXmlByDocumentKeyAction(),
        ];
    }

    public function sendBillSync(string $fileName, string $zipBase64): array
    {
        return $this->dispatch('SendBillSync', [
            'fileName' => $fileName,
            'contentFile' => $zipBase64,
        ]);
    }

    public function sendBillAsync(string $fileName, string $zipBase64): array
    {
        return $this->dispatch('SendBillAsync', [
            'fileName' => $fileName,
            'contentFile' => $zipBase64,
        ]);
    }

    public function sendTestSetAsync(string $fileName, string $zipBase64, string $testSetId): array
    {
        return $this->dispatch('SendTestSetAsync', [
            'fileName' => $fileName,
            'contentFile' => $zipBase64,
            'testSetId' => $testSetId,
        ]);
    }

    public function getStatus(string $trackId): array
    {
        return $this->dispatch('GetStatus', ['trackId' => $trackId]);
    }

    public function getStatusZip(string $trackId): array
    {
        return $this->dispatch('GetStatusZip', ['trackId' => $trackId]);
    }

    public function getNumberingRange(array $params): array
    {
        return $this->dispatch('GetNumberingRange', $params);
    }

    public function sendEventUpdateStatus(string $fileName, string $zipBase64): array
    {
        return $this->dispatch('SendEventUpdateStatus', [
            'fileName' => $fileName,
            'contentFile' => $zipBase64,
        ]);
    }

    public function getXmlByDocumentKey(string $cufe): array
    {
        return $this->dispatch('GetXmlByDocumentKey', ['trackId' => $cufe]);
    }

    /**
     * Build the SOAP envelope for an operation without sending it.
     *
     * Used by tests and audit tooling. Returns the signed envelope when
     * SigningMaterial is configured, unsigned envelope otherwise.
     */
    public function buildEnvelopeFor(string $operationName, array $params): string
    {
        return $this->buildEnvelope($this->resolveAction($operationName), $params);
    }

    public function dryRun(string $operationName, array $params): array
    {
        $action = $this->resolveAction($operationName);
        $envelope = $this->buildEnvelope($action, $params);
        return [
            'dry_run' => true,
            'soap_action' => $action->soapAction(),
            'endpoint' => $this->endpoint(),
            'envelope' => $envelope,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function supportedOperations(): array
    {
        return array_keys($this->actions);
    }

    private function dispatch(string $operationName, array $params): array
    {
        $action = $this->resolveAction($operationName);

        if (!empty($this->config['dry_run'])) {
            return [
                'dry_run' => true,
                'soap_action' => $action->soapAction(),
                'endpoint' => $this->endpoint(),
                'envelope' => $this->buildEnvelope($action, $params),
            ];
        }

        if ($this->signingMaterial === null) {
            throw new DianSoapSigningUnavailableException(
                'WS-Security signing material is required to call DIAN.'
            );
        }

        $envelope = $this->buildEnvelope($action, $params);
        $headers = [
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => '"' . $action->soapAction() . '"',
            'Accept' => 'text/xml',
        ];

        $response = $this->transport->post($this->endpoint(), $envelope, $headers);

        return $this->parseResponse($response, $action);
    }

    private function buildEnvelope(AbstractDianAction $action, array $params): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $operationEl = $action->buildOperationElement($doc, $params);

        return $this->envelopeBuilder->build(
            $operationEl,
            $this->signingMaterial,
            null,
            (int) ($this->config['window_seconds'] ?? WsSecurityEnvelopeBuilder::DEFAULT_WINDOW_SECONDS)
        );
    }

    private function resolveAction(string $operationName): AbstractDianAction
    {
        if (!isset($this->actions[$operationName])) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown DIAN SOAP operation "%s".',
                $operationName
            ));
        }
        return $this->actions[$operationName];
    }

    private function endpoint(): string
    {
        $endpoint = (string) ($this->config['endpoint'] ?? '');
        if ($endpoint === '') {
            throw new \InvalidArgumentException(
                'config.endpoint is required to dispatch DIAN SOAP requests.'
            );
        }
        return $endpoint;
    }

    /**
     * @param array{status:int, headers: array<string,string>, body:string} $response
     */
    private function parseResponse(array $response, AbstractDianAction $action): array
    {
        $status = $response['status'];
        $body = $response['body'];

        if ($status >= 500) {
            throw new DianSoapResponseException(
                'DIAN webservice returned a server error.',
                $status,
                $this->extractFaultCode($body)
            );
        }

        if ($status < 200 || $status >= 300) {
            throw new DianSoapResponseException(
                'DIAN webservice returned a non-success HTTP status.',
                $status,
                $this->extractFaultCode($body)
            );
        }

        $parsed = $this->extractOperationResult($body, $action->operationName());

        return [
            'dry_run' => false,
            'http_status' => $status,
            'soap_action' => $action->soapAction(),
            'result' => $parsed,
        ];
    }

    private function extractOperationResult(string $body, string $operationName): array
    {
        if ($body === '') {
            return [];
        }

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $doc->loadXML($body, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) {
            return [];
        }

        $resultName = $operationName . 'Result';
        $resultNodes = $doc->getElementsByTagNameNS(SoapNamespaces::WCF_DIAN, $resultName);
        if ($resultNodes->length === 0) {
            $resultNodes = $doc->getElementsByTagName($resultName);
        }
        if ($resultNodes->length === 0) {
            return [];
        }

        $result = [];
        foreach ($resultNodes->item(0)->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $result[$child->localName] = trim($child->textContent);
        }
        return $result;
    }

    private function extractFaultCode(string $body): ?string
    {
        if ($body === '') {
            return null;
        }

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $doc->loadXML($body, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) {
            return null;
        }

        foreach (['faultcode', 'Code', 'Value'] as $name) {
            $nodes = $doc->getElementsByTagName($name);
            if ($nodes->length > 0) {
                return trim($nodes->item(0)->textContent);
            }
        }
        return null;
    }
}
