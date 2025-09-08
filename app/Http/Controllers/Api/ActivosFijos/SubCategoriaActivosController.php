<?php

namespace App\Http\Controllers\Api\ActivosFijos;

use App\Http\Controllers\Controller;
use App\Models\SubCategoriaActivos;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SubCategoriaActivosController extends Controller
{
     public function index()
    {
        //consulta a la bd los clientes
        $clientes = DB::connection('mysql')
            ->table('subcategoria_activos')
            ->join('users', 'subcategoria_activos.user_id', '=', 'users.id') 
            ->join('categoria_activos', 'subcategoria_activos.categoria_id', '=', 'categoria_activos.id') 
            ->select('subcategoria_activos.*', 'users.nombre as usuario','categoria_activos.nombre as categoria') 
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
                'categoria_id' => ['required'],
            ]);

            //validación falla
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $user = Auth::user();
            // Si la validación ok creamos la nueva categoría
            $cliente = new SubCategoriaActivos();
            $cliente->nombre = $request->nombre;
            $cliente->categoria_id = $request->categoria_id;
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
        return response()->json(SubCategoriaActivos::find($id), 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => ['required', 'string'],
                'categoria_id' => ['required'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // Obtener la categoría actual
            $cliente = SubCategoriaActivos::findOrFail($id);

            // Actualizar la categoría
            $cliente->nombre = $request->nombre;
            $cliente->categoria_id = $request->categoria_id;
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
        $categoria = SubCategoriaActivos::find($id);

        $categoria->estado = !$categoria->estado;
        $categoria->update();
    }

    public function SubcategoriaFiltrado($id){
        $datos = SubCategoriaActivos::where('categoria_id',$id)->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $datos
        ]);
    }
}
