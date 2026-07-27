<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReservationGuestImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Guest import tests require MySQL-compatible schema.');
        }

        $this->user = User::create([
            'name' => 'Import User',
            'email' => 'import@example.com',
            'password' => bcrypt('password'),
        ]);

        $customer = Customer::create([
            'customer_type' => 'person',
            'dni' => '9988776655',
            'name' => 'Cliente',
            'last_name' => 'Import',
            'email' => 'cliente@import.com',
            'active' => true,
        ]);

        $roomType = RoomType::create([
            'name' => 'Doble',
            'code' => 'DBL',
            'default_capacity' => 2,
            'max_capacity' => 2,
            'active' => true,
        ]);

        $room = Room::create([
            'room_type_id' => $roomType->id,
            'room_number' => '201',
            'name' => 'Habitación 201',
            'status' => 'available',
            'active' => true,
            'capacity' => 2,
            'max_capacity' => 2,
            'room_price' => 250000,
        ]);

        $this->reservation = Reservation::create([
            'customer_id' => $customer->id,
            'room_id' => $room->id,
            'room_type_id' => $roomType->id,
            'reservation_type' => 'room',
            'reservation_number' => 'RES-IMPORT-001',
            'check_in_date' => now()->addDays(2)->format('Y-m-d'),
            'check_out_date' => now()->addDays(4)->format('Y-m-d'),
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'status' => 'confirmed',
            'total_price' => 500000,
        ]);
    }

    public function test_guest_import_template_is_downloadable(): void
    {
        $this->actingAs($this->user);

        $response = $this->get('/api/guests/import/template');

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type')
        );
    }

    public function test_guests_can_be_imported_from_csv(): void
    {
        $this->actingAs($this->user);

        $csv = implode("\n", [
            'Nombre,Apellido,Tipo documento,Número documento,Fecha nacimiento,Género,Nacionalidad,Email,Teléfono,Necesidades especiales,Principal,EPS/Aseguradora,Tipo EPS',
            'María,López,CC,5566778899,1992-01-20,Femenino,Colombiana,maria@test.com,3009998877,,Sí,,',
            'Pedro,Sánchez,CC,1122334455,,Masculino,,,,,No,,',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'guest_import_');
        file_put_contents($path, $csv);

        $file = new UploadedFile($path, 'huespedes.csv', 'text/csv', null, true);

        $response = $this->post("/api/reservations/{$this->reservation->id}/guests/import", [
            'file' => $file,
        ]);

        @unlink($path);

        $response->assertOk()
            ->assertJsonFragment(['created' => 2]);

        $this->assertDatabaseHas('reservation_guests', [
            'reservation_id' => $this->reservation->id,
            'first_name' => 'María',
            'document_number' => '5566778899',
            'is_primary_guest' => 1,
        ]);
    }
}
