<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPerfilLog;
use App\Models\UsersPerfiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UsersPerfilesController extends Controller
{
    public function index()
    {
        $usersPerfiles = UsersPerfiles::all();
        return response()->json($usersPerfiles);
    }

    public function show($id)
    {
        $userPerfil = UsersPerfiles::find($id);
        return response()->json($userPerfil);
    }

    public function store(Request $request)
    {
        $userPerfil = new UsersPerfiles;
        $userPerfil->id_user = $request->id_user;
        $userPerfil->id_perfil = $request->id_perfil;
        $userPerfil->save();

        // registrar acción en User_Perfil_Log
        $log = new UserPerfilLog();
        $log->id_user = Auth::user()->id; // id del usuario autenticado "quien lo modifico"
        $log->id_perfil = $userPerfil->id; // id del perfil "recien creado"
        $log->accion = 'Se creo el usuario con el perfil id ' . $userPerfil->id;
        $log->data = $userPerfil;
        $log->old = 'registro nuevo sin data anterior';
        $log->save();

        return response()->json($userPerfil);
    }

    public function update(Request $request, $id)
    {
        $datos = UsersPerfiles::find($id);
        $userPerfil = clone $datos;
        $userPerfil->id_user = $request->id_user;
        $userPerfil->id_perfil = $request->id_perfil;
        $userPerfil->save();

        // registrar acción en User_Perfil_Log
        $log = new UserPerfilLog();
        $log->id_user = Auth::user()->id; // id del usuario autenticado "quien lo modifico"
        $log->id_perfil = $userPerfil->id; // id del perfil "recien creado"
        $log->accion = 'Se creo el usuario con el perfil id ' . $userPerfil->id;
        $log->data = $userPerfil;
        $log->old = $datos;
        $log->save();

        return response()->json($userPerfil);
    }

    public function destroy($id)
    {
        $userPerfil = UsersPerfiles::find($id);
        $userPerfil->delete();

        return response()->json('Registro eliminado correctamente');
    }
}
