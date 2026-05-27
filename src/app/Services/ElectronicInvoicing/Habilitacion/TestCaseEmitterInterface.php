<?php

namespace App\Services\ElectronicInvoicing\Habilitacion;

/**
 * Strategy that materialises a habilitacion test case as a signed UBL XML.
 *
 * The runner does NOT know how the XML is produced. Implementations
 * may:
 *  - Build a synthetic UBL skeleton (default for smoke runs).
 *  - Invoke the production `DocumentEmitter` against a seeded
 *    `CompanyFiscalProfile` to exercise the real pipeline (used in
 *    integration sessions).
 *  - Replay a prebaked XML from disk to test specific edge cases.
 *
 * The contract is pure: return the signed XML as a UTF-8 string. Any
 * persistence / DB side effect is the implementation's business.
 */
interface TestCaseEmitterInterface
{
    /**
     * @return array{file_name?: string, signed_xml: string, dian_number?: string}
     */
    public function emit(TestCaseDescriptor $case): array;
}
