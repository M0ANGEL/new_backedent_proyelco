<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $menus = [
            [
                'nom_menu' => 'Empresas',
                'link_menu' => 'empresas',
                'desc_menu' => 'Empresas',
                'id_modulo' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ], //1
            [
                'nom_menu' => 'Perfiles',
                'link_menu' => 'perfiles',
                'desc_menu' => 'Perfiles',
                'id_modulo' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ], //2
            [
                'nom_menu' => 'Usuarios',
                'link_menu' => 'usuarios',
                'desc_menu' => 'Usuarios',
                'id_modulo' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ], //3
            [
                'nom_menu' => 'Cargos',
                'link_menu' => 'cargos',
                'desc_menu' => 'Cargos',
                'id_modulo' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ], //4
            [
                'nom_menu' => 'Menus',
                'link_menu' => 'menus',
                'desc_menu' => 'Menus',
                'id_modulo' => 3,
                'created_at' => now(),
                'updated_at' => now()
            ], //5
            [
                'nom_menu' => 'Submenus',
                'link_menu' => 'submenus',
                'desc_menu' => 'Submenus',
                'id_modulo' => 3,
                'created_at' => now(),
                'updated_at' => now()
            ], //6
            [
                'nom_menu' => 'Modulos',
                'link_menu' => 'modulos',
                'desc_menu' => 'Modulos',
                'id_modulo' => 3,
                'created_at' => now(),
                'updated_at' => now()
            ], //7
            [
                'nom_menu' => 'Administracion Proyectos',
                'link_menu' => 'administrar-proyectos',
                'desc_menu' => 'Administracion Proyectos',
                'id_modulo' => 4,
                'created_at' => now(),
                'updated_at' => now()
            ], //8
            [
                'nom_menu' => 'Gestion Ingenieros Obras',
                'link_menu' => 'gestion-proyectos',
                'desc_menu' => 'Gestion Poryectos',
                'id_modulo' => 4,
                'created_at' => now(),
                'updated_at' => now()
            ], //9
            [
                'nom_menu' => 'Logs',
                'link_menu' => 'logs',
                'desc_menu' => 'Logs',
                'id_modulo' => 5,
                'created_at' => now(),
                'updated_at' => now()
            ], //10
            [
                'nom_menu' => 'Administrar Clientes',
                'link_menu' => 'administrar-clientes',
                'desc_menu' => 'Administrar Clientes',
                'id_modulo' => 6,
                'created_at' => now(),
                'updated_at' => now()
            ], //11
            [
                'nom_menu' => 'Configurar Procesos',
                'link_menu' => 'administracion-procesos-proyectos',
                'desc_menu' => 'Configurar Procesos',
                'id_modulo' => 7,
                'created_at' => now(),
                'updated_at' => now()
            ], //12
            [
                'nom_menu' => 'Proveedores',
                'link_menu' => 'admin-proveedores',
                'desc_menu' => 'Adminsitracion de Proveedores',
                'id_modulo' => 8,
                'created_at' => now(),
                'updated_at' => now()
            ], //13
            [
                'nom_menu' => 'cotizaciones',
                'link_menu' => 'administrar-cotizaciones',
                'desc_menu' => 'cotizaciones con proveedores',
                'id_modulo' => 8,
                'created_at' => now(),
                'updated_at' => now()
            ], //14
            [
                'nom_menu' => 'Historial Cotizaciones',
                'link_menu' => 'historial-cotizaciones',
                'desc_menu' => 'Historial Cotizaciones',
                'id_modulo' => 8,
                'created_at' => now(),
                'updated_at' => now()
            ], //15
            [
                'nom_menu' => 'Asistencias en Obra',
                'link_menu' => 'asistencias-obra',
                'desc_menu' => 'Asistencias en Obra',
                'id_modulo' => 9,
                'created_at' => now(),
                'updated_at' => now()
            ], //16
            [
                'nom_menu' => 'Cargue Masivos',
                'link_menu' => 'cargue-masivo',
                'desc_menu' => 'Cargue Masivos',
                'id_modulo' => 3,
                'created_at' => now(),
                'updated_at' => now()
            ], //17
            [
                'nom_menu' => 'Asistencia Obra',
                'link_menu' => 'asistencia-obra-personal',
                'desc_menu' => 'Asistencia Obra',
                'id_modulo' => 4,
                'created_at' => now(),
                'updated_at' => now()
            ], //18 
            [
                'nom_menu' => 'Administrar Personal',
                'link_menu' => 'administrar-personal',
                'desc_menu' => 'Administrar Personal',
                'id_modulo' => 9,
                'created_at' => now(),
                'updated_at' => now()
            ], //19
            [
                'nom_menu' => 'Gestion Encargado Obra',
                'link_menu' => 'gestion-encargado-obra',
                'desc_menu' => 'Gestion Encargado Obra',
                'id_modulo' => 4,
                'created_at' => now(),
                'updated_at' => now()
            ], //20
        ];

        DB::table('menu')->insert($menus);
    }
}
