<?php

namespace Tests\Unit;

use App\Services\GuestImportService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GuestImportServiceTest extends TestCase
{
    protected GuestImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GuestImportService();
    }

    public function test_build_template_creates_xlsx_file(): void
    {
        $path = $this->service->buildTemplate();

        $this->assertFileExists($path);
        $this->assertGreaterThan(1000, filesize($path));

        @unlink($path);
    }

    public function test_parses_csv_guest_rows(): void
    {
        $csv = implode("\n", [
            'Nombre,Apellido,Tipo documento,Número documento,Fecha nacimiento,Género,Nacionalidad,Email,Teléfono,Necesidades especiales,Principal,EPS/Aseguradora,Tipo EPS',
            'Ana,Gómez,CC,987654321,1988-03-10,Femenino,Colombiana,ana@test.com,3001112233,,Sí,Sura,Nacional',
            'Luis,Ramírez,CC,123123123,,Masculino,,,,,No,,',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'guest_csv_');
        file_put_contents($path, $csv);

        $file = new UploadedFile($path, 'huespedes.csv', 'text/csv', null, true);
        $result = $this->service->parseFile($file);

        @unlink($path);

        $this->assertCount(2, $result['guests']);
        $this->assertSame('Ana', $result['guests'][0]['first_name']);
        $this->assertSame('Gómez', $result['guests'][0]['last_name']);
        $this->assertTrue($result['guests'][0]['is_primary_guest']);
        $this->assertSame('female', $result['guests'][0]['gender']);
        $this->assertSame('national', $result['guests'][0]['health_insurance_type']);
        $this->assertSame('Luis', $result['guests'][1]['first_name']);
        $this->assertFalse($result['guests'][1]['is_primary_guest']);
    }

    public function test_returns_error_when_required_columns_missing(): void
    {
        $csv = "Documento,Email\n123,test@test.com\n";
        $path = tempnam(sys_get_temp_dir(), 'guest_csv_');
        file_put_contents($path, $csv);

        $file = new UploadedFile($path, 'huespedes.csv', 'text/csv', null, true);
        $result = $this->service->parseFile($file);

        @unlink($path);

        $this->assertEmpty($result['guests']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_ensure_primary_guest_marks_first_when_none_selected(): void
    {
        $guests = [
            ['first_name' => 'A', 'last_name' => 'B', 'is_primary_guest' => false],
            ['first_name' => 'C', 'last_name' => 'D', 'is_primary_guest' => false],
        ];

        $normalized = $this->service->ensurePrimaryGuest($guests);

        $this->assertTrue($normalized[0]['is_primary_guest']);
        $this->assertFalse($normalized[1]['is_primary_guest']);
    }
}
