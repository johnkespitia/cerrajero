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
}



