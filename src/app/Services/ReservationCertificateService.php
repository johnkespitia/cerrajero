<?php

namespace App\Services;

use App\Models\Reservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReservationCertificateService
{
    /**
     * Obtener logo en formato base64 para usar en PDFs
     * Prioriza la ruta constante: storage/app/public/logocv.png
     * 
     * @return string|null Logo en formato base64 o null si no se encuentra
     */
    private function getLogoBase64(): ?string
    {
        // Ruta constante del logo (prioridad)
        $logoPath = storage_path('app/public/logocv.png');
        
        if (file_exists($logoPath)) {
            $imageData = file_get_contents($logoPath);
            $imageInfo = getimagesize($logoPath);
            $mimeType = $imageInfo['mime'] ?? 'image/png';
            return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
        }
        
        // Fallback: buscar en otras ubicaciones posibles
        $possibleLogoPaths = [
            storage_path('app/public/logo-campo-verde.png'),
            public_path('images/logo-campo-verde.png'),
            public_path('logo.png'),
            public_path('logo.jpg'),
            storage_path('app/public/logo.png'),
            base_path('public/images/logo-campo-verde.png'),
            base_path('public/logo.png'),
        ];

        foreach ($possibleLogoPaths as $path) {
            if (file_exists($path)) {
                $imageData = file_get_contents($path);
                $imageInfo = getimagesize($path);
                $mimeType = $imageInfo['mime'] ?? 'image/png';
                return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
            }
        }
        
        return null;
    }

    /**
     * Para reservas con múltiples habitaciones (grupo): devuelve [reservations], [rooms], [guests], totalAdults, totalChildren, totalInfants.
     * Para reserva simple: devuelve null en las listas y las totals = de la misma reserva.
     */
    private function multiRoomData(Reservation $reservation): array
    {
        $isGroup = $reservation->is_group_reservation && !$reservation->parent_reservation_id;
        if (!$isGroup || !$reservation->relationLoaded('childReservations')) {
            return [
                'reservations' => collect([$reservation]),
                'rooms' => $reservation->room ? collect([$reservation->room]) : collect(),
                'guests' => $reservation->guests ?? collect(),
                'totalAdults' => (int) $reservation->adults,
                'totalChildren' => (int) $reservation->children,
                'totalInfants' => (int) $reservation->infants,
                'isMultiRoom' => false,
            ];
        }
        $reservations = collect([$reservation])->merge($reservation->childReservations);
        $rooms = $reservations->map(fn ($r) => $r->room)->filter()->values();
        $guests = $reservations->flatMap(fn ($r) => $r->guests ?? []);
        return [
            'reservations' => $reservations,
            'rooms' => $rooms,
            'guests' => $guests,
            'totalAdults' => $reservations->sum('adults'),
            'totalChildren' => $reservations->sum('children'),
            'totalInfants' => $reservations->sum('infants'),
            'isMultiRoom' => true,
        ];
    }

    public function generateCertificate(Reservation $reservation)
    {
        $reservation->loadMissing([
            'customer', 'room', 'roomType', 'guests', 'additionalServices.additionalService',
            'childReservations.room', 'childReservations.guests',
        ]);

        $multi = $this->multiRoomData($reservation);
        $logoBase64 = $this->getLogoBase64();

        $data = [
            'reservation' => $reservation,
            'customer' => $reservation->customer,
            'room' => $reservation->room,
            'date' => now()->format('d/m/Y'),
            'time' => now()->format('H:i:s'),
            'logo_base64' => $logoBase64,
            'allRooms' => $multi['rooms'],
            'allGuests' => $multi['guests'],
            'totalAdults' => $multi['totalAdults'],
            'totalChildren' => $multi['totalChildren'],
            'totalInfants' => $multi['totalInfants'],
            'isMultiRoom' => $multi['isMultiRoom'],
        ];

        $pdf = Pdf::loadView('reservations.certificate', $data);

        $filename = "certificate_{$reservation->reservation_number}.pdf";
        $path = "reservations/certificates/{$filename}";

        Storage::put($path, $pdf->output());

        return [
            'path' => $path,
            'filename' => $filename,
            'url' => Storage::url($path)
        ];
    }

    public function getCertificatePath(Reservation $reservation)
    {
        $filename = "certificate_{$reservation->reservation_number}.pdf";
        return "reservations/certificates/{$filename}";
    }

    /**
     * Generar certificado de checkout con detalles completos
     */
    public function generateCheckoutCertificate(Reservation $reservation)
    {
        $reservation->loadMissing([
            'customer', 'room', 'roomType', 'guests', 'payments.paymentType', 'additionalServices.additionalService', 'minibarCharges.product',
            'childReservations.room', 'childReservations.guests',
        ]);

        $multi = $this->multiRoomData($reservation);

        // Separar pagos normales de pagos a crédito (cargo a habitación)
        $allPayments = $reservation->payments;
        $normalPayments = $allPayments->filter(function($payment) {
            return !$payment->concept || !str_contains($payment->concept, 'Compra en kiosko (a crédito)');
        });
        $creditPayments = $allPayments->filter(function($payment) {
            return $payment->concept && str_contains($payment->concept, 'Compra en kiosko (a crédito)');
        });

        // Obtener cargos de minibar
        $minibarCharges = $reservation->minibarCharges;

        $logoBase64 = $this->getLogoBase64();

        $data = [
            'reservation' => $reservation,
            'customer' => $reservation->customer,
            'room' => $reservation->room,
            'roomType' => $reservation->roomType,
            'guests' => $multi['guests'],
            'payments' => $normalPayments,
            'creditPayments' => $creditPayments,
            'minibarCharges' => $minibarCharges,
            'date' => now()->format('d/m/Y'),
            'time' => now()->format('H:i:s'),
            'logo_base64' => $logoBase64,
            'type' => 'checkout',
            'allRooms' => $multi['rooms'],
            'allGuests' => $multi['guests'],
            'totalAdults' => $multi['totalAdults'],
            'totalChildren' => $multi['totalChildren'],
            'totalInfants' => $multi['totalInfants'],
            'isMultiRoom' => $multi['isMultiRoom'],
        ];

        $pdf = Pdf::loadView('reservations.checkout_certificate', $data);

        $filename = "checkout_{$reservation->reservation_number}.pdf";
        $path = "reservations/checkouts/{$filename}";

        Storage::put($path, $pdf->output());

        return [
            'path' => $path,
            'filename' => $filename,
            'url' => Storage::url($path)
        ];
    }

    public function getCheckoutCertificatePath(Reservation $reservation)
    {
        $filename = "checkout_{$reservation->reservation_number}.pdf";
        return "reservations/checkouts/{$filename}";
    }

    /**
     * Generar factura consolidada de checkout con todos los consumos
     */
    public function generateCheckoutInvoice(Reservation $reservation)
    {
        $reservation->loadMissing([
            'customer', 'room', 'roomType', 'guests', 'payments.paymentType',
            'additionalServices.additionalService',
            'kioskInvoices.paymentType',
            'kioskInvoices.details.kiosk_unit.product',
            'childReservations.room', 'childReservations.guests',
        ]);

        $multi = $this->multiRoomData($reservation);
        $logoBase64 = $this->getLogoBase64();

        // Para grupo, el total está en la reserva principal (final_price)
        $reservationTotal = (float) ($reservation->final_price ?? $reservation->total_price);

        $kioskInvoices = $reservation->kioskInvoices;
        $kioskTotal = $kioskInvoices->sum(function($invoice) {
            return $invoice->details->sum('price');
        });

        $allPayments = $reservation->payments;
        $normalPayments = $allPayments->filter(function($payment) {
            return !$payment->concept || !str_contains($payment->concept, 'Compra en kiosko (a crédito)');
        });
        $creditPayments = $allPayments->filter(function($payment) {
            return $payment->concept && str_contains($payment->concept, 'Compra en kiosko (a crédito)');
        });

        $totalPaid = $normalPayments->sum('amount');
        $totalPending = max(0, ($reservationTotal + $kioskTotal) - $totalPaid);
        $invoiceNumber = 'FV-' . str_pad($reservation->id, 6, '0', STR_PAD_LEFT) . '-' . date('Ymd');

        $data = [
            'reservation' => $reservation,
            'customer' => $reservation->customer,
            'room' => $reservation->room,
            'roomType' => $reservation->roomType,
            'guests' => $multi['guests'],
            'payments' => $normalPayments,
            'creditPayments' => $creditPayments,
            'kioskInvoices' => $kioskInvoices,
            'totals' => [
                'reservation' => $reservationTotal,
                'kiosko' => $kioskTotal,
                'paid' => $totalPaid,
                'pending' => $totalPending,
                'grand_total' => $reservationTotal + $kioskTotal
            ],
            'invoice_number' => $invoiceNumber,
            'date' => now()->format('d/m/Y'),
            'time' => now()->format('H:i:s'),
            'logo_base64' => $logoBase64,
            'allRooms' => $multi['rooms'],
            'totalAdults' => $multi['totalAdults'],
            'totalChildren' => $multi['totalChildren'],
            'totalInfants' => $multi['totalInfants'],
            'isMultiRoom' => $multi['isMultiRoom'],
        ];

        $pdf = Pdf::loadView('reservations.checkout_invoice', $data);

        $filename = "invoice_{$reservation->reservation_number}.pdf";
        $path = "reservations/invoices/{$filename}";

        Storage::put($path, $pdf->output());

        return [
            'path' => $path,
            'filename' => $filename,
            'url' => Storage::url($path),
            'invoice_number' => $invoiceNumber
        ];
    }
}



