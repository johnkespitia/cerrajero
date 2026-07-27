<?php

namespace App\Http\Controllers\ElectronicInvoicing;

use App\Http\Controllers\Controller;
use App\Services\ElectronicInvoicing\Habilitacion\HabilitacionTestSetRunner;
use App\Services\ElectronicInvoicing\Habilitacion\TestCaseRepository;
use App\Services\ElectronicInvoicing\Habilitacion\TestSetReportRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HTTP entry point for the DIAN habilitacion test set.
 *
 *  - `POST /api/electronic-invoicing/habilitacion/run-test-set`
 *      Runs the canonical pack (or a user-provided pack on the body)
 *      and returns the full report.
 *  - `GET  /api/electronic-invoicing/habilitacion/latest-report`
 *      Returns the most recent report previously stored.
 *
 * Both require permission `electronic_invoicing.admin`.
 *
 * Note: this endpoint synchronously executes the runner. Long-running
 * scenarios should rely on the artisan command + scheduler. The
 * controller is here so operators can trigger the smoke run from the
 * FiscalAdmin panel during habilitacion sessions.
 */
class HabilitacionController extends Controller
{
    public function runTestSet(
        Request $request,
        HabilitacionTestSetRunner $runner,
        TestSetReportRepository $reports
    ): JsonResponse {
        $validated = $request->validate([
            'test_set_id' => 'sometimes|string|max:200',
            'fixtures' => 'sometimes|array',
        ]);

        $cases = isset($validated['fixtures'])
            ? TestCaseRepository::fromArray($validated['fixtures'])
            : TestCaseRepository::canonical();
        if ($cases === []) {
            return response()->json([
                'error_code' => 'habilitacion_no_cases',
                'message' => 'No hay casos de prueba para ejecutar.',
            ], 422);
        }

        $testSetId = (string) ($validated['test_set_id'] ?? config('electronic-invoicing.test_set_id', ''));
        if ($testSetId === '') {
            return response()->json([
                'error_code' => 'habilitacion_missing_test_set_id',
                'message' => 'No se encontro un TestSetId configurado.',
            ], 422);
        }

        $report = $runner->run($cases, $testSetId);
        $path = $reports->save($report);

        return response()->json([
            'storage_key' => $path,
            'report' => $report->payload(),
        ]);
    }

    public function latestReport(TestSetReportRepository $reports): JsonResponse
    {
        $report = $reports->latest();
        if ($report === null) {
            return response()->json([
                'report' => null,
            ]);
        }

        return response()->json([
            'report' => $report->payload(),
        ]);
    }
}
