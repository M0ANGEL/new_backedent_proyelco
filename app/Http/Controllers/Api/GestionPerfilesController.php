<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Perfil;
use App\Models\PerfilesModulos;
use App\Models\PerfilLog;
use App\Models\UsersPerfiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GestionPerfilesController extends Controller
{

    public function index()
    {
        $user = auth()->user();
        $userPerfil = UsersPerfiles::where('id_user', $user->id)->first();
        $perfil = Perfil::find($userPerfil->id_perfil);

        if ($perfil->nom_perfil == 'SUPER_ADMIN') {
            $perfiles = Perfil::with('modulos', 'empresa')->get(['id', 'cod_perfil', 'nom_perfil', 'desc_perfil', 'id_empresa', 'estado']);
        } else {
            $perfiles = Perfil::with('modulos', 'empresa')
            ->where('nom_perfil', '!=', 'SUPER_ADMIN')
            ->get(['id', 'cod_perfil', 'nom_perfil', 'desc_perfil', 'id_empresa', 'estado']);
        }

        $perfilesConModulos = [];

        foreach ($perfiles as $perfil) {
            $perfilConModulos = [
                'id' => $perfil->id,
                'cod_perfil' => $perfil->cod_perfil,
                'nom_perfil' => $perfil->nom_perfil,
                'desc_perfil' => $perfil->desc_perfil,
                'estado' => $perfil->estado,
                'id_empresa' => $perfil->id_empresa,
                'modulos' => $perfil->modulos->toArray(),
                'empresa' => $perfil->empresa,
            ];

            $perfilesConModulos[] = $perfilConModulos;
        }

        return response()->json($perfilesConModulos, 200);
    }

    public function store(Request $request)
    {

        $datos = $request->json()->all();

        // Insertar los datos en la tabla "perfiles"
        $perfil = Perfil::create([
            'cod_perfil' => $datos['cod_perfil'],
            'nom_perfil' => $datos['nom_perfil'],
            'desc_perfil' => $datos['desc_perfil'],
            'estado' => $datos['estado'],
            'id_empresa' => $datos['id_empresa'],
        ]);

        // Insertar los datos en la tabla "perfiles_modulos"
        foreach ($datos['modulos'] as $item) {
            $dataSplit = explode('_', $item);
            $perfilModulo = new PerfilesModulos();
            $perfilModulo->id_perfil = $perfil->id;
            switch ($dataSplit[0]) {
                case 'modulo':
                    $perfilModulo->id_modulo = $dataSplit[1];
                    break;
                case 'menu':
                    $perfilModulo->id_modulo = $dataSplit[1];
                    $perfilModulo->id_menu = $dataSplit[2];
                    break;
                case 'submenu':
                    $perfilModulo->id_modulo = $dataSplit[1];
                    $perfilModulo->id_menu = $dataSplit[2];
                    $perfilModulo->id_submenu = $dataSplit[3];
                    break;
            }
            $perfilModulo->save();
        }

        // registrar acción en Perfiles_Logs
        $log = new PerfilLog();
        $log->id_user = Auth::user()->id; // id del usuario autenticado "quien lo modifico"
        $log->id_perfil = $perfil->id; // id del perfil "recien creado"
        $log->accion = 'Se creo el perfil con el id ' . $perfil->id;
        $log->data = $perfil;
        $log->old = 'registro nuevo sin data pasada';
        $log->save();

        // Retornar una respuesta exitosa
        return response()->json(['message' => 'Datos insertados correctamente'], 200);
    }

    public function show($id)
    {
        //$perfil = Perfil::has('modulos')->with('modulos')->find($id, ['id', 'cod_perfil', 'nom_perfil']);

        $perfil = Perfil::with('modulos.modulo', 'modulos.menu', 'modulos.submenu', 'empresa')->findOrFail($id, ['id', 'cod_perfil', 'nom_perfil', 'desc_perfil', 'id_empresa', 'estado']);

        if (!$perfil) {
            return response()->json(['error' => 'Perfil no encontrado'], 404);
        }

        $perfilConModulos = [
            'id' => $perfil->id,
            'cod_perfil' => $perfil->cod_perfil,
            'nom_perfil' => $perfil->nom_perfil,
            'desc_perfil' => $perfil->desc_perfil,
            'estado' => $perfil->estado,
            'id_empresa' => $perfil->id_empresa,
            'modulos' => $perfil->modulos->toArray(),
            'empresa' => $perfil->empresa,
        ];

        return response()->json($perfilConModulos, 200);
    }


    public function update(Request $request, Perfil $perfil)
    {
        $data = clone $perfil;
        // registrar acción en Perfiles_Logs
        $log = new PerfilLog();
        $log->id_user = Auth::user()->id; // id del usuario autenticado "quien lo modifico"
        $log->id_perfil = $perfil->id; // id del perfil "recien actualizado"
        $log->accion = 'Se actualizo el perfil con id ' . $perfil->id;
        $log->data = $perfil;
        $log->old = $data;
        $log->save();

        $perfil->cod_perfil = $request->input('cod_perfil');
        $perfil->nom_perfil = $request->input('nom_perfil');
        $perfil->desc_perfil = $request->input('desc_perfil');
        $perfil->id_empresa = $request->input('id_empresa');
        $perfil->estado = $request->input('estado');
        $perfil->save();

        PerfilesModulos::where('id_perfil', $perfil->id)->delete();

        foreach ($request->input('modulos') as $item) {
            $dataSplit = explode('_', $item);
            $perfilModulo = new PerfilesModulos();
            $perfilModulo->id_perfil = $perfil->id;
            switch ($dataSplit[0]) {
                case 'modulo':
                    $perfilModulo->id_modulo = $dataSplit[1];
                    break;
                case 'menu':
                    $perfilModulo->id_modulo = $dataSplit[1];
                    $perfilModulo->id_menu = $dataSplit[2];
                    break;
                case 'submenu':
                    $perfilModulo->id_modulo = $dataSplit[1];
                    $perfilModulo->id_menu = $dataSplit[2];
                    $perfilModulo->id_submenu = $dataSplit[3];
                    break;
            }
            $perfilModulo->save();
        }


        return response()->json($perfil);
    }


    public function destroy($id_perfil)
    {
        try {
            $datos = Perfil::findOrFail($id_perfil);
            $perfil = clone $datos;
            $perfil->estado = $perfil->estado == 1 ? 0 : 1;
            $perfil->save();

            $accion = $perfil->estado == 1 ? 'Se activo' : 'Se desactivo';

            // registrar acción en Perfiles_Logs
            $log = new PerfilLog();
            $log->id_user = Auth::user()->id; // id del usuario autenticado "quien lo modifico"
            $log->id_perfil = $perfil->id; // id del perfil "recien creado"
            $log->accion = $accion . ' Se actualizo el perfil con id ' . $perfil->id;
            $log->data = $perfil;
            $log->old = $datos;
            $log->save();

            return response()->json(['message' => 'Perfil cambia de estado correctamente']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ocurrió un error al actualizar el estado de la empresa y el usuario.'], 500);
        }
    }
}
