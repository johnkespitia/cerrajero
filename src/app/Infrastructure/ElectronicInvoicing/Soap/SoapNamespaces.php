<?php

namespace App\Infrastructure\ElectronicInvoicing\Soap;

/**
 * Canonical SOAP / WS-Security / DIAN WCF namespace URIs.
 *
 * DIAN exposes WcfDianCustomerServices via SOAP 1.1; we keep both 1.1 and 1.2
 * URIs available so the client can switch envelope binding if the published
 * service changes versions.
 */
final class SoapNamespaces
{
    public const SOAP_1_1 = 'http://schemas.xmlsoap.org/soap/envelope/';
    public const SOAP_1_2 = 'http://www.w3.org/2003/05/soap-envelope';

    public const WSSE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
    public const WSU = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
    public const DS = 'http://www.w3.org/2000/09/xmldsig#';

    public const WCF_DIAN = 'http://wcf.dian.colombia';

    public const EXC_C14N = 'http://www.w3.org/2001/10/xml-exc-c14n#';
    public const SIG_RSA_SHA256 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
    public const DIGEST_SHA256 = 'http://www.w3.org/2001/04/xmlenc#sha256';

    public const X509_ENCODING_BASE64 = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary';
    public const X509_VALUE_TYPE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3';

    private function __construct()
    {
    }
}
