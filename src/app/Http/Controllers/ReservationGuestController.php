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
}



