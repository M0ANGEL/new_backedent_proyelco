<?php

namespace Database\Seeders;

use App\Models\Cargo;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CargosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Cargo::create([
            'nombre' => 'Administrador', 
            'descripcion' => 'Administrador', 
            'id_empresa' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

    }
}
