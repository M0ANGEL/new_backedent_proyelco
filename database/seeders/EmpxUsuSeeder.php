<?php

namespace Database\Seeders;

use App\Models\EmpxUsu;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmpxUsuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Crear una relacion de usuario con empresa
        EmpxUsu::create([
            'id_user' => 1,
            'id_empresa' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

}
