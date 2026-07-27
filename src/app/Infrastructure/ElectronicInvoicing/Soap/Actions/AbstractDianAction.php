<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap\Actions;

use App\Infrastructure\ElectronicInvoicing\Soap\Exceptions\InvalidSoapPayloadException;
use App\Infrastructure\ElectronicInvoicing\Soap\SoapNamespaces;
use DOMDocument;
use DOMElement;

/**
 * Shared scaffolding for the eight DIAN SOAP operations.
 *
 * Subclasses declare:
 *  - operationName() : WCF method name (used as element name and SOAPAction).
 *  - requiredParams(): the keys that must be present in params.
 *  - buildBodyContents($doc, $element, $params): fills <wcf:OPERATION>...
 *
 * Validation is centralised here so every action raises the same exception
 * type (InvalidSoapPayloadException) on missing/empty fields.
 */
abstract class AbstractDianAction
{
    public const ACTION_NAMESPACE = 'http://wcf.dian.colombia/IWcfDianCustomerServices';

    abstract public function operationName(): string;

    /** @return array<int, string> */
    abstract public function requiredParams(): array;

    abstract protected function buildBodyContents(DOMDocument $doc, DOMElement $operationEl, array $params): void;

    public function soapAction(): string
    {
        return self::ACTION_NAMESPACE . '/' . $this->operationName();
    }

    public function buildOperationElement(DOMDocument $doc, array $params): DOMElement
    {
        $this->assertParams($params);
        $operation = $doc->createElementNS(SoapNamespaces::WCF_DIAN, 'wcf:' . $this->operationName());
        $this->buildBodyContents($doc, $operation, $params);
        return $operation;
    }

    protected function assertParams(array $params): void
    {
        foreach ($this->requiredParams() as $key) {
            if (!array_key_exists($key, $params)) {
                throw InvalidSoapPayloadException::for($key);
            }
            $value = $params[$key];
            if ($value === null || $value === '') {
                throw InvalidSoapPayloadException::for($key, 'empty');
            }
        }
    }

    protected function appendText(DOMDocument $doc, DOMElement $parent, string $localName, string $value): void
    {
        $el = $doc->createElementNS(SoapNamespaces::WCF_DIAN, 'wcf:' . $localName);
        $el->appendChild($doc->createTextNode($value));
        $parent->appendChild($el);
    }

    protected function assertBase64(string $value, string $param): void
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            throw InvalidSoapPayloadException::for($param, 'not valid base64');
        }
    }
}
