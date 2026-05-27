<?php

namespace App\Services\ElectronicInvoicing\Habilitacion;

use App\Domain\ElectronicInvoicing\Ports\CertificateProviderInterface;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Infrastructure\ElectronicInvoicing\Xades\XadesEpesSigner;

/**
 * Default emitter used by the runner when no production pipeline is
 * available. Produces a minimal UBL `Invoice` skeleton with stable
 * placeholders and signs it with the active P12 certificate.
 *
 * This is enough to exercise:
 *  - The XAdES-EPES signer (asserts the certificate is healthy).
 *  - The DIAN ZIP packager.
 *  - The SOAP `sendTestSetAsync` call + response mapping.
 *
 * It deliberately does NOT mimic every Annex Tecnico rule (no line
 * items, no tax totals); we rely on the canonical builders during the
 * real habilitacion session. The synthetic emitter is used to verify
 * the *pipe* before introducing the full builder stack.
 */
class SyntheticTestCaseEmitter implements TestCaseEmitterInterface
{
    public function __construct(
        private readonly CertificateProviderInterface $certificateProvider,
        private readonly XadesEpesSigner $signer,
        private readonly int $companyId,
        private readonly string $environment = FiscalEnvironment::HABILITACION,
        private readonly string $nit = '900123456',
    ) {
    }

    public function emit(TestCaseDescriptor $case): array
    {
        $material = $this->certificateProvider->load($this->companyId, $this->environment);
        $signed = $this->signer->signWithMaterial($this->buildSyntheticXml($case), $material);
        $consecutive = preg_replace('/[^0-9A-Za-z]/', '', $case->code) ?: $case->code;
        $fileName = sprintf('fv%s-%s.xml', $this->nit, strtolower($consecutive));

        return [
            'file_name' => $fileName,
            'signed_xml' => $signed,
            'dian_number' => $consecutive,
        ];
    }

    private function buildSyntheticXml(TestCaseDescriptor $case): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $id = htmlspecialchars($case->code, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $desc = htmlspecialchars($case->description, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">
  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
  <cbc:CustomizationID>10</cbc:CustomizationID>
  <cbc:ProfileID>DIAN 2.1: Habilitacion</cbc:ProfileID>
  <cbc:ProfileExecutionID>2</cbc:ProfileExecutionID>
  <cbc:ID>{$id}</cbc:ID>
  <cbc:UUID schemeID="2" schemeName="CUFE-SHA384"></cbc:UUID>
  <cbc:IssueDate>{$now}</cbc:IssueDate>
  <cbc:Note>{$desc}</cbc:Note>
</Invoice>
XML;
    }
}
