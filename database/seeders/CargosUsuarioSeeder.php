<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CargosUsuarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $cargosUsuarios =
            [
                [
                    'estado' => '1',
                    'id_user' => 1,
                    'id_cargo' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
            ];

        DB::table('cargo_users')->insert($cargosUsuarios);
    }
}
