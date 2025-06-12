<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CargoUsuario;
use App\Models\CargoUsuarioLog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CargoUsuarioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $cargosUsuarios = CargoUsuario::select(
                'id',
                'id_user',
                'id_cargo',
                'estado'
            )->get();
        
            return response()->json([
                'status' => 'success',
                'data' => $cargosUsuarios,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se puede listar los datos de los cargos con sus usuarios',
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_user' => ['required', 'numeric'],
                'id_cargo_usuario' => ['required', 'numeric']
            ]);
    
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

                $cargoUsuario = new CargoUsuario();
                $cargoUsuario->id_user = $request->id_user;
                $cargoUsuario->id_cargo_usuario = $request->id_cargo_usuario;
                $cargoUsuario->save();

                // registrar acción en cargo_logs
                $log = new CargoUsuario();
                $log->id_ser = Auth::user()->id; // id del usuario autenticado "quien lo modifico"
                $log->id_cargo_usuario = $cargoUsuario->id; // id de la cargo "recien creada"
                $log->accion = 'Se creo la cargo usuario con id ' . $cargoUsuario->id;
                $log->data = $cargoUsuario;
                $log->old = 'registro nuevo sin data anterior';
                $log->save();

            return response()->json([
                'status' => 'success',
                'data' => $cargoUsuario,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se puede guardar los datos del cargo.',
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_user' => ['required', 'numeric'],
                'id_cargo_usuario' => ['required', 'numeric']
            ]);
    
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }
    
            $data = CargoUsuario::findOrFail($id);
            $cargoUsuario = clone $data;
            $cargoUsuario->update($validator->validated());

            // registrar acción en cargo_logs
            $log = new CargoUsuarioLog();
            $log->id_ser = Auth::user()->id; // id del usuario autenticado "quien lo modifico"
            $log->id_cargo_usuario = $cargoUsuario->id; // id de la cargo "recien creada"
            $log->accion = 'Se actualizo la cargo usuario con id ' . $cargoUsuario->id;
            $log->data = $cargoUsuario;
            $log->old = $data;
            $log->save();

            return response()->json([
                'status' => 'success',
                'data' => $cargoUsuario,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se puede actualizar los datos del cargo.',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $data = CargoUsuario::findOrFail($id);
            $cargoUsuario = clone $data;
            $cargoUsuario->estado = $cargoUsuario->estado == 1 ? 0 : 1;
            $cargoUsuario->save();

            $accion = $cargoUsuario->estado == 1 ? 'Se activo' : 'Se desactivo';

            // registrar acción en cargo_Logs
            $log = new CargoUsuarioLog();
            $log->id_user = Auth::user()->id;
            $log->id_cargo_usuario = $cargoUsuario->id; 
            $log->accion = $accion.' el cargo con id '.$cargoUsuario->id;
            $log->data = $cargoUsuario;
            $log->old = $data;
            $log->save();
    
            return response()->json(['message' => 'El estado del cargo y su usuario se actualizó correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ocurrió un error al actualizar el estado del cargo y el usuario'], 500);
        }
    }
}
