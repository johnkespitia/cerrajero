<?php

namespace Tests\Feature\ElectronicInvoicing;

use Database\Seeders\ElectronicInvoicingPermissionsSeeder;
use Database\Seeders\GuardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ElectronicInvoicingPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_declares_all_electronic_documents_permissions_under_cerrajero_guard(): void
    {
        $this->seed(GuardSeeder::class);
        $this->seed(ElectronicInvoicingPermissionsSeeder::class);

        foreach (array_keys(ElectronicInvoicingPermissionsSeeder::PERMISSIONS) as $name) {
            $this->assertDatabaseHas('permissions', [
                'name' => $name,
                'guard_name' => 'cerrajero',
            ]);
        }
    }

    public function test_seeder_is_idempotent_and_does_not_duplicate_existing_permissions(): void
    {
        $this->seed(GuardSeeder::class);
        $this->seed(ElectronicInvoicingPermissionsSeeder::class);
        $countAfterFirst = Permission::query()
            ->whereIn('name', array_keys(ElectronicInvoicingPermissionsSeeder::PERMISSIONS))
            ->count();
        $this->seed(ElectronicInvoicingPermissionsSeeder::class);
        $countAfterSecond = Permission::query()
            ->whereIn('name', array_keys(ElectronicInvoicingPermissionsSeeder::PERMISSIONS))
            ->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
        $this->assertSame(count(ElectronicInvoicingPermissionsSeeder::PERMISSIONS), $countAfterSecond);
    }
}
