<?php

namespace App\Services\ElectronicInvoicing\Dispatch;

use App\Models\ElectronicDocument;
use RuntimeException;
use ZipArchive;

/**
 * Packages a signed UBL XML into the ZIP container expected by DIAN
 * `SendBillSync` / `SendBillAsync` operations.
 *
 * Resolution 000165 Annex 1.7.-c specifies:
 *   - File name: `<tipo><nit>-<consecutivo>.zip`
 *     (e.g. `fv900123456-990000001.zip`)
 *   - Inside the ZIP: a single `*.xml` file with the signed UBL.
 *   - `contentFile` SOAP parameter is the base64 of the ZIP bytes.
 *
 * The packager is pure and does not touch storage. It produces a
 * temporary file on the local filesystem only when ZipArchive requires
 * it (PHP cannot construct an in-memory ZIP without the extension),
 * deleting it before returning.
 */
final class DianZipPackager
{
    public function package(ElectronicDocument $document, string $signedXml): array
    {
        if ($signedXml === '') {
            throw new RuntimeException('Cannot package an empty signed XML.');
        }
        $fileName = $this->resolveFileName($document);
        $xmlName = $this->xmlFileName($document);

        $tmp = tempnam(sys_get_temp_dir(), 'dian-zip-');
        if ($tmp === false) {
            throw new RuntimeException('Could not allocate a temporary file for the DIAN ZIP.');
        }

        try {
            $zip = new ZipArchive();
            $opened = $zip->open($tmp, ZipArchive::OVERWRITE | ZipArchive::CREATE);
            if ($opened !== true) {
                throw new RuntimeException('Could not create DIAN ZIP archive.');
            }
            $zip->addFromString($xmlName, $signedXml);
            $zip->close();

            $bytes = file_get_contents($tmp);
            if ($bytes === false) {
                throw new RuntimeException('Could not read back the DIAN ZIP archive.');
            }
        } finally {
            @unlink($tmp);
        }

        return [
            'file_name' => $fileName,
            'xml_name' => $xmlName,
            'zip_bytes' => $bytes,
            'zip_base64' => base64_encode($bytes),
            'zip_sha256' => hash('sha256', $bytes),
        ];
    }

    private function resolveFileName(ElectronicDocument $document): string
    {
        $prefix = $this->prefixFor((string) $document->document_type);
        $nit = $this->nitFor($document);
        $consec = preg_replace('/[^0-9]/', '', (string) $document->dian_number) ?: '0';

        return sprintf('%s%s-%s.zip', $prefix, $nit, $consec);
    }

    private function xmlFileName(ElectronicDocument $document): string
    {
        $prefix = $this->prefixFor((string) $document->document_type);
        $nit = $this->nitFor($document);
        $consec = preg_replace('/[^0-9]/', '', (string) $document->dian_number) ?: '0';

        return sprintf('%s%s-%s.xml', $prefix, $nit, $consec);
    }

    private function prefixFor(string $documentType): string
    {
        switch ($documentType) {
            case 'fev':
                return 'fv';
            case 'dee_pos':
                return 'dp';
            case 'nc':
                return 'nc';
            case 'nd':
                return 'nd';
            case 'application_response':
                return 'ar';
            default:
                return 'fv';
        }
    }

    private function nitFor(ElectronicDocument $document): string
    {
        $company = $document->company;
        $nit = $company !== null ? (string) ($company->nit ?? '') : '';
        $nit = preg_replace('/[^0-9]/', '', $nit) ?: '0';

        return $nit;
    }
}
