<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private const GUARD = 'reservas';

    /**
     * Crear el permiso administrativo del minibar y asignarlo al rol admin.
     */
    public function up(): void
    {
        $warehousePermission = Permission::firstOrCreate(
            ['name' => 'minibar.warehouse.record', 'guard_name' => self::GUARD],
            ['name' => 'minibar.warehouse.record', 'guard_name' => self::GUARD]
        );

        $admin = Role::firstOrCreate(
            ['name' => 'reservas_admin', 'guard_name' => self::GUARD],
            ['name' => 'reservas_admin', 'guard_name' => self::GUARD]
        );

        $admin->givePermissionTo([
            'minibar.list',
            'minibar.category.list',
            'minibar.category.create',
            'minibar.category.edit',
            'minibar.category.delete',
            'minibar.product.list',
            'minibar.product.create',
            'minibar.product.edit',
            'minibar.product.delete',
            'minibar.inventory.view',
            'minibar.inventory.record',
            'minibar.warehouse.record',
            'minibar.charge.view',
            'minibar.charge.delete',
        ]);

        $receptionist = Role::firstOrCreate(
            ['name' => 'recepcionista', 'guard_name' => self::GUARD],
            ['name' => 'recepcionista', 'guard_name' => self::GUARD]
        );

        $receptionist->revokePermissionTo($warehousePermission);
    }

    public function down(): void
    {
        $permission = Permission::where('name', 'minibar.warehouse.record')
            ->where('guard_name', self::GUARD)
            ->first();

        if ($permission) {
            $permission->delete();
        }
    }
};
