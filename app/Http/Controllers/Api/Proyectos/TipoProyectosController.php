<?php

namespace App\Http\Controllers\Api\Proyectos;

use App\Http\Controllers\Controller;
use App\Models\TipoProyectos;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TipoProyectosController extends Controller
{
    public function index()
    {
        //consulta a la bd los clientes
        $tipos_de_proyectos = DB::connection('mysql')
            ->table('tipos_de_proyectos')
            ->join('users', 'tipos_de_proyectos.user_id', '=', 'users.id') // Se especifica users.user_id
            ->select('tipos_de_proyectos.*', 'users.nombre as nombre') // Seleccionamos todas las columnas de tk_categorias y el nombre del usuario
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $tipos_de_proyectos
        ]);
    }


    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'emp_nombre' => ['required', 'string'],
                'nit' => ['required', 'string'],
                'direccion' => ['required', 'string'],
                'telefono' => ['required', 'string'],
                'cuenta_de_correo' => ['required', 'string'],
            ]);

            //validación falla
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $user = Auth::user();
            // Si la validación ok creamos la nueva categoría
            $cliente = new TipoProyectos();
            $cliente->emp_nombre = $request->emp_nombre;
            $cliente->nit = $request->nit;
            $cliente->direccion = $request->direccion;
            $cliente->telefono = $request->telefono;
            $cliente->cuenta_de_correo = $request->cuenta_de_correo;
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
        return response()->json(TipoProyectos::find($id), 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'emp_nombre' => ['required', 'string'],
                'nit' => ['required', 'string'],
                'direccion' => ['required', 'string'],
                'telefono' => ['required', 'string'],
                'cuenta_de_correo' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // Obtener la categoría actual
            $cliente = TipoProyectos::findOrFail($id);

            // Si el prefijo no cambió, permitir actualización normal
            if ($cliente->nit !== $request->nit) {
                // Verificar si hay tickets con este prefijo
                $existeTicket = DB::table('clientes')->where('nit', $request->nit)->exists();

                if ($existeTicket) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No se puede actualizar el nit porque ya hay un cliente con este NIT.'
                    ], 400);
                }
            }

            // Actualizar la categoría
            $cliente->emp_nombre = $request->emp_nombre;
            $cliente->nit = $request->nit;
            $cliente->direccion = $request->direccion;
            $cliente->telefono = $request->telefono;
            $cliente->cuenta_de_correo = $request->cuenta_de_correo;
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
        $categoria = TipoProyectos::find($id);

        $categoria->estado = !$categoria->estado;
        $categoria->update();
    }
}
