<?php

namespace Database\Seeders;

use App\Models\Perfil;                                                                
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PerfilesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Crear una empresa
        Perfil::create([
            'cod_perfil' => 'ADMIN',
            'nom_perfil' => 'Administrador',
            'desc_perfil' => 'Administrador del sistema',
            'id_empresa' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

    }
}
