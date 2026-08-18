<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Services\GuestAgeClassifier;
use App\Services\GuestImportService;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReservationGuestController extends Controller
{
    protected GuestImportService $guestImportService;
    protected GoogleCalendarService $googleCalendarService;
    protected GuestAgeClassifier $guestAgeClassifier;

    public function __construct(
        GuestImportService $guestImportService,
        GoogleCalendarService $googleCalendarService,
        GuestAgeClassifier $guestAgeClassifier
    ) {
        $this->guestImportService = $guestImportService;
        $this->googleCalendarService = $googleCalendarService;
        $this->guestAgeClassifier = $guestAgeClassifier;
    }

    protected function syncReservationToGoogleCalendar(Reservation $reservation): void
    {
        $this->googleCalendarService->syncReservation($reservation);
    }

    public function downloadTemplate(Request $request)
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));
        if ($format === 'csv') {
            $path = resource_path('templates/plantilla-huespedes.csv');
            if (!is_readable($path)) {
                return response()->json([
                    'message' => 'Plantilla CSV no disponible en el servidor.',
                ], 503);
            }

            return response()->download(
                $path,
                'plantilla-huespedes.csv',
                ['Content-Type' => 'text/csv; charset=UTF-8']
            );
        }

        $path = resource_path('templates/plantilla-huespedes.xlsx');
        if (is_readable($path)) {
            return response()->download(
                $path,
                'plantilla-huespedes.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            );
        }

        try {
            $generatedPath = $this->guestImportService->buildTemplate();

            return response()->download(
                $generatedPath,
                'plantilla-huespedes.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            )->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'No se pudo generar la plantilla Excel.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function previewImport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120',
            'max_guests' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $result = $this->guestImportService->parseFile($request->file('file'));
        if (!empty($result['errors']) && empty($result['guests'])) {
            return response()->json([
                'message' => 'No se pudo procesar el archivo',
                'errors' => $result['errors'],
            ], 422);
        }

        $maxGuests = $request->integer('max_guests');
        if ($maxGuests > 0 && count($result['guests']) > $maxGuests) {
            return response()->json([
                'message' => "El archivo contiene " . count($result['guests']) . " huéspedes pero la reserva admite máximo {$maxGuests}.",
                'errors' => $result['errors'],
            ], 422);
        }

        return response()->json([
            'guests' => $result['guests'],
            'errors' => $result['errors'],
            'imported_count' => count($result['guests']),
        ]);
    }

    public function import(Request $request, Reservation $reservation)
    {
        if (in_array($reservation->status, ['checked_in', 'checked_out'], true)) {
            return response()->json([
                'message' => 'No se pueden importar huéspedes en reservas con check-in o check-out realizado.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $result = $this->guestImportService->parseFile($request->file('file'));
        if (!empty($result['errors']) && empty($result['guests'])) {
            return response()->json([
                'message' => 'No se pudo procesar el archivo',
                'errors' => $result['errors'],
            ], 422);
        }

        $maxGuests = $this->getReservationGuestCapacity($reservation);
        $currentCount = $reservation->guests()->count();
        $availableSlots = max(0, $maxGuests - $currentCount);

        $newGuestsCount = 0;
        foreach ($result['guests'] as $guestData) {
            if (empty($guestData['document_number'])) {
                $newGuestsCount++;
                continue;
            }

            $exists = $reservation->guests()
                ->where('document_number', $guestData['document_number'])
                ->where('document_type', $guestData['document_type'] ?? 'CC')
                ->exists();

            if (!$exists) {
                $newGuestsCount++;
            }
        }

        if ($newGuestsCount > $availableSlots) {
            return response()->json([
                'message' => "El archivo agregaría {$newGuestsCount} huésped(es) nuevo(s) pero solo quedan {$availableSlots} cupo(s) disponible(s).",
                'errors' => $result['errors'],
            ], 422);
        }

        if ($availableSlots <= 0 && $newGuestsCount > 0) {
            return response()->json([
                'message' => "La reserva ya tiene registrados {$currentCount} de {$maxGuests} huéspedes permitidos.",
            ], 422);
        }

        if (count($result['guests']) > $availableSlots) {
            // Mantener compatibilidad cuando no hay huéspedes previos y el archivo excede el total.
            if ($currentCount === 0 && count($result['guests']) > $maxGuests) {
                return response()->json([
                    'message' => "El archivo contiene " . count($result['guests']) . " huéspedes pero la reserva admite máximo {$maxGuests}.",
                    'errors' => $result['errors'],
                ], 422);
            }
        }

        $created = 0;
        $updated = 0;
        $importErrors = $result['errors'];

        foreach ($result['guests'] as $guestData) {
            try {
                $saved = $this->upsertGuest($reservation, $guestData);
                if ($saved['created']) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (\Throwable $e) {
                $importErrors[] = [
                    'row' => 0,
                    'message' => ($guestData['first_name'] ?? '') . ' ' . ($guestData['last_name'] ?? '') . ': ' . $e->getMessage(),
                ];
            }
        }

        $this->normalizePrimaryGuest($reservation);
        $this->syncReservationToGoogleCalendar($reservation);

        return response()->json([
            'message' => "Importación completada: {$created} creado(s), {$updated} actualizado(s).",
            'created' => $created,
            'updated' => $updated,
            'errors' => $importErrors,
            'guests' => $reservation->guests()->orderBy('is_primary_guest', 'desc')->get(),
        ]);
    }

    protected function getReservationGuestCapacity(Reservation $reservation): int
    {
        $total = (int) $reservation->adults + (int) $reservation->children + (int) $reservation->infants;

        if ($reservation->is_group_reservation) {
            $reservation->loadMissing('childReservations');
            foreach ($reservation->childReservations as $child) {
                $total += (int) $child->adults + (int) $child->children + (int) $child->infants;
            }
        }

        return max(1, $total);
    }

    /**
     * @param array<string, mixed> $guestData
     * @return array{guest: ReservationGuest, created: bool}
     */
    protected function upsertGuest(Reservation $reservation, array $guestData): array
    {
        $existingGuest = null;
        if (!empty($guestData['document_number'])) {
            $existingGuest = $reservation->guests()
                ->where('document_number', $guestData['document_number'])
                ->where('document_type', $guestData['document_type'] ?? 'CC')
                ->first();
        }

        $guestData = $this->classifyGuestPayload($guestData, $reservation);

        if ($existingGuest) {
            $existingGuest->update($guestData);

            return ['guest' => $existingGuest->fresh(), 'created' => false];
        }

        $guest = $reservation->guests()->create($guestData);

        return ['guest' => $guest, 'created' => true];
    }

    /**
     * @param array<string, mixed> $guestData
     * @return array<string, mixed>
     */
    protected function classifyGuestPayload(array $guestData, Reservation $reservation): array
    {
        return $this->guestAgeClassifier->applyToGuestPayload(
            $guestData,
            $reservation->check_in_date
        );
    }

    protected function normalizePrimaryGuest(Reservation $reservation): void
    {
        $guests = $reservation->guests()->orderBy('id')->get();
        if ($guests->isEmpty()) {
            return;
        }

        $primary = $guests->firstWhere('is_primary_guest', true) ?? $guests->first();
        $reservation->guests()->update(['is_primary_guest' => false]);
        $primary->update(['is_primary_guest' => true]);
    }

    public function index(Reservation $reservation)
    {
        $guests = $reservation->guests()->orderBy('is_primary_guest', 'desc')->get();
        return response()->json($guests);
    }

    /**
     * Busca un huésped previo o cliente registrado por documento para prellenar formularios.
     */
    public function lookup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_number' => 'required|string|max:50',
            'document_type' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $documentNumber = trim((string) $request->input('document_number'));
        if ($documentNumber === '') {
            return response()->json(['found' => false]);
        }

        $documentType = $request->filled('document_type')
            ? trim((string) $request->input('document_type'))
            : null;

        $previousGuest = $this->findPreviousGuest($documentNumber, $documentType);
        $customer = $this->findCustomerByDocument($documentNumber);

        if (!$previousGuest && !$customer) {
            return response()->json(['found' => false]);
        }

        $guestPayload = $this->buildLookupGuestPayload($previousGuest, $customer, $documentNumber, $documentType);
        $source = $previousGuest && $customer
            ? 'both'
            : ($previousGuest ? 'guest' : 'customer');

        return response()->json([
            'found' => true,
            'source' => $source,
            'guest' => $guestPayload,
        ]);
    }

    protected function findPreviousGuest(string $documentNumber, ?string $documentType): ?ReservationGuest
    {
        $query = ReservationGuest::query()
            ->where('document_number', $documentNumber)
            ->whereNotNull('first_name')
            ->where('first_name', '!=', '')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($documentType) {
            $typed = (clone $query)->where('document_type', $documentType)->first();
            if ($typed) {
                return $typed;
            }
        }

        return $query->first();
    }

    protected function findCustomerByDocument(string $documentNumber): ?Customer
    {
        return Customer::query()
            ->where(function ($q) use ($documentNumber) {
                $q->where('dni', $documentNumber)
                    ->orWhere('company_nit', $documentNumber);
            })
            ->first();
    }

    protected function buildLookupGuestPayload(
        ?ReservationGuest $previousGuest,
        ?Customer $customer,
        string $documentNumber,
        ?string $documentType
    ): array {
        $fromGuest = $previousGuest ? [
            'first_name' => $previousGuest->first_name,
            'last_name' => $previousGuest->last_name,
            'document_type' => $previousGuest->document_type ?: ($documentType ?: 'CC'),
            'document_number' => $previousGuest->document_number ?: $documentNumber,
            'birth_date' => optional($previousGuest->birth_date)->format('Y-m-d'),
            'gender' => $previousGuest->gender,
            'nationality' => $previousGuest->nationality,
            'email' => $previousGuest->email,
            'phone' => $previousGuest->phone,
            'health_insurance_name' => $previousGuest->health_insurance_name,
            'health_insurance_type' => $previousGuest->health_insurance_type ?: 'national',
            'special_needs' => $previousGuest->special_needs,
            'is_infant' => (bool) $previousGuest->is_infant,
            'is_child' => (bool) $previousGuest->is_child,
        ] : [];

        $fromCustomer = [];
        if ($customer) {
            if ($customer->customer_type === 'company') {
                $fromCustomer = [
                    'first_name' => $customer->company_name ?: $customer->company_legal_representative,
                    'last_name' => $customer->company_legal_representative ?: '',
                    'document_type' => $documentType ?: 'NIT',
                    'document_number' => $customer->company_nit ?: $documentNumber,
                    'email' => $customer->email,
                    'phone' => $customer->phone_number,
                ];
            } else {
                $fromCustomer = [
                    'first_name' => $customer->name,
                    'last_name' => $customer->last_name,
                    'document_type' => $documentType ?: 'CC',
                    'document_number' => $customer->dni ?: $documentNumber,
                    'email' => $customer->email,
                    'phone' => $customer->phone_number,
                ];
            }
        }

        $fields = [
            'first_name',
            'last_name',
            'document_type',
            'document_number',
            'birth_date',
            'gender',
            'nationality',
            'email',
            'phone',
            'health_insurance_name',
            'health_insurance_type',
            'special_needs',
        ];

        $merged = [];
        foreach ($fields as $field) {
            $guestValue = $fromGuest[$field] ?? null;
            $customerValue = $fromCustomer[$field] ?? null;
            $value = ($guestValue !== null && $guestValue !== '')
                ? $guestValue
                : $customerValue;
            $merged[$field] = $value !== null && $value !== '' ? $value : null;
        }

        $merged['is_infant'] = (bool) ($fromGuest['is_infant'] ?? false);
        $merged['is_child'] = (bool) ($fromGuest['is_child'] ?? false);

        if (empty($merged['document_number'])) {
            $merged['document_number'] = $documentNumber;
        }
        if (empty($merged['document_type'])) {
            $merged['document_type'] = $documentType ?: 'CC';
        }
        if (empty($merged['health_insurance_type'])) {
            $merged['health_insurance_type'] = 'national';
        }

        return $merged;
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
            'is_infant' => 'nullable|boolean',
            'is_child' => 'nullable|boolean',
            'health_insurance_name' => 'nullable|string|max:200',
            'health_insurance_type' => 'nullable|in:national,international',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $this->classifiedGuestAttributes($request, $reservation);

        // Verificar si ya existe un huésped con el mismo documento en esta reserva
        if (!empty($payload['document_number'])) {
            $existingGuest = $reservation->guests()
                ->where('document_number', $payload['document_number'])
                ->where('document_type', $payload['document_type'] ?? 'CC')
                ->first();
            
            if ($existingGuest) {
                // Si existe, actualizar en lugar de crear
                if (!empty($payload['is_primary_guest'])) {
                    $reservation->guests()->where('id', '!=', $existingGuest->id)->update(['is_primary_guest' => false]);
                }
                
                $existingGuest->update($payload);
                $this->syncReservationToGoogleCalendar($reservation);
                return response()->json($existingGuest->fresh());
            }
        }

        if (!empty($payload['is_primary_guest'])) {
            $reservation->guests()->update(['is_primary_guest' => false]);
        }

        $guest = $reservation->guests()->create($payload);
        $this->syncReservationToGoogleCalendar($reservation);

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
            'is_infant' => 'nullable|boolean',
            'is_child' => 'nullable|boolean',
            'health_insurance_name' => 'nullable|string|max:200',
            'health_insurance_type' => 'nullable|in:national,international',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $validated = $validator->validated();
        $resolved = $this->guestAgeClassifier->resolve(
            $request->has('birth_date')
                ? $request->input('birth_date')
                : optional($guest->birth_date)->format('Y-m-d'),
            $request->has('is_infant')
                ? $request->boolean('is_infant')
                : (bool) $guest->is_infant,
            $request->has('is_child')
                ? $request->boolean('is_child')
                : (bool) $guest->is_child,
            $reservation->check_in_date
        );

        $payload = array_merge($validated, [
            'is_infant' => $resolved['is_infant'],
            'is_child' => $resolved['is_child'],
        ]);

        if (!empty($payload['is_primary_guest'])) {
            $reservation->guests()->where('id', '!=', $guest->id)->update(['is_primary_guest' => false]);
        }

        $guest->update($payload);
        $this->syncReservationToGoogleCalendar($reservation);

        return response()->json($guest->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    protected function classifiedGuestAttributes(Request $request, Reservation $reservation): array
    {
        $data = $request->only([
            'first_name',
            'last_name',
            'document_type',
            'document_number',
            'birth_date',
            'gender',
            'nationality',
            'email',
            'phone',
            'special_needs',
            'is_primary_guest',
            'health_insurance_name',
            'health_insurance_type',
        ]);

        $data['is_infant'] = $request->boolean('is_infant');
        $data['is_child'] = $request->boolean('is_child');

        return $this->classifyGuestPayload($data, $reservation);
    }

    public function destroy(Reservation $reservation, ReservationGuest $guest)
    {
        $guest->delete();
        $this->syncReservationToGoogleCalendar($reservation);
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

        if ($deletedCount > 0) {
            $this->syncReservationToGoogleCalendar($reservation);
        }

        return response()->json([
            'message' => "Se eliminaron {$deletedCount} huéspedes duplicados",
            'deleted_count' => $deletedCount,
            'remaining_guests' => $reservation->guests()->count()
        ]);
    }
}

