<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationGuest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReservationGuestController extends Controller
{
    public function index(Reservation $reservation)
    {
        $guests = $reservation->guests()->orderBy('is_primary_guest', 'desc')->get();
        return response()->json($guests);
    }

    public function store(Request $request, Reservation $reservation)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'document_type' => 'nullable|string|max:20',
            'document_number' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'nationality' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:200',
            'phone' => 'nullable|string|max:20',
            'special_needs' => 'nullable|string|max:500',
            'is_primary_guest' => 'nullable|boolean',
            'health_insurance_name' => 'nullable|string|max:200',
            'health_insurance_type' => 'nullable|in:national,international',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Verificar si ya existe un huésped con el mismo documento en esta reserva
        if ($request->document_number) {
            $existingGuest = $reservation->guests()
                ->where('document_number', $request->document_number)
                ->where('document_type', $request->document_type ?? 'CC')
                ->first();
            
            if ($existingGuest) {
                // Si existe, actualizar en lugar de crear
                if ($request->is_primary_guest) {
                    $reservation->guests()->where('id', '!=', $existingGuest->id)->update(['is_primary_guest' => false]);
                }
                
                $existingGuest->update($request->all());
                return response()->json($existingGuest);
            }
        }

        if ($request->is_primary_guest) {
            $reservation->guests()->update(['is_primary_guest' => false]);
        }

        $guest = $reservation->guests()->create($request->all());

        return response()->json($guest, 201);
    }

    public function update(Request $request, Reservation $reservation, ReservationGuest $guest)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'document_type' => 'nullable|string|max:20',
            'document_number' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'nationality' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:200',
            'phone' => 'nullable|string|max:20',
            'special_needs' => 'nullable|string|max:500',
            'is_primary_guest' => 'nullable|boolean',
            'health_insurance_name' => 'nullable|string|max:200',
            'health_insurance_type' => 'nullable|in:national,international',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->is_primary_guest) {
            $reservation->guests()->where('id', '!=', $guest->id)->update(['is_primary_guest' => false]);
        }

        $guest->update($request->all());

        return response()->json($guest);
    }

    public function destroy(Reservation $reservation, ReservationGuest $guest)
    {
        $guest->delete();
        return response()->json(null, 204);
    }

    /**
     * Limpiar huéspedes duplicados de una reserva
     * Mantiene solo el más reciente de cada documento
     */
    public function removeDuplicates(Reservation $reservation)
    {
        $guests = $reservation->guests()->get();
        $seen = [];
        $duplicates = [];

        foreach ($guests as $guest) {
            if (!$guest->document_number) {
                continue; // Saltar huéspedes sin documento
            }

            $key = strtolower(trim($guest->document_number)) . '|' . ($guest->document_type ?? 'CC');
            
            if (isset($seen[$key])) {
                // Es un duplicado, mantener el más reciente
                if ($guest->created_at > $seen[$key]->created_at) {
                    $duplicates[] = $seen[$key];
                    $seen[$key] = $guest;
                } else {
                    $duplicates[] = $guest;
                }
            } else {
                $seen[$key] = $guest;
            }
        }

        // Eliminar duplicados
        $deletedCount = 0;
        foreach ($duplicates as $duplicate) {
            $duplicate->delete();
            $deletedCount++;
        }

        return response()->json([
            'message' => "Se eliminaron {$deletedCount} huéspedes duplicados",
            'deleted_count' => $deletedCount,
            'remaining_guests' => $reservation->guests()->count()
        ]);
    }
}

