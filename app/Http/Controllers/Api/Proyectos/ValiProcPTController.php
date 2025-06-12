<?php

namespace App\Http\Controllers\Api\Proyectos;

use App\Http\Controllers\Controller;
use App\Models\ValidacionXProcesoXProyecto;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ValiProcPTController extends Controller
{
    public function index()
    {
        //consulta a la bd los clientes
        $clientes = DB::connection('mysql')
            ->table('procesos_proyectos')
            ->join('users', 'procesos_proyectos.user_id', '=', 'users.id') // Se especifica users.user_id
            ->select('procesos_proyectos.*', 'users.nombre as nombre') // Seleccionamos todas las columnas de tk_categorias y el nombre del usuario
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $clientes
        ]);
    }


    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tipoPoryecto_id' => ['required', 'string'],
                'nombre_proceso' => ['required', 'string'],
            ]);

            //validación falla
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $user = Auth::user();
            // Si la validación ok creamos la nueva categoría
            $cliente = new ValidacionXProcesoXProyecto();
            $cliente->tipoPoryecto_id = $request->tipoPoryecto_id;
            $cliente->nombre_proceso = $request->nombre_proceso;
            $cliente->id_user = $user->id;
            $cliente->save();

            return response()->json([
                'status' => 'success',
                'data' => $cliente
            ], 200);
        } catch (Exception $e) {
            // Manejo de errores
            return response()->json([
                'status' => 'error',
                'message' => 'error ' . $e->getMessage(),
            ], 500);
        }
    }


    public function show($id)
    {
        return response()->json(ValidacionXProcesoXProyecto::find($id), 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tipoPoryecto_id' => ['required', 'string'],
                'nombre_proceso' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // Obtener la categoría actual
            $cliente = ValidacionXProcesoXProyecto::findOrFail($id);
            $cliente->tipoPoryecto_id = $request->tipoPoryecto_id;
            $cliente->nombre_proceso = $request->nombre_proceso;
            $cliente->id_user = Auth::id();
            $cliente->save();

            return response()->json([
                'status' => 'success',
                'data' => $cliente
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
                'code' => $e->getCode()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $categoria = ValidacionXProcesoXProyecto::find($id);

        $categoria->estado = !$categoria->estado;
        $categoria->update();
    }
}
