<?php

namespace Database\Seeders;

use App\Models\Empresa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class EmpresasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Crear una empresa
        Empresa::create([
            'nit' => '900432887-4',
            'emp_nombre' => 'PROYELCO',
            'direccion' => 'YUMBO',
            'telefono' => '123456789',
            'cuenta_de_correo' => 'proyelco@gmail.com',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

    }
}
