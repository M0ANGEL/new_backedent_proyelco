<?php

namespace Database\Seeders\Tenants;

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
            'nombre' => 'Administrador',
            'telefono' => '123456',
            'cargo' => 'Administrador',
            'username' => 'admin',
            'password' => Hash::make('admin'),
            'last_login' => now(),
            'rol' => 'administrador',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
