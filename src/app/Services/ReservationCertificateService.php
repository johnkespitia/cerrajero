<?php

namespace App\Services;

use App\Models\Reservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReservationCertificateService
{
    public function generateCertificate(Reservation $reservation)
    {
        $reservation->loadMissing(['customer', 'room', 'roomType', 'guests']);

        // Buscar logo y convertirlo a base64 para DomPDF
        $logoBase64 = null;
        $possibleLogoPaths = [
            storage_path('app/public/logo-campo-verde.png'), // Primera opción: ubicación especificada
            storage_path('app/public/logocv.png'), // Logo actual
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
                $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                break;
            }
        }

        $data = [
            'reservation' => $reservation,
            'customer' => $reservation->customer,
            'room' => $reservation->room,
            'date' => now()->format('d/m/Y'),
            'time' => now()->format('H:i:s'),
            'logo_base64' => $logoBase64,
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
        $reservation->loadMissing(['customer', 'room', 'roomType', 'guests', 'payments.paymentType']);

        // Separar pagos normales de pagos a crédito (cargo a habitación)
        $allPayments = $reservation->payments;
        $normalPayments = $allPayments->filter(function($payment) {
            return !$payment->concept || !str_contains($payment->concept, 'Compra en kiosko (a crédito)');
        });
        $creditPayments = $allPayments->filter(function($payment) {
            return $payment->concept && str_contains($payment->concept, 'Compra en kiosko (a crédito)');
        });

        // Buscar logo y convertirlo a base64 para DomPDF
        $logoBase64 = null;
        $possibleLogoPaths = [
            storage_path('app/public/logo-campo-verde.png'),
            storage_path('app/public/logocv.png'),
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
                $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                break;
            }
        }

        $data = [
            'reservation' => $reservation,
            'customer' => $reservation->customer,
            'room' => $reservation->room,
            'roomType' => $reservation->roomType,
            'guests' => $reservation->guests,
            'payments' => $normalPayments,
            'creditPayments' => $creditPayments,
            'date' => now()->format('d/m/Y'),
            'time' => now()->format('H:i:s'),
            'logo_base64' => $logoBase64,
            'type' => 'checkout', // Indicar que es un certificado de checkout
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
            'customer', 
            'room', 
            'roomType', 
            'guests', 
            'payments.paymentType',
            'kioskInvoices.paymentType',
            'kioskInvoices.details.kiosk_unit.product'
        ]);

        // Buscar logo y convertirlo a base64 para DomPDF
        $logoBase64 = null;
        $possibleLogoPaths = [
            storage_path('app/public/logo-campo-verde.png'),
            storage_path('app/public/logocv.png'),
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
                $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                break;
            }
        }

        // Calcular totales
        $reservationTotal = $reservation->final_price ?? $reservation->total_price;
        
        // Total de facturas del kiosko (todas, no solo pendientes)
        $kioskInvoices = $reservation->kioskInvoices;
        $kioskTotal = $kioskInvoices->sum(function($invoice) {
            return $invoice->details->sum('price');
        });
        
        // Separar pagos normales de pagos a crédito (cargo a habitación)
        $allPayments = $reservation->payments;
        $normalPayments = $allPayments->filter(function($payment) {
            return !$payment->concept || !str_contains($payment->concept, 'Compra en kiosko (a crédito)');
        });
        $creditPayments = $allPayments->filter(function($payment) {
            return $payment->concept && str_contains($payment->concept, 'Compra en kiosko (a crédito)');
        });
        
        // Total pagado en la reserva (solo pagos normales, excluyendo créditos)
        $totalPaid = $normalPayments->sum('amount');
        
        // Saldo pendiente
        $totalPending = max(0, ($reservationTotal + $kioskTotal) - $totalPaid);
        
        // Generar número de factura único
        $invoiceNumber = 'FV-' . str_pad($reservation->id, 6, '0', STR_PAD_LEFT) . '-' . date('Ymd');

        $data = [
            'reservation' => $reservation,
            'customer' => $reservation->customer,
            'room' => $reservation->room,
            'roomType' => $reservation->roomType,
            'guests' => $reservation->guests,
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



