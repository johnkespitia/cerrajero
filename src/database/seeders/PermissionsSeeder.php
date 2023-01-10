<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Permission::create([
            "name"=>"user.list",
            "guard_name"=>"cerrajero"
        ]);
        Permission::create([
            "name"=>"user.create",
            "guard_name"=>"cerrajero"
        ]);
        Permission::create([
            "name"=>"user.edit",
            "guard_name"=>"cerrajero"
        ]);
        Permission::create([
            "name"=>"rol.list",
            "guard_name"=>"cerrajero"
        ]);
        Permission::create([
            "name"=>"rol.create",
            "guard_name"=>"cerrajero"
        ]);
        Permission::create([
            "name"=>"rol.edit",
            "guard_name"=>"cerrajero"
        ]);
        Permission::create([
            "name"=>"rol.grantpermission",
            "guard_name"=>"cerrajero"
        ]);
        Permission::create([
            "name"=>"rol.revokepermission",
            "guard_name"=>"cerrajero"
        ]);
        Permission::create([
            "name"=>"permission.list",
            "guard_name"=>"cerrajero"
        ]);
        Permission::create([
            "name"=>"permission.create",
            "guard_name"=>"cerrajero"
        ]);
        Permission::create([
            "name"=>"permission.edit",
            "guard_name"=>"cerrajero"
        ]);
        Permission::create([
            "name"=>"guard.list",
            "guard_name"=>"cerrajero"
        ]);
        Permission::create([
            "name"=>"guard.create",
            "guard_name"=>"cerrajero"
        ]);
        Permission::create([
            "name"=>"guard.edit",
            "guard_name"=>"cerrajero"
        ]);
    }
}
