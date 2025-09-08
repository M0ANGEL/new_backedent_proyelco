<?php

namespace App\Http\Controllers\Api\ActivosFijos;

use App\Http\Controllers\Controller;
use App\Models\Bodegas;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BodegasController extends Controller
{
    public function index() 
    {
        //consulta a la bd los clientes
        $clientes = DB::connection('mysql')
            ->table('bodegas_area')
            ->join('users', 'bodegas_area.user_id', '=', 'users.id') // Se especifica users.user_id
            ->select('bodegas_area.*', 'users.nombre as usuario') 
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $clientes
        ]);
    }

    public function obras() 
    {
        //consulta a la bd los clientes
        $clientes = DB::connection('mysql')
            ->table('proyecto')
            ->select('id','descripcion_proyecto as nombre') 
            ->where('estado',1)
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
                'direccion' => ['required', 'string'],
                'nombre' => ['required', 'string'],
            ]);

            //validación falla
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $user = Auth::user();
            // Si la validación ok creamos la nueva categoría
            $cliente = new Bodegas();
            $cliente->direccion = $request->direccion;
            $cliente->nombre = $request->nombre;
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
        return response()->json(Bodegas::find($id), 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'direccion' => ['required', 'string'],
                'nombre' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // Obtener la categoría actual
            $cliente = Bodegas::findOrFail($id);

            // Actualizar la categoría
            $cliente->direccion = $request->direccion;
            $cliente->nombre = $request->nombre;
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
        $categoria = Bodegas::find($id);

        $categoria->estado = !$categoria->estado;
        $categoria->update();
    }
}
