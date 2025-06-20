<?php

namespace App\Http\Controllers\Api\Proveedores;

use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProveedoresController extends Controller
{
  public function index()
    {
        //consulta a la bd los clientes
        $proveedores = DB::connection('mysql')
            ->table('proveedores')
            // ->join('users', 'proveedores.id_user', '=', 'users.id') // Se especifica users.user_id
            // ->select('proveedores.*', 'users.nombre as nombre') // Seleccionamos todas las columnas de tk_categorias y el nombre del usuario
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $proveedores
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => ['required', 'string'],
                'correo' => ['required', 'string', 'unique:proveedores,correo'],
                'ciudad' => ['required', 'string'],
                'telefono' => ['required', 'string'],
            ]);

            //validación falla
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $user = Auth::user();
            // Si la validación ok creamos la nueva categoría
            $proveedor = new Proveedor();
            $proveedor->nombre = $request->nombre;
            $proveedor->correo = $request->correo;
            $proveedor->ciudad = $request->ciudad;
            $proveedor->telefono = $request->telefono;
            $proveedor->id_user = $user->id;
            $proveedor->save();

            return response()->json([
                'status' => 'success',
                'data' => $proveedor
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
        return response()->json(Proveedor::find($id), 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => ['required', 'string'],
                'correo' => ['required', 'string'],
                'ciudad' => ['required', 'string'],
                'telefono' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // Obtener la categoría actual
            $proveedor = Proveedor::findOrFail($id);
            $proveedor->nombre = $request->nombre;
            $proveedor->correo = $request->correo;
            $proveedor->ciudad = $request->ciudad;
            $proveedor->telefono = $request->telefono;
            $proveedor->save();

            return response()->json([
                'status' => 'success',
                'data' => $proveedor
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
        $proveedor = Proveedor::find($id);

        $proveedor->estado = !$proveedor->estado;
        $proveedor->update();
    }
}
