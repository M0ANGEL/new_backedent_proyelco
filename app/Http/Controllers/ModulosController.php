<?php

namespace App\Http\Controllers;

use App\Models\Modulo;
use Illuminate\Http\Request;
use App\Models\UsersPerfiles;
use App\Models\Perfil;

class ModulosController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $userPerfil = UsersPerfiles::where('id_user', $user->id)->first();
        $perfil = Perfil::find($userPerfil->id_perfil);
        
        $modulos = Modulo::with('menus.submenus')->get();

        if ($perfil->nom_perfil !== 'SUPER_ADMIN') {
            foreach ($modulos as $modulo) {
                if ($modulo->cod_modulo === 'GSTHUM') {
                    foreach ($modulo->menus as $menu) {
                        $menu->estado = 1;
                        foreach ($menu->submenus as $submenu) {
                            $submenu->estado = 1;
                        }
                    }
                }
            }
        } else {
            foreach ($modulos as $modulo) {
                if ($modulo->cod_modulo === 'GSTHUM') {
                    foreach ($modulo->menus as $menu) {
                        $menu->estado = 0;
                        foreach ($menu->submenus as $submenu) {
                            $submenu->estado = 0;
                        }
                    }
                }
            }
        }
                
        return response()->json($modulos);
    }

    public function store(Request $request)
    {
        $modulo = new Modulo();
        $modulo->cod_modulo = $request->input('cod_modulo');
        $modulo->nom_modulo = $request->input('nom_modulo');
        $modulo->desc_modulo = $request->input('desc_modulo');
        $modulo->estado = $request->input('estado');
        $modulo->save();
        return response()->json($modulo);
    }

    public function show(Modulo $modulo)
    {
        return response()->json($modulo);
    }

    public function update(Request $request, Modulo $modulo)
    {
        $modulo->cod_modulo = $request->input('cod_modulo');
        $modulo->nom_modulo = $request->input('nom_modulo');
        $modulo->desc_modulo = $request->input('desc_modulo');
        $modulo->estado = $request->input('estado');
        $modulo->save();
        return response()->json($modulo);
    }

    public function destroy($id_modulo)
    {
        $modulo = Modulo::findOrFail($id_modulo);
        $modulo->estado = $modulo->estado == 1 ? 0 : 1;
        $modulo->save();
        return response()->json(['message' => 'Módulo desactivado correctamente']);
    }
}
