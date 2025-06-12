<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Crear un usuario
        User::create([
            'cedula' => '12345678',
            'nombre' => 'Admin Software',
            'telefono' => '123456',
            'cargo' => 'Administrador',
            'username' => 'admin',
            'password' => Hash::make('12345678'),
            'last_login' => now(),
            'rol' => 'administrador',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        User::create([
            'cedula' => '1221',
            'nombre' => 'Jorge',
            'telefono' => '123456',
            'cargo' => 'Administrador',
            'username' => 'jorge',
            'password' => Hash::make('12345678'),
            'last_login' => now(),
            'rol' => 'administrador',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
