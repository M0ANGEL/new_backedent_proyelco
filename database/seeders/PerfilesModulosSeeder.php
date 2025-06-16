<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PerfilesModulosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $perfilesModulos =
            [
                //empresa
                [
                    'id_modulo' => 1,
                    'id_menu' => 1,
                    'id_submenu' => null,
                    'id_perfil' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                //usuarios
                [
                    'id_modulo' => 2,
                    'id_menu' => 2,
                    'id_submenu' => null,
                    'id_perfil' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'id_modulo' => 2,
                    'id_menu' => 3,
                    'id_submenu' => null,
                    'id_perfil' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'id_modulo' => 2,
                    'id_menu' => 4,
                    'id_submenu' => null,
                    'id_perfil' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                //configuracion sistemas
                [
                    'id_modulo' => 3,
                    'id_menu' => 5,
                    'id_submenu' => null,
                    'id_perfil' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'id_modulo' => 3,
                    'id_menu' => 6,
                    'id_submenu' => null,
                    'id_perfil' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'id_modulo' => 3,
                    'id_menu' => 7,
                    'id_submenu' => null,
                    'id_perfil' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                //proyectos
                [
                    'id_modulo' => 4,
                    'id_menu' => 8,
                    'id_submenu' => null,
                    'id_perfil' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'id_modulo' => 4,
                    'id_menu' => 9,
                    'id_submenu' => null,
                    'id_perfil' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                //clientes
                [
                    'id_modulo' => 6,
                    'id_menu' => 11,
                    'id_submenu' => null,
                    'id_perfil' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                //confi procesos
                [
                    'id_modulo' => 7,
                    'id_menu' => 12,
                    'id_submenu' => null,
                    'id_perfil' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                //compras
                [
                    'id_modulo' => 8,
                    'id_menu' => 13,
                    'id_submenu' => null,
                    'id_perfil' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'id_modulo' => 8,
                    'id_menu' => 14,
                    'id_submenu' => null,
                    'id_perfil' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'id_modulo' => 8,
                    'id_menu' => 15,
                    'id_submenu' => null,
                    'id_perfil' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                //talento humano
            ];

        DB::table('perfiles_modulos')->insert($perfilesModulos);
    }
}
