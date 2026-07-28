<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\GuestPortalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GuestPortalTest extends TestCase
{
    use RefreshDatabase;

    protected Reservation $reservation;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Guest portal tests require MySQL-compatible schema.');
        }

        config(['services.guest_portal.public_site_url' => 'http://localhost:3001']);

        $customer = Customer::create([
            'customer_type' => 'person',
            'dni' => '1234567890',
            'name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => 'ana.perez@example.com',
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
            'room_number' => '301',
            'name' => 'Habitación 301',
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
            'reservation_number' => 'RES-PORTAL-001',
            'check_in_date' => now()->addDays(2)->format('Y-m-d'),
            'check_out_date' => now()->addDays(4)->format('Y-m-d'),
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'status' => 'confirmed',
            'total_price' => 500000,
        ]);

        $portal = app(GuestPortalService::class);
        $portal->ensureToken($this->reservation);
        $this->reservation->refresh();
        $this->token = $this->reservation->guest_portal_token;
    }

    public function test_invalid_token_returns_404(): void
    {
        $this->getJson('/api/public/guest-portal/not-a-valid-token')
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'Link de registro no válido.']);
    }

    public function test_summary_hides_pii_before_otp(): void
    {
        $response = $this->getJson('/api/public/guest-portal/' . $this->token);

        $response->assertOk()
            ->assertJsonPath('reservation_number', 'RES-PORTAL-001')
            ->assertJsonPath('masked_email', 'a***@example.com')
            ->assertJsonMissing(['email' => 'ana.perez@example.com']);
    }

    public function test_guests_require_session(): void
    {
        $this->getJson('/api/public/guest-portal/' . $this->token . '/guests')
            ->assertUnauthorized();
    }

    public function test_otp_request_and_verify_flow(): void
    {
        Mail::fake();

        $this->postJson('/api/public/guest-portal/' . $this->token . '/otp/request')
            ->assertOk()
            ->assertJsonPath('masked_email', 'a***@example.com');

        $this->reservation->refresh();
        $this->assertNotNull($this->reservation->guest_portal_otp_hash);

        // Sembramos un código conocido para verificar (el real solo va por email).
        $this->reservation->update([
            'guest_portal_otp_hash' => Hash::make('654321'),
            'guest_portal_otp_expires_at' => now()->addMinutes(10),
            'guest_portal_otp_attempts' => 0,
        ]);

        $verify = $this->postJson('/api/public/guest-portal/' . $this->token . '/otp/verify', [
            'otp_code' => '654321',
        ]);

        $verify->assertOk()
            ->assertJsonStructure(['session_token', 'expires_in_hours']);

        $session = $verify->json('session_token');

        $this->withHeader('Authorization', 'Bearer ' . $session)
            ->getJson('/api/public/guest-portal/' . $this->token . '/guests')
            ->assertOk()
            ->assertJsonStructure(['guests', 'capacity', 'summary']);
    }

    public function test_otp_lockout_after_max_attempts(): void
    {
        $this->reservation->update([
            'guest_portal_otp_hash' => Hash::make('111111'),
            'guest_portal_otp_expires_at' => now()->addMinutes(10),
            'guest_portal_otp_attempts' => 0,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/public/guest-portal/' . $this->token . '/otp/verify', [
                'otp_code' => '000000',
            ])->assertStatus(422);
        }

        $this->postJson('/api/public/guest-portal/' . $this->token . '/otp/verify', [
            'otp_code' => '111111',
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['locked' => true]);
    }

    public function test_expired_otp_is_rejected(): void
    {
        $this->reservation->update([
            'guest_portal_otp_hash' => Hash::make('222222'),
            'guest_portal_otp_expires_at' => now()->subMinute(),
            'guest_portal_otp_attempts' => 0,
        ]);

        $this->postJson('/api/public/guest-portal/' . $this->token . '/otp/verify', [
            'otp_code' => '222222',
        ])->assertStatus(422);
    }

    public function test_blocked_status_returns_closed_summary(): void
    {
        $this->reservation->update(['status' => 'checked_in']);

        $this->getJson('/api/public/guest-portal/' . $this->token)
            ->assertOk()
            ->assertJsonPath('portal_open', false)
            ->assertJsonPath('reservation_number', 'RES-PORTAL-001');

        $this->postJson('/api/public/guest-portal/' . $this->token . '/otp/request')
            ->assertStatus(422);
    }

    public function test_guest_crud_respects_capacity(): void
    {
        $session = app(GuestPortalService::class)->issueSession($this->reservation);

        $headers = ['Authorization' => 'Bearer ' . $session];

        $this->withHeaders($headers)
            ->postJson('/api/public/guest-portal/' . $this->token . '/guests', [
                'first_name' => 'Ana',
                'last_name' => 'Pérez',
                'document_number' => '111',
                'document_type' => 'CC',
                'is_primary_guest' => true,
            ])
            ->assertCreated();

        $this->withHeaders($headers)
            ->postJson('/api/public/guest-portal/' . $this->token . '/guests', [
                'first_name' => 'Luis',
                'last_name' => 'Pérez',
                'document_number' => '222',
                'document_type' => 'CC',
            ])
            ->assertCreated();

        $this->withHeaders($headers)
            ->postJson('/api/public/guest-portal/' . $this->token . '/guests', [
                'first_name' => 'Extra',
                'last_name' => 'Guest',
                'document_number' => '333',
                'document_type' => 'CC',
            ])
            ->assertStatus(422);
    }

    public function test_staff_can_get_and_send_portal_link(): void
    {
        Mail::fake();

        $user = User::create([
            'name' => 'Staff',
            'email' => 'staff-portal@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);
        $this->withoutMiddleware(\App\Http\Middleware\ValidatePermission::class);

        $this->getJson('/api/reservations/' . $this->reservation->id . '/guest-portal/link')
            ->assertOk()
            ->assertJsonStructure(['url', 'token', 'has_recipient_email'])
            ->assertJsonPath('url', 'http://localhost:3001/registro-huespedes/?token=' . $this->token);

        $this->postJson('/api/reservations/' . $this->reservation->id . '/guest-portal/link', [
            'send_email' => true,
        ])->assertOk();
    }
}
