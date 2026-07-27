<?php

namespace App\Domain\ElectronicInvoicing\Ports;

/**
 * Signs an unsigned UBL XML body using XAdES-EPES with the DIAN signature
 * policy.
 *
 * Algorithm: RSA-SHA256.
 * Canonicalization: Exclusive XML Canonicalization (xml-exc-c14n#).
 *
 * Concrete impl: App\Infrastructure\ElectronicInvoicing\Xades\XadesEpesSigner.
 */
interface XadesSignerInterface
{
    /**
     * Returns the XML with the XAdES-EPES signature embedded under
     * /Invoice/ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent/ds:Signature.
     *
     * @param string $unsignedXml UBL XML without ds:Signature.
     * @param string $certificateAlias Logical alias of the active certificate.
     * @return string Signed UBL XML.
     */
    public function sign(string $unsignedXml, string $certificateAlias): string;
}
