<?php

namespace App\Http\Controllers;

use App\Models\PerfilesModulos;
use Illuminate\Http\Request;

class PerfilesModulosController extends Controller
{
    public function index()
    {
        $perfilesModulos = PerfilesModulos::all();
        return response()->json($perfilesModulos);
    }

    public function show($id)
    {
        $perfilModulo = PerfilesModulos::find($id);
        return response()->json($perfilModulo);
    }

    public function store(Request $request)
    {
        $perfilModulo = new PerfilesModulos;
        $perfilModulo->id_modulo = $request->id_modulo;
        $perfilModulo->id_perfil = $request->id_perfil;
        $perfilModulo->save();

        return response()->json($perfilModulo);
    }

    public function update(Request $request, $id)
    {
        $perfilModulo = PerfilesModulos::find($id);
        $perfilModulo->id_modulo = $request->id_modulo;
        $perfilModulo->id_perfil = $request->id_perfil;
        $perfilModulo->save();

        return response()->json($perfilModulo);
    }

    public function destroy($id)
    {
        $perfilModulo = PerfilesModulos::find($id);
        $perfilModulo->delete();

        return response()->json('Registro eliminado correctamente');
    }
}
