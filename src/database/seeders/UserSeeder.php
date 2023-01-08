<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::create([
            "name" => "ADMINISTRATOR",
            "email" => "jcespitia1@demo.com",
            "password"=>Hash::make("1234567890"),
            "active"=>1
        ]);

        $rol = Role::findByName("root","cerrajero");
        $user->assignRole($rol);
    }
}
