<?php

namespace Tests\Concerns;

use App\Models\User;
use Database\Seeders\ElectronicInvoicingPermissionsSeeder;
use Database\Seeders\GuardSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shared helpers for feature tests that hit `/api/electronic-invoicing/**`.
 *
 * Seeds the `cerrajero` guard and the EI permission catalogue, and gives
 * the supplied user the requested EI permissions. Default = all of them,
 * which keeps existing tests green without forcing every author to know
 * the exact permission required by each route.
 */
trait SeedsElectronicInvoicingPermissions
{
    /**
     * Seed guard + permissions and assign the listed EI permissions to the
     * user. Pass an empty array to register a user with NO EI permissions
     * (useful for "403 without permission" tests).
     *
     * @param  array<int, string>|null  $permissions  null = all EI permissions
     */
    protected function grantElectronicInvoicingPermissions(User $user, ?array $permissions = null): User
    {
        $this->seed(GuardSeeder::class);
        $this->seed(ElectronicInvoicingPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $catalogue = array_keys(ElectronicInvoicingPermissionsSeeder::PERMISSIONS);
        $selected = $permissions === null ? $catalogue : array_values(array_intersect($permissions, $catalogue));

        foreach ($selected as $name) {
            $user->givePermissionTo($name);
        }

        return $user->refresh();
    }
}
