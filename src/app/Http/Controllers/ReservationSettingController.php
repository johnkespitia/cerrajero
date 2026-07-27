<?php

namespace App\Http\Controllers;

use App\Models\ReservationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReservationSettingController extends Controller
{
    /**
     * Obtener todas las configuraciones
     */
    public function index()
    {
        $settings = ReservationSetting::all()->pluck('value', 'key')->toArray();
        
        // Asegurar que existan los valores por defecto
        $defaults = [
            'max_advance_days' => '365',
            'min_stay_nights' => '1',
            'max_stay_nights' => '30',
            'max_reservations_per_customer' => '5',
            'check_in_time' => '15:00',
            'check_out_time' => '12:00',
            'web_booking_enabled' => 'true',
            'web_payment_mode_request_enabled' => 'true',
            'web_payment_mode_deposit_enabled' => 'true',
            'web_payment_mode_full_enabled' => 'true',
            'web_deposit_percentage' => '30',
            'web_payment_instructions' => 'Realice la transferencia o consignación y adjunte el comprobante (captura o PDF). Verificaremos el pago para confirmar su reserva.',
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($settings[$key])) {
                ReservationSetting::set($key, $value);
                $settings[$key] = $value;
            }
        }

        return response()->json($settings);
    }

    /**
     * Actualizar configuraciones
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'max_advance_days' => 'nullable|integer|min:1',
            'min_stay_nights' => 'nullable|integer|min:1',
            'max_stay_nights' => 'nullable|integer|min:1',
            'max_reservations_per_customer' => 'nullable|integer|min:1',
            'check_in_time' => ['nullable', 'string', 'regex:/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/'],
            'check_out_time' => ['nullable', 'string', 'regex:/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/'],
            'web_booking_enabled' => 'nullable|in:true,false,0,1',
            'web_payment_mode_request_enabled' => 'nullable|in:true,false,0,1',
            'web_payment_mode_deposit_enabled' => 'nullable|in:true,false,0,1',
            'web_payment_mode_full_enabled' => 'nullable|in:true,false,0,1',
            'web_deposit_percentage' => 'nullable|numeric|min:1|max:100',
            'web_payment_instructions' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $descriptions = [
            'max_advance_days' => 'Días máximos de anticipación para reservar',
            'min_stay_nights' => 'Noches mínimas de estadía',
            'max_stay_nights' => 'Noches máximas de estadía',
            'max_reservations_per_customer' => 'Reservas simultáneas máximas por cliente',
            'check_in_time' => 'Hora de check-in por defecto',
            'check_out_time' => 'Hora de check-out por defecto',
            'web_booking_enabled' => 'Habilitar reservas desde el sitio web',
            'web_payment_mode_request_enabled' => 'Modo solicitud de reserva (web)',
            'web_payment_mode_deposit_enabled' => 'Modo abono/depósito (web)',
            'web_payment_mode_full_enabled' => 'Modo pago completo (web)',
            'web_deposit_percentage' => 'Porcentaje de abono para reservas web',
            'web_payment_instructions' => 'Instrucciones de pago manual para reservas web',
        ];

        $booleanKeys = [
            'web_booking_enabled',
            'web_payment_mode_request_enabled',
            'web_payment_mode_deposit_enabled',
            'web_payment_mode_full_enabled',
        ];

        foreach ($request->all() as $key => $value) {
            if (!isset($descriptions[$key])) {
                continue;
            }

            if (in_array($key, $booleanKeys, true)) {
                $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
                ReservationSetting::set($key, $normalized, $descriptions[$key]);
                continue;
            }

            ReservationSetting::set($key, (string) $value, $descriptions[$key]);
        }

        return response()->json([
            'message' => 'Configuraciones actualizadas correctamente',
            'settings' => ReservationSetting::all()->pluck('value', 'key')->toArray()
        ]);
    }
}




