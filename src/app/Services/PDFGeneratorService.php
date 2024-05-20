<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use \PDF;

class PDFGeneratorService
{
    public function generatePDF($data)
    {
        $pdf = app('dompdf.wrapper');
        $html = view('pdf.invoice', ["data"=>$data])->render();
        $pdf->loadHTML($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        return $pdf->stream("cuenta_cobro_No_{$data->id}.pdf");
    }
}

/*
'customerName' => 'Personal Learning Group',
            'customerID' => 'NIT. 900.954.523-9',
            'customerPhone' => '+57 3237608867',
            'customerEmail' => 'info@plgcolombia.com',
*/
