<?php

use Illuminate\Database\Seeder;
use App\User;
class UserStartup extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $password = Hash::make('1234567890');

        User::create([
            'name' => 'Root',
            'email' => 'root@campoverde.com',
            'password' => $password,
            'rol_id' => 5
        ]);

        User::create([
            'name' => 'Admin',
            'email' => 'admin@campoverde.com',
            'password' => $password,
            'rol_id' => 6
        ]);

        User::create([
            'name' => 'Agent',
            'email' => 'agent@campoverde.com',
            'password' => $password,
            'rol_id' => 7
        ]);
    }
}
