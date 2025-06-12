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
                [
                    'id_modulo' => 1,
                    'id_menu' => 1,
                    'id_submenu' => null,
                    'id_perfil' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
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
                [
                    'id_modulo' => 3,
                    'id_menu' => 8,
                    'id_submenu' => null,
                    'id_perfil' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
            ];

        DB::table('perfiles_modulos')->insert($perfilesModulos);
    }
}
