<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubmenusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $submenus = [
            [
                'nom_smenu' => 'Cargue Empleados',
                'link_smenu' => 'cargue-empleados',
                'desc_smenu' => 'Cargue Empleados',
                'id_menu' => 17,
                'created_at' => now(),
                'updated_at' => now()
            ], //1
        ];

        DB::table('submenu')->insert($submenus);
    }
}
