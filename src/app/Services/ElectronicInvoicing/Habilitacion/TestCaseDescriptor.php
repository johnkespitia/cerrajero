<?php

namespace App\Services\ElectronicInvoicing\Habilitacion;

/**
 * Self-describing entry of the DIAN habilitacion test set.
 *
 * Each case is the minimum data the runner needs to drive the existing
 * emission pipeline:
 *  - `code` short identifier, used for logging/reports (e.g. "FEV-01").
 *  - `category` "fev" | "nc" | "nd" so the report can aggregate by type.
 *  - `description` human readable rule under test (e.g. "FEV con
 *    descuento global y IVA discriminado").
 *  - `payload` raw payload accepted by the emitter for the document
 *    type (kept opaque on purpose: each fixture file may use a slightly
 *    different shape and the runner just passes it through).
 *  - `expectations` declarative checks (e.g. `['target_status' =>
 *    'dian_accepted']`). When DIAN returns an unexpected outcome the
 *    runner flags the case as `failed_expectation`.
 *
 * Test cases live next to the runner so we can ship the canonical
 * fixture pack as code (versioned in git) and load custom packs from
 * disk during habilitacion sessions.
 */
final class TestCaseDescriptor
{
    public function __construct(
        public readonly string $code,
        public readonly string $category,
        public readonly string $description,
        public readonly array $payload,
        public readonly array $expectations = ['target_status' => 'dian_accepted'],
    ) {
    }
}
