<?php

namespace App\Http\Controllers\ElectronicInvoicing;

use App\Http\Controllers\Controller;
use App\Services\ElectronicInvoicing\Cutover\CutoverReadinessService;
use Illuminate\Http\JsonResponse;

class CutoverReadinessController extends Controller
{
    public function __construct(private readonly CutoverReadinessService $service)
    {
    }

    public function show(): JsonResponse
    {
        $payload = $this->service->evaluate();
        $status = $payload['ready'] ? 200 : 409;
        return response()->json($payload, $status);
    }
}
