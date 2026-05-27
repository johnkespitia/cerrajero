<?php

namespace App\Services\ElectronicInvoicing\Cutover;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\ElectronicDocument;
use App\Models\FiscalCertificate;
use App\Services\ElectronicInvoicing\Contingency\CircuitBreakerInterface;
use App\Services\ElectronicInvoicing\Habilitacion\TestSetReportRepository;
use Carbon\Carbon;

/**
 * Snapshot of every precondition required to promote the system to
 * production.
 *
 * The service NEVER mutates anything: it only inspects DB rows, the
 * latest habilitacion test-set report and the circuit-breaker state.
 * The dashboard (and the artisan command) call this to decide whether
 * the cutover gate can be closed.
 *
 * Each rule contributes an entry to either `blockers` (must be fixed)
 * or `warnings` (best-effort). A run with `blockers === []` flips
 * `ready` to true; the operator still has to manually confirm the
 * promotion in ops.
 *
 * The rules are conservative on purpose: feature flags are inspected
 * but never auto-flipped here, so this service is safe to call even
 * when facturacion electronica is globally disabled (the result will
 * just enumerate every reason why we are not ready).
 */
class CutoverReadinessService
{
    public function __construct(
        private readonly ?CircuitBreakerInterface $breaker = null,
        private readonly ?TestSetReportRepository $reportRepository = null,
        private readonly int $certificateMinDaysValid = 90,
        private readonly int $resolutionMinDaysValid = 60,
        private readonly int $resolutionMinRangeRemaining = 1000,
    ) {
    }

    /**
     * @return array{
     *     ready: bool,
     *     environment: string,
     *     blockers: array<int, array{code:string, message:string, details?:array}>,
     *     warnings: array<int, array{code:string, message:string, details?:array}>,
     *     checks: array<string, array{status:string, details?:array}>
     * }
     */
    public function evaluate(): array
    {
        $environment = (string) config('electronic-invoicing.environment', FiscalEnvironment::HABILITACION);
        $blockers = [];
        $warnings = [];
        $checks = [];

        $checks['module_enabled'] = $this->moduleEnabled($warnings);
        $checks['signing_enabled'] = $this->signingEnabled($warnings);
        $checks['dispatch_enabled'] = $this->dispatchEnabled($warnings);
        $checks['company_profile'] = $this->companyProfile($environment, $blockers);
        $checks['certificate'] = $this->certificate($environment, $blockers, $warnings);
        $checks['resolution'] = $this->resolution($environment, $blockers, $warnings);
        $checks['dead_letter'] = $this->deadLetter($blockers);
        $checks['circuit_breaker'] = $this->circuitBreaker($warnings);
        $checks['habilitacion_test_set'] = $this->habilitacionTestSet($blockers);

        return [
            'ready' => $blockers === [],
            'environment' => $environment,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'checks' => $checks,
        ];
    }

    private function moduleEnabled(array &$warnings): array
    {
        $enabled = (bool) config('electronic-invoicing.enabled', false);
        if (! $enabled) {
            $warnings[] = [
                'code' => 'module_disabled',
                'message' => 'electronic-invoicing.enabled = false. The platform will not emit any document until the flag is flipped to true.',
            ];
        }
        return ['status' => $enabled ? 'ok' : 'warn'];
    }

    private function signingEnabled(array &$warnings): array
    {
        $enabled = (bool) config('electronic-invoicing.signing.enabled', false);
        if (! $enabled) {
            $warnings[] = [
                'code' => 'signing_disabled',
                'message' => 'electronic-invoicing.signing.enabled = false. Documents will stop at ubl_built.',
            ];
        }
        return ['status' => $enabled ? 'ok' : 'warn'];
    }

    private function dispatchEnabled(array &$warnings): array
    {
        $enabled = (bool) config('electronic-invoicing.dispatch.enabled', false);
        if (! $enabled) {
            $warnings[] = [
                'code' => 'dispatch_disabled',
                'message' => 'electronic-invoicing.dispatch.enabled = false. Signed documents will not reach DIAN.',
            ];
        }
        return ['status' => $enabled ? 'ok' : 'warn'];
    }

    private function companyProfile(string $environment, array &$blockers): array
    {
        $profile = CompanyFiscalProfile::query()
            ->where('active', true)
            ->where('environment', $environment)
            ->first();
        if ($profile === null) {
            $blockers[] = [
                'code' => 'company_profile_missing',
                'message' => sprintf('No active CompanyFiscalProfile for environment [%s].', $environment),
            ];
            return ['status' => 'error'];
        }

        return [
            'status' => 'ok',
            'details' => [
                'id' => (int) $profile->id,
                'nit' => (string) $profile->nit,
                'environment' => (string) $profile->environment,
            ],
        ];
    }

    private function certificate(string $environment, array &$blockers, array &$warnings): array
    {
        $cert = FiscalCertificate::query()
            ->where('active', true)
            ->where('environment', $environment)
            ->orderByDesc('not_after')
            ->first();
        if ($cert === null) {
            $blockers[] = [
                'code' => 'certificate_missing',
                'message' => sprintf('No active FiscalCertificate for environment [%s].', $environment),
            ];
            return ['status' => 'error'];
        }
        $notAfter = $cert->not_after instanceof \DateTimeInterface
            ? Carbon::instance($cert->not_after instanceof Carbon ? $cert->not_after : new Carbon($cert->not_after))
            : null;
        if ($notAfter === null) {
            $blockers[] = [
                'code' => 'certificate_missing_not_after',
                'message' => 'Active certificate has no not_after date.',
            ];
            return ['status' => 'error'];
        }
        $now = Carbon::now();
        if ($notAfter->lessThan($now)) {
            $blockers[] = [
                'code' => 'certificate_expired',
                'message' => 'Active certificate is already expired.',
                'details' => ['not_after' => $notAfter->toIso8601String()],
            ];
            return ['status' => 'error'];
        }
        $daysRemaining = $now->diffInDays($notAfter, false);
        if ($daysRemaining < $this->certificateMinDaysValid) {
            $blockers[] = [
                'code' => 'certificate_near_expiry',
                'message' => sprintf(
                    'Active certificate expires in %d days (minimum %d).',
                    $daysRemaining,
                    $this->certificateMinDaysValid
                ),
                'details' => ['not_after' => $notAfter->toIso8601String()],
            ];
            return ['status' => 'error'];
        }
        if ($daysRemaining < ($this->certificateMinDaysValid + 30)) {
            $warnings[] = [
                'code' => 'certificate_rotation_soon',
                'message' => sprintf('Plan certificate rotation: only %d days remaining.', $daysRemaining),
            ];
        }

        return [
            'status' => 'ok',
            'details' => [
                'id' => (int) $cert->id,
                'subject_cn' => (string) $cert->subject_cn,
                'not_after' => $notAfter->toIso8601String(),
                'days_remaining' => $daysRemaining,
            ],
        ];
    }

    private function resolution(string $environment, array &$blockers, array &$warnings): array
    {
        $resolution = DianResolution::query()
            ->where('active', true)
            ->where('environment', $environment)
            ->orderByDesc('valid_to')
            ->first();
        if ($resolution === null) {
            $blockers[] = [
                'code' => 'resolution_missing',
                'message' => sprintf('No active DianResolution for environment [%s].', $environment),
            ];
            return ['status' => 'error'];
        }
        $validTo = $resolution->valid_to instanceof \DateTimeInterface
            ? Carbon::instance($resolution->valid_to instanceof Carbon ? $resolution->valid_to : new Carbon($resolution->valid_to))
            : null;
        $now = Carbon::now();
        if ($validTo === null || $validTo->lessThan($now)) {
            $blockers[] = [
                'code' => 'resolution_expired',
                'message' => 'Active resolution is expired.',
            ];
            return ['status' => 'error'];
        }
        $daysRemaining = $now->diffInDays($validTo, false);
        if ($daysRemaining < $this->resolutionMinDaysValid) {
            $blockers[] = [
                'code' => 'resolution_near_expiry',
                'message' => sprintf('Active resolution expires in %d days (minimum %d).', $daysRemaining, $this->resolutionMinDaysValid),
            ];
            return ['status' => 'error'];
        }
        $remaining = max(0, (int) $resolution->to_number - (int) $resolution->current_number);
        if ($remaining < $this->resolutionMinRangeRemaining) {
            $blockers[] = [
                'code' => 'resolution_range_low',
                'message' => sprintf('Only %d numbers left in the active resolution (minimum %d).', $remaining, $this->resolutionMinRangeRemaining),
            ];
            return ['status' => 'error'];
        }

        return [
            'status' => 'ok',
            'details' => [
                'id' => (int) $resolution->id,
                'prefix' => (string) $resolution->prefix,
                'valid_to' => $validTo->toIso8601String(),
                'days_remaining' => $daysRemaining,
                'numbers_remaining' => $remaining,
            ],
        ];
    }

    private function deadLetter(array &$blockers): array
    {
        $count = ElectronicDocument::query()
            ->where('status', DocumentStatus::DEAD_LETTER)
            ->count();
        if ($count > 0) {
            $blockers[] = [
                'code' => 'dead_letter_present',
                'message' => sprintf('There are %d ElectronicDocument(s) in DEAD_LETTER status. Resolve before cutover.', $count),
            ];
            return ['status' => 'error', 'details' => ['count' => $count]];
        }
        return ['status' => 'ok', 'details' => ['count' => 0]];
    }

    private function circuitBreaker(array &$warnings): array
    {
        if ($this->breaker === null) {
            return ['status' => 'unknown'];
        }
        $state = $this->breaker->state();
        if ($state !== CircuitBreakerInterface::STATE_CLOSED) {
            $warnings[] = [
                'code' => 'breaker_not_closed',
                'message' => sprintf('Circuit breaker is in state [%s]. Wait for recovery before cutover.', $state),
            ];
        }
        return [
            'status' => $state === CircuitBreakerInterface::STATE_CLOSED ? 'ok' : 'warn',
            'details' => ['state' => $state],
        ];
    }

    private function habilitacionTestSet(array &$blockers): array
    {
        if ($this->reportRepository === null) {
            $blockers[] = [
                'code' => 'test_set_report_unavailable',
                'message' => 'Habilitacion test-set report repository is not wired.',
            ];
            return ['status' => 'error'];
        }
        $report = $this->reportRepository->latest();
        if ($report === null) {
            $blockers[] = [
                'code' => 'test_set_not_executed',
                'message' => 'Run electronic-invoicing:run-test-set before cutover.',
            ];
            return ['status' => 'error'];
        }
        if (! $report->isCutoverReady()) {
            $blockers[] = [
                'code' => 'test_set_not_full_pass',
                'message' => sprintf(
                    'Latest habilitacion test set acceptance is %.2f%% (need 100%%).',
                    $report->acceptanceRate()
                ),
            ];
            return [
                'status' => 'error',
                'details' => [
                    'acceptance_rate' => $report->acceptanceRate(),
                    'total' => $report->total(),
                ],
            ];
        }

        return [
            'status' => 'ok',
            'details' => [
                'acceptance_rate' => $report->acceptanceRate(),
                'total' => $report->total(),
            ],
        ];
    }
}
