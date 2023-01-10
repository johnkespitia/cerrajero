<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $rol = Role::create([
            "name"=>"root",
            "guard_name"=>"cerrajero"
        ]);
        $rol->givePermissionTo("user.list");
        $rol->givePermissionTo("user.create");
        $rol->givePermissionTo("user.edit");
        $rol->givePermissionTo("rol.list");
        $rol->givePermissionTo("rol.create");
        $rol->givePermissionTo("rol.edit");
        $rol->givePermissionTo("rol.grantpermission");
        $rol->givePermissionTo("rol.revokepermission");
        $rol->givePermissionTo("permission.list");
        $rol->givePermissionTo("permission.create");
        $rol->givePermissionTo("permission.edit");
        $rol->givePermissionTo("guard.list");
        $rol->givePermissionTo("guard.create");
        $rol->givePermissionTo("guard.edit");

    }
}
