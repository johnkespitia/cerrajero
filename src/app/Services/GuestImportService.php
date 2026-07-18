<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GuestImportService
{
    public const TEMPLATE_HEADERS = [
        'Nombre',
        'Apellido',
        'Tipo documento',
        'Número documento',
        'Fecha nacimiento',
        'Género',
        'Nacionalidad',
        'Email',
        'Teléfono',
        'Necesidades especiales',
        'Principal',
        'EPS/Aseguradora',
        'Tipo EPS',
    ];

    private const HEADER_ALIASES = [
        'nombre' => 'first_name',
        'first_name' => 'first_name',
        'apellido' => 'last_name',
        'last_name' => 'last_name',
        'tipo documento' => 'document_type',
        'tipo de documento' => 'document_type',
        'document_type' => 'document_type',
        'numero documento' => 'document_number',
        'número documento' => 'document_number',
        'numero de documento' => 'document_number',
        'número de documento' => 'document_number',
        'document_number' => 'document_number',
        'fecha nacimiento' => 'birth_date',
        'fecha de nacimiento' => 'birth_date',
        'birth_date' => 'birth_date',
        'genero' => 'gender',
        'género' => 'gender',
        'gender' => 'gender',
        'nacionalidad' => 'nationality',
        'nationality' => 'nationality',
        'email' => 'email',
        'correo' => 'email',
        'telefono' => 'phone',
        'teléfono' => 'phone',
        'phone' => 'phone',
        'necesidades especiales' => 'special_needs',
        'special_needs' => 'special_needs',
        'principal' => 'is_primary_guest',
        'is_primary_guest' => 'is_primary_guest',
        'eps/aseguradora' => 'health_insurance_name',
        'eps aseguradora' => 'health_insurance_name',
        'health_insurance_name' => 'health_insurance_name',
        'tipo eps' => 'health_insurance_type',
        'health_insurance_type' => 'health_insurance_type',
    ];

    public function buildTemplate(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Huéspedes');

        foreach (self::TEMPLATE_HEADERS as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $sheet->fromArray([
            'Juan',
            'Pérez',
            'CC',
            '1234567890',
            '1990-05-15',
            'Masculino',
            'Colombiana',
            'juan@ejemplo.com',
            '3001234567',
            '',
            'Sí',
            'Sanitas',
            'Nacional',
        ], null, 'A2');

        $writer = new Xlsx($spreadsheet);
        $tempPath = tempnam(sys_get_temp_dir(), 'guest_template_') . '.xlsx';
        $writer->save($tempPath);

        return $tempPath;
    }

    /**
     * @return array{guests: array<int, array<string, mixed>>, errors: array<int, array{row: int, message: string}>}
     */
    public function parseFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            return [
                'guests' => [],
                'errors' => [
                    ['row' => 0, 'message' => 'Formato no soportado. Use .xlsx, .xls o .csv'],
                ],
            ];
        }

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable $e) {
            return [
                'guests' => [],
                'errors' => [
                    ['row' => 0, 'message' => 'No se pudo leer el archivo Excel: ' . $e->getMessage()],
                ],
            ];
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        if (count($rows) < 2) {
            return [
                'guests' => [],
                'errors' => [
                    ['row' => 0, 'message' => 'El archivo no contiene filas de huéspedes'],
                ],
            ];
        }

        $headerRow = array_shift($rows);
        $columnMap = $this->mapHeaders($headerRow);
        if (!isset($columnMap['first_name']) || !isset($columnMap['last_name'])) {
            return [
                'guests' => [],
                'errors' => [
                    ['row' => 1, 'message' => 'La plantilla debe incluir columnas Nombre y Apellido'],
                ],
            ];
        }

        $guests = [];
        $errors = [];
        $seenDocuments = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $guest = $this->extractGuestFromRow($row, $columnMap);

            if ($this->isEmptyGuest($guest)) {
                continue;
            }

            $rowErrors = $this->validateGuest($guest, $rowNumber);
            if (!empty($rowErrors)) {
                foreach ($rowErrors as $message) {
                    $errors[] = ['row' => $rowNumber, 'message' => $message];
                }
                continue;
            }

            $docKey = strtolower(trim((string) $guest['document_number'])) . '|' . ($guest['document_type'] ?? 'CC');
            if ($guest['document_number'] && isset($seenDocuments[$docKey])) {
                $guests[$seenDocuments[$docKey]] = $guest;
                continue;
            }

            if ($guest['document_number']) {
                $seenDocuments[$docKey] = count($guests);
            }

            $guests[] = $guest;
        }

        $guests = $this->ensurePrimaryGuest($guests);

        return [
            'guests' => array_values($guests),
            'errors' => $errors,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $guests
     * @return array<int, array<string, mixed>>
     */
    public function ensurePrimaryGuest(array $guests): array
    {
        if (empty($guests)) {
            return $guests;
        }

        $primaryIndexes = [];
        foreach ($guests as $index => $guest) {
            if (!empty($guest['is_primary_guest'])) {
                $primaryIndexes[] = $index;
            }
        }

        if (count($primaryIndexes) === 0) {
            $guests[0]['is_primary_guest'] = true;
            return $guests;
        }

        $firstPrimary = $primaryIndexes[0];
        foreach ($guests as $index => &$guest) {
            $guest['is_primary_guest'] = $index === $firstPrimary;
        }
        unset($guest);

        return $guests;
    }

    /**
     * @param array<int, string|null> $headerRow
     * @return array<string, int>
     */
    private function mapHeaders(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);
            if ($normalized === '' || !isset(self::HEADER_ALIASES[$normalized])) {
                continue;
            }
            $map[self::HEADER_ALIASES[$normalized]] = (int) $index;
        }

        return $map;
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim(mb_strtolower($header));
        $header = str_replace(['_', '-'], ' ', $header);
        $header = preg_replace('/\s+/', ' ', $header) ?? $header;

        return $header;
    }

    /**
     * @param array<int, mixed> $row
     * @param array<string, int> $columnMap
     * @return array<string, mixed>
     */
    private function extractGuestFromRow(array $row, array $columnMap): array
    {
        $get = function (string $field) use ($row, $columnMap) {
            if (!isset($columnMap[$field])) {
                return null;
            }
            $value = $row[$columnMap[$field]] ?? null;
            if ($value === null) {
                return null;
            }
            $value = trim((string) $value);

            return $value === '' ? null : $value;
        };

        return [
            'first_name' => $get('first_name'),
            'last_name' => $get('last_name'),
            'document_type' => $this->normalizeDocumentType($get('document_type')),
            'document_number' => $get('document_number'),
            'birth_date' => $this->normalizeDate($get('birth_date')),
            'gender' => $this->normalizeGender($get('gender')),
            'nationality' => $get('nationality'),
            'email' => $get('email'),
            'phone' => $get('phone'),
            'special_needs' => $get('special_needs'),
            'is_primary_guest' => $this->normalizeBoolean($get('is_primary_guest')),
            'health_insurance_name' => $get('health_insurance_name'),
            'health_insurance_type' => $this->normalizeHealthInsuranceType($get('health_insurance_type')),
        ];
    }

    /**
     * @param array<string, mixed> $guest
     */
    private function isEmptyGuest(array $guest): bool
    {
        return empty($guest['first_name'])
            && empty($guest['last_name'])
            && empty($guest['document_number']);
    }

    /**
     * @param array<string, mixed> $guest
     * @return array<int, string>
     */
    private function validateGuest(array $guest, int $rowNumber): array
    {
        $errors = [];

        if (empty($guest['first_name'])) {
            $errors[] = "Fila {$rowNumber}: Nombre es obligatorio";
        }
        if (empty($guest['last_name'])) {
            $errors[] = "Fila {$rowNumber}: Apellido es obligatorio";
        }
        if (!empty($guest['email']) && !filter_var($guest['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Fila {$rowNumber}: Email inválido";
        }
        if (!empty($guest['birth_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $guest['birth_date'])) {
            $errors[] = "Fila {$rowNumber}: Fecha de nacimiento debe ser YYYY-MM-DD";
        }
        if (!empty($guest['gender']) && !in_array($guest['gender'], ['male', 'female', 'other'], true)) {
            $errors[] = "Fila {$rowNumber}: Género inválido";
        }
        if (
            !empty($guest['health_insurance_type'])
            && !in_array($guest['health_insurance_type'], ['national', 'international'], true)
        ) {
            $errors[] = "Fila {$rowNumber}: Tipo EPS inválido";
        }

        return $errors;
    }

    private function normalizeDocumentType(?string $value): string
    {
        if ($value === null || $value === '') {
            return 'CC';
        }

        $value = strtoupper(trim($value));
        $map = [
            'C.C.' => 'CC',
            'C.C' => 'CC',
            'CEDULA' => 'CC',
            'CÉDULA' => 'CC',
            'PASAPORTE' => 'PA',
            'PASSPORT' => 'PA',
            'P' => 'PA',
        ];

        return $map[$value] ?? $value;
    }

    private function normalizeGender(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($value));
        $map = [
            'm' => 'male',
            'masculino' => 'male',
            'hombre' => 'male',
            'male' => 'male',
            'f' => 'female',
            'femenino' => 'female',
            'mujer' => 'female',
            'female' => 'female',
            'otro' => 'other',
            'other' => 'other',
        ];

        return $map[$normalized] ?? null;
    }

    private function normalizeHealthInsuranceType(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($value));
        $map = [
            'nacional' => 'national',
            'national' => 'national',
            'internacional' => 'international',
            'international' => 'international',
        ];

        return $map[$normalized] ?? null;
    }

    private function normalizeBoolean(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $normalized = mb_strtolower(trim($value));

        return in_array($normalized, ['1', 'true', 'si', 'sí', 'yes', 'y', 'x'], true);
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (is_numeric($value)) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value);

                return $date->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }
}
