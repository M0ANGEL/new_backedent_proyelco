<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\CargoLog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CargoController extends Controller
{
    public function index()
    {
        try {
            $cargos = Cargo::select(
                'id',
                'nombre',
                'descripcion',
                'estado',
                'id_empresa'
            )
                ->get()
                ->load('empresas');

            return response()->json([
                'status' => 'success',
                'data' => $cargos,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se puede listar los datos de los cargos.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => ['required', 'string', 'unique:cargos,nombre'],
                'descripcion' => ['required', 'string'],
                'id_empresa' => ['required', 'numeric']
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $cargo = new Cargo();
            $cargo->nombre = $request->nombre;
            $cargo->descripcion = $request->descripcion;
            $cargo->id_empresa = $request->id_empresa;
            $cargo->save();

                // registrar acción en cargo_logs
                $log = new CargoLog();
                $log->id_user = Auth::user()->id; // id del usuario autenticado "quien lo modifico"
                $log->id_cargo = $cargo->id; // id de la cargo "recien creada"
                $log->accion = 'Se creo la cargo con id ' . $cargo->id;
                $log->data = $cargo;
                $log->old = 'registro nuevo sin data anterior';
                $log->save();

            return response()->json([
                'status' => 'success',
                'data' => $cargo,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se puede guardar los datos del cargo.',
            ], 500);
        }
    }

    public function show($id)
    {
        $cargo = Cargo::with('empresas')->findOrFail($id, ['id', 'nombre', 'descripcion', 'id_empresa', 'estado']);

        if (!$cargo) {
            return response()->json(['error' => 'Cargo no encontrado'], 404);
        }

        $cargoConEmpresa = [
            'id' => $cargo->id,
            'nombre' => $cargo->nombre,
            'descripcion' => $cargo->descripcion,
            'estado' => $cargo->estado,
            'id_empresa' => $cargo->id_empresa,
            'empresa' => $cargo->empresas,
        ];

        return response()->json($cargoConEmpresa, 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => ['required', 'string'],
                'descripcion' => ['required', 'string'],
                'id_empresa' => ['required', 'string']
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $data = Cargo::findOrFail($id);
            $cargo = clone $data;
            $cargo->update($validator->validated());

            // registrar acción en cargo_logs
            $log = new CargoLog();
            $log->id_user = Auth::user()->id; // id del usuario autenticado "quien lo modifico"
            $log->id_cargo = $cargo->id; // id de la cargo "recien creada"
            $log->accion = 'Se actualizo la cargo con id ' . $cargo->id;
            $log->data = $cargo;
            $log->old = $data;
            $log->save();

            return response()->json([
                'status' => 'success',
                'data' => $cargo,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se puede actualizar los datos del cargo.',
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $data = Cargo::findOrFail($id);
            $cargo = clone $data;
            $cargo->estado = $cargo->estado == 1 ? 0 : 1;
            $cargo->save();

            $accion = $cargo->estado == 1 ? 'Se activo' : 'Se desactivo';

            // registrar acción en cargo_Logs
            $log = new CargoLog();
            $log->id_user = Auth::user()->id;
            $log->id_cargo = $cargo->id;
            $log->accion = $accion.' el cargo con id '.$cargo->id;
            $log->data = $cargo;
            $log->old = $data;
            $log->save();


            return response()->json(['message' => 'El estado del cargo se actualizó correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ocurrió un error al actualizar el estado del cargo.'], 500);
        }
    }
}
