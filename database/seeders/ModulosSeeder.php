<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModulosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $modulos = [
            [
                'cod_modulo' => 'GSTEMP',
                'nom_modulo' => 'Gestión de Empresas',
                'desc_modulo' => 'Gestión de Empresas',
                'created_at' => now(),
                'updated_at' => now()
            ], //1
            [
                'cod_modulo' => 'ADMUSU',
                'nom_modulo' => 'Administración de Usuarios',
                'desc_modulo' => 'Administración de Usuarios',
                'created_at' => now(),
                'updated_at' => now()
            ], //2
            [
                'cod_modulo' => 'CFGSIS',
                'nom_modulo' => 'Configuración del Sistema',
                'desc_modulo' => 'Configuración del Sistema',
                'created_at' => now(),
                'updated_at' => now()
            ], //3
            [
                'cod_modulo' => 'SLD',
                'nom_modulo' => 'Proyectos',
                'desc_modulo' => 'Proyectos',
                'created_at' => now(),
                'updated_at' => now()
            ], //4
            [
                'cod_modulo' => 'LOGSIS',
                'nom_modulo' => 'Logs del Sistema',
                'desc_modulo' => 'Logs del Sistema',
                'created_at' => now(),
                'updated_at' => now()
            ], //5
            [
                'cod_modulo' => 'CLI',
                'nom_modulo' => 'Clientes',
                'desc_modulo' => 'Clientes',
                'created_at' => now(),
                'updated_at' => now()
            ], //6
              [
                'cod_modulo' => 'PMPT',
                'nom_modulo' => 'Configuracion Proyectos',
                'desc_modulo' => 'Configuracion Proyectos',
                'created_at' => now(),
                'updated_at' => now()
            ], //7
        ];

        DB::table('modulos')->insert($modulos);
    }
}
