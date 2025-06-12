<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(EmpresasSeeder::class);
        $this->call(UsersSeeder::class);
        $this->call(EmpxUsuSeeder::class);
        $this->call(PerfilesSeeder::class);
        $this->call(CargosSeeder::class);
        $this->call(ModulosSeeder::class);
        $this->call(MenusSeeder::class);
        $this->call(SubmenusSeeder::class);
        $this->call(PerfilesModulosSeeder::class);
        $this->call(PerfilesUsuarioSeeder::class);
        $this->call(CargosUsuarioSeeder::class);
    }
}
