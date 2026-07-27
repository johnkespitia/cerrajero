<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsElectronicInvoicingPermissions;
use Tests\TestCase;

/**
 * Verifies that every `/api/electronic-invoicing/**` route is gated by the
 * correct `electronic_invoicing.*` permission. The contract defended by
 * these tests is: an authenticated user without any EI permission cannot
 * reach EI routes; a user granted only `list` cannot mutate; a user
 * granted only `admin` cannot list documents; etc.
 */
class PermissionsMiddlewareTest extends TestCase
{
    use RefreshDatabase;
    use SeedsElectronicInvoicingPermissions;

    public function test_authenticated_user_without_any_ei_permission_cannot_access_routes(): void
    {
        $user = $this->makeUser('no-perms@test.local');
        $this->grantElectronicInvoicingPermissions($user, []);
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/electronic-invoicing/documents')->assertStatus(401);
        $this->getJson('/api/electronic-invoicing/company-profile')->assertStatus(401);
        $this->getJson('/api/electronic-invoicing/healthcheck')->assertStatus(401);
        $this->postJson('/api/electronic-invoicing/documents/1/credit-note', [])->assertStatus(401);
    }

    public function test_list_permission_grants_read_but_not_admin_or_create(): void
    {
        $user = $this->makeUser('only-list@test.local');
        $this->grantElectronicInvoicingPermissions($user, ['electronic_invoicing.list']);
        $this->actingAs($user, 'sanctum');

        // List/healthcheck = 200 (or empty/null result, never 401)
        $this->getJson('/api/electronic-invoicing/healthcheck')->assertStatus(200);
        $this->getJson('/api/electronic-invoicing/documents')->assertStatus(200);

        // Admin endpoint and create endpoint denied with 401
        $this->getJson('/api/electronic-invoicing/company-profile')->assertStatus(401);
        $this->postJson('/api/electronic-invoicing/documents/1/credit-note', [])->assertStatus(401);
    }

    public function test_admin_permission_grants_company_profile_but_not_list(): void
    {
        $user = $this->makeUser('only-admin@test.local');
        $this->grantElectronicInvoicingPermissions($user, ['electronic_invoicing.admin']);
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/electronic-invoicing/company-profile')->assertStatus(200);

        $this->getJson('/api/electronic-invoicing/documents')->assertStatus(401);
        $this->getJson('/api/electronic-invoicing/healthcheck')->assertStatus(401);
    }

    public function test_create_permission_grants_credit_note_route(): void
    {
        $user = $this->makeUser('only-create@test.local');
        $this->grantElectronicInvoicingPermissions($user, ['electronic_invoicing.create']);
        $this->actingAs($user, 'sanctum');

        // With no parent doc the service returns 422 (validation), but the route is reachable (not 401).
        $response = $this->postJson('/api/electronic-invoicing/documents/9999/credit-note', []);
        $this->assertNotSame(401, $response->status(), 'create permission must not be blocked by middleware');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/electronic-invoicing/documents')->assertStatus(401);
    }

    private function makeUser(string $email): User
    {
        return User::create([
            'name' => 'Test',
            'email' => $email,
            'password' => bcrypt('test1234'),
        ]);
    }
}
