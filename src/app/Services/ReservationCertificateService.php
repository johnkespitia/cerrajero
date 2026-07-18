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

    /**
     * Refresca totales de la reserva (y hijas en grupos) antes de generar PDF.
     */
    private function refreshReservationTotals(Reservation $reservation): void
    {
        $reservation->refresh();
        $reservation->recomputeFinalPrice();

        if ($reservation->is_group_reservation && !$reservation->parent_reservation_id) {
            $reservation->loadMissing('childReservations');
            foreach ($reservation->childReservations as $child) {
                $child->refresh();
                $child->recomputeFinalPrice();
            }
        }
    }

    /**
     * Desglose de precio para PDF (hospedaje, cupón, cortesías, servicios y consumos).
     */
    private function buildPriceBreakdownData(Reservation $reservation, array $multi, array $options = []): array
    {
        $reservation->loadMissing(['promotion', 'additionalServices', 'childReservations']);

        $pb = $reservation->price_breakdown ?? [];
        $hospedaje = (float) $reservation->getLodgingBaseForFinalPrice();
        $discount = (float) ($pb['discount'] ?? $reservation->discount_amount ?? 0);

        $subtotalBeforeDiscount = isset($pb['subtotal']) ? (float) $pb['subtotal'] : null;
        if ($subtotalBeforeDiscount === null && !empty($pb['rooms'])) {
            $subtotalBeforeDiscount = (float) collect($pb['rooms'])->sum('price');
        }
        if ($subtotalBeforeDiscount === null) {
            $subtotalBeforeDiscount = $hospedaje + $discount;
        }

        $courtesyGuests = (int) ($pb['courtesy_guests'] ?? $reservation->courtesy_guests ?? 0);
        $courtesyDiscount = (float) ($pb['courtesy_discount'] ?? 0);

        $additionalServicesTotal = (float) $reservation->additional_services_total;
        $minibarTotal = (float) ($options['minibar_total'] ?? 0);
        $roomChargesTotal = (float) ($options['room_charges_total'] ?? 0);
        $kioskoTotal = (float) ($options['kiosko_total'] ?? 0);

        $total = max(
            0,
            $hospedaje + $additionalServicesTotal + $minibarTotal + $roomChargesTotal + $kioskoTotal - $courtesyDiscount
        );

        return [
            'hospedaje' => $hospedaje,
            'subtotal_before_discount' => $subtotalBeforeDiscount,
            'discount' => $discount,
            'promotion_code' => $reservation->promotion_code,
            'promotion_name' => $reservation->promotion?->name,
            'courtesy_guests' => $courtesyGuests,
            'courtesy_discount' => $courtesyDiscount,
            'additional_services_total' => $additionalServicesTotal,
            'minibar_total' => $minibarTotal,
            'room_charges_total' => $roomChargesTotal,
            'kiosko_total' => $kioskoTotal,
            'total' => $total,
            'is_multi_room' => $multi['isMultiRoom'] ?? false,
        ];
    }

    /**
     * Totales de consumos del grupo para el desglose de checkout.
     */
    private function collectCheckoutConsumptionTotals($reservationsInGroup): array
    {
        $minibarTotal = 0.0;
        $roomChargesTotal = 0.0;
        $kioskoTotal = 0.0;

        foreach ($reservationsInGroup as $res) {
            $minibarTotal += (float) ($res->minibar_charges_total ?? 0);
            $roomChargesTotal += (float) ($res->room_charges_total ?? 0);

            foreach ($res->kioskInvoices ?? collect() as $invoice) {
                $paymentType = $invoice->payment_type ?? $invoice->paymentType ?? null;
                $isCredit = $paymentType && ($paymentType->credit === true || $paymentType->credit === 1);
                if (!$isCredit) {
                    continue;
                }
                foreach ($invoice->details ?? [] as $detail) {
                    $kioskoTotal += (float) ($detail->price ?? 0);
                }
            }
        }

        return [
            'minibar_total' => $minibarTotal,
            'room_charges_total' => $roomChargesTotal,
            'kiosko_total' => $kioskoTotal,
        ];
    }

    /**
     * Pagos a crédito o cargos a habitación: no deben listarse como pagos en el PDF.
     */
    private function isCreditPayment($payment): bool
    {
        if ($payment->payment_type_id === null) {
            return true;
        }

        $paymentType = $payment->paymentType ?? $payment->payment_type ?? null;
        if ($paymentType && ($paymentType->credit === true || $paymentType->credit === 1)) {
            return true;
        }

        return $payment->concept && str_contains($payment->concept, 'Compra en kiosko (a crédito)');
    }

    /**
     * Pagos y totales del grupo (reserva principal + hijas en multihabitación).
     */
    private function collectGroupFinancialData(Reservation $reservation): array
    {
        $reservation->loadMissing([
            'payments.paymentType',
            'childReservations.payments.paymentType',
        ]);

        if ($reservation->is_group_reservation && !$reservation->parent_reservation_id) {
            $reservation->loadMissing('childReservations');
        }

        $multi = $this->multiRoomData($reservation);
        $reservationsInGroup = $multi['reservations'];

        $allPayments = $reservationsInGroup->flatMap(function ($r) {
            return $r->payments ?? collect();
        });
        $payments = $allPayments->filter(function ($payment) {
            return !$this->isCreditPayment($payment);
        })->sortBy('created_at')->values();
        $creditPayments = $allPayments->filter(function ($payment) {
            return $this->isCreditPayment($payment);
        })->sortBy('created_at')->values();

        $totalPriceGroup = $reservationsInGroup->sum(function ($r) {
            return (float) ($r->final_price ?? $r->total_price ?? 0);
        });
        $totalPaid = (float) $payments->sum('amount');
        $remainingBalance = max(0, $totalPriceGroup - $totalPaid);

        return [
            'payments' => $payments,
            'creditPayments' => $creditPayments,
            'totalPriceGroup' => $totalPriceGroup,
            'totalPaid' => $totalPaid,
            'remainingBalance' => $remainingBalance,
            'multi' => $multi,
        ];
    }

    public function generateCertificate(Reservation $reservation)
    {
        $this->refreshReservationTotals($reservation);

        $financial = $this->collectGroupFinancialData($reservation);
        $multi = $financial['multi'];

        $reservation->loadMissing([
            'customer', 'room', 'roomType', 'guests', 'additionalServices.additionalService',
            'promotion',
            'childReservations.room', 'childReservations.guests',
        ]);

        $logoBase64 = $this->getLogoBase64();
        $priceBreakdown = $this->buildPriceBreakdownData($reservation, $multi);

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
            'payments' => $financial['payments'],
            'creditPayments' => $financial['creditPayments'],
            'totalPriceGroup' => $financial['totalPriceGroup'],
            'totalPaid' => $financial['totalPaid'],
            'remainingBalance' => $financial['remainingBalance'],
            'priceBreakdown' => $priceBreakdown,
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
        $this->refreshReservationTotals($reservation);

        $financial = $this->collectGroupFinancialData($reservation);
        $multi = $financial['multi'];
        $reservationsInGroup = $multi['reservations'];

        $reservation->loadMissing([
            'customer', 'room', 'roomType', 'guests', 'payments.paymentType', 'additionalServices.additionalService',
            'promotion',
            'minibarCharges.product', 'kioskInvoices.details.kiosk_unit.product', 'kioskInvoices.payment_type',
            'childReservations.room', 'childReservations.guests',
            'childReservations.minibarCharges.product',
            'childReservations.kioskInvoices.details.kiosk_unit.product',
            'childReservations.kioskInvoices.payment_type',
            'childReservations.payments.paymentType',
        ]);

        $normalPayments = $financial['payments'];
        $creditPayments = $financial['creditPayments'];
        $totalPriceGroup = $financial['totalPriceGroup'];

        // Minibar y kiosko por reserva/habitación (para listar en PDF multihabitación)
        $minibarChargesByReservation = $reservationsInGroup->map(function ($r) {
            return [
                'reservation' => $r,
                'room' => $r->room,
                'charges' => $r->minibarCharges ?? collect(),
            ];
        })->values();

        $kioskInvoicesByReservation = $reservationsInGroup->map(function ($r) {
            return [
                'reservation' => $r,
                'room' => $r->room,
                'invoices' => $r->kioskInvoices ?? collect(),
            ];
        })->values();

        // Lista plana de cargos de minibar (compatibilidad: una sola tabla cuando una habitación)
        $minibarCharges = $reservationsInGroup->flatMap(function ($r) {
            return $r->minibarCharges ?? collect();
        });

        $logoBase64 = $this->getLogoBase64();
        $consumptionTotals = $this->collectCheckoutConsumptionTotals($reservationsInGroup);
        $priceBreakdown = $this->buildPriceBreakdownData($reservation, $multi, $consumptionTotals);

        $data = [
            'reservation' => $reservation,
            'totalPriceGroup' => $totalPriceGroup,
            'customer' => $reservation->customer,
            'room' => $reservation->room,
            'roomType' => $reservation->roomType,
            'guests' => $multi['guests'],
            'payments' => $normalPayments,
            'creditPayments' => $creditPayments,
            'minibarCharges' => $minibarCharges,
            'minibarChargesByReservation' => $minibarChargesByReservation,
            'kioskInvoicesByReservation' => $kioskInvoicesByReservation,
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
            'priceBreakdown' => $priceBreakdown,
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
            'childReservations.kioskInvoices.paymentType',
            'childReservations.kioskInvoices.details.kiosk_unit.product',
            'childReservations.payments.paymentType',
        ]);

        $multi = $this->multiRoomData($reservation);
        $logoBase64 = $this->getLogoBase64();

        // Para grupo, el total está en la reserva principal (final_price)
        $reservationTotal = (float) ($reservation->final_price ?? $reservation->total_price);

        // Consumos del kiosko: reserva principal + reservas hijas (multihabitaciones)
        $kioskInvoices = $reservation->kioskInvoices;
        if ($reservation->childReservations->isNotEmpty()) {
            foreach ($reservation->childReservations as $child) {
                $child->load(['kioskInvoices.details.kiosk_unit.product', 'kioskInvoices.paymentType']);
                $kioskInvoices = $kioskInvoices->merge($child->kioskInvoices);
            }
        }
        $kioskTotal = $kioskInvoices->sum(function ($invoice) {
            return $invoice->details->sum('price');
        });

        // Pagos del grupo (principal + hijas) para multihabitaciones
        $allPayments = $reservation->payments;
        if ($reservation->childReservations->isNotEmpty()) {
            foreach ($reservation->childReservations as $child) {
                $allPayments = $allPayments->merge($child->payments);
            }
        }
        $normalPayments = $allPayments->filter(function ($payment) {
            return !$this->isCreditPayment($payment);
        });
        $creditPayments = $allPayments->filter(function ($payment) {
            return $this->isCreditPayment($payment);
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



