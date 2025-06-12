<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PerfilesUsuarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $perfilesUsuarios =
            [
                [
                    'id_user' => 1,
                    'id_perfil' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
            ];

        DB::table('users_perfiles')->insert($perfilesUsuarios);
    }
}
