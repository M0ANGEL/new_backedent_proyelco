<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoProyectosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $tipoProyectos = [
            [
                'nombre_tipo' => 'Apartamentos',
                'user_id' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ], //1
        ];

        DB::table('tipos_de_proyectos')->insert($tipoProyectos);
    }
}
