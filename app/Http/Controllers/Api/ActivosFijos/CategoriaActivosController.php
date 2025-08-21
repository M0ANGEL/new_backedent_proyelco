<?php

namespace App\Http\Controllers\Api\ActivosFijos;

use App\Http\Controllers\Controller;
use App\Models\CategoriaActivos;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CategoriaActivosController extends Controller
{
    public function index()
    {
        //consulta a la bd los clientes
        $clientes = DB::connection('mysql')
            ->table('categoria_activos')
            ->join('users', 'categoria_activos.user_id', '=', 'users.id') // Se especifica users.user_id
            ->select('categoria_activos.*', 'users.nombre as usuario')
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
                'nombre' => ['required', 'string'],
                'descripcion' => ['required', 'string'],
                'prefijo' => ['required', 'string'],
            ]);

            //validación falla
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 409);
            }

            //validacion si el prefijo existe
            $existePrefijo = CategoriaActivos::where('prefijo', $request->prefijo)->exists();

            if ($existePrefijo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Prefijo en uso',
                ], 500);
            }

            $user = Auth::user();
            // Si la validación ok creamos la nueva categoría
            $cliente = new CategoriaActivos();
            $cliente->nombre = $request->nombre;
            $cliente->descripcion = $request->descripcion;
            $cliente->prefijo = $request->prefijo;
            $cliente->user_id = $user->id;
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
        return response()->json(CategoriaActivos::find($id), 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => ['required', 'string'],
                'descripcion' => ['required', 'string'],
                'prefijo' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // validar que el codigo del proyecto no este usado por otro
            $proyectoUnico = CategoriaActivos::where('prefijo', $request->prefijo)
                ->where('id', '!=', $id)
                ->first();
            if ($proyectoUnico) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error: Este prefijo ya esta registrado:   ',
                ], 404);
            }


            // Obtener la categoría actual
            $cliente = CategoriaActivos::findOrFail($id);

            // Actualizar la categoría
            $cliente->nombre = $request->nombre;
            $cliente->descripcion = $request->descripcion;
            $cliente->prefijo = $request->prefijo;
            $cliente->user_id = Auth::id();
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
        $categoria = CategoriaActivos::find($id);

        $categoria->estado = !$categoria->estado;
        $categoria->update();
    }
}
