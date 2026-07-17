<?php

namespace App\Http\Controllers;

use App\Services\DayPassSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DayPassSettingController extends Controller
{
    public function __construct(
        private readonly DayPassSettingsService $dayPassSettingsService
    ) {
    }

    public function index()
    {
        return response()->json($this->dayPassSettingsService->defaults());
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'default_capacity' => 'required|integer|min:1',
            'default_adult_price' => 'required|numeric|min:0',
            'default_child_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $settings = $this->dayPassSettingsService->update($validator->validated());

        return response()->json([
            'message' => 'Configuración de pasadía actualizada correctamente',
            'settings' => $settings,
        ]);
    }
}
