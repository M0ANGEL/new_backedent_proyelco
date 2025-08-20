<?php

namespace App\Http\Controllers\Api\ActivosFijos;

use App\Http\Controllers\Controller;
use App\Models\Activo;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ActivosController extends Controller
{
    public function index()
    {
        //consulta a la bd los clientes
        $clientes = DB::connection('mysql')
            ->table('activo')
            ->join('users', 'activo.user_id', '=', 'users.id')
            ->join('categoria_activos', 'activo.categoria_id', '=', 'categoria_activos.id')
            ->join('subcategoria_activos', 'activo.subcategoria_id', '=', 'subcategoria_activos.id')
            ->select(
                'activo.*',
                'users.nombre as usuario',
                'categoria_activos.nombre as categoria',
                'subcategoria_activos.nombre as subcategoria'
            )
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
                'categoria_id' => ['required'],
                'subcategoria_id' => ['required'],
                'numero_activo' => ['required', 'string'],
                'valor' => ['required', 'string'],
                'condicion' => ['required'],
                'ubicacion_actual' => ['required'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $user = Auth::user();

            $cliente = new Activo();
            $cliente->numero_activo = $request->numero_activo;
            $cliente->categoria_id = $request->categoria_id;
            $cliente->subcategoria_id = $request->subcategoria_id;
            $cliente->user_id = $user->id;
            $cliente->descripcion = $request->descripcion ? $request->descripcion : "..";
            $cliente->valor = $request->valor;
            $cliente->fecha_fin_garantia = Carbon::parse($request->fecha_fin_garantia)->format('Y-m-d');
            $cliente->condicion = $request->condicion;
            $cliente->marca = $request->marca ? $request->marca : null;
            $cliente->serial = $request->serial ? $request->serial : null;
            $cliente->ubicacion_actual_id = $request->ubicacion_actual;
            $cliente->save(); // se guarda para obtener el ID

            

            // Si hay archivo, lo guardamos usando el ID como nombre
            if ($request->hasFile('file')) {
                $extension = $request->file('file')->getClientOriginalExtension();
                $request->file('file')->storeAs('public/activos', $cliente->id . '.' . $extension);
            }

            return response()->json([
                'status' => 'success',
                'data' => $cliente
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'error ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        return response()->json(Activo::find($id), 200);
        
    }

    public function update(Request $request, $id)
    {
        try {
             $validator = Validator::make($request->all(), [
                'categoria_id' => ['required'],
                'subcategoria_id' => ['required'],
                'numero_activo' => ['required', 'string'],
                'valor' => ['required', 'string'],
                'condicion' => ['required'],
                'ubicacion_actual' => ['required'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // validar que el codigo del proyecto no este usado por otro
            $proyectoUnico = Activo::where('numero_activo', $request->numero_activo)
                ->where('id', '!=', $id)
                ->first();
            if ($proyectoUnico) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error: Este numero de activo ya esta registrado:   ',
                ], 404);
            }

            $cliente = new Activo();
            $cliente->numero_activo = $request->numero_activo;
            $cliente->categoria_id = $request->categoria_id;
            $cliente->subcategoria_id = $request->subcategoria_id;
            $cliente->descripcion = $request->descripcion ? $request->descripcion : "..";
            $cliente->valor = $request->valor;
            $cliente->fecha_fin_garantia = Carbon::parse($request->fecha_fin_garantia)->format('Y-m-d');
            $cliente->condicion = $request->condicion;
            $cliente->ubicacion_actual_id = $request->ubicacion_actual;
            $cliente->marca = $request->marca ? $request->marca : null;
            $cliente->serial = $request->serial ? $request->serial : null;
            $cliente->save(); // se guarda para obtener el ID

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
        $categoria = Activo::find($id);

        $categoria->estado = !$categoria->estado;
        $categoria->update();
    }

    public function usuariosAsignacion()
    {
        $datos = User::select('id', 'nombre')->get();

        return response()->json([
            'status' => 'success',
            'data' => $datos
        ]);
    }

    // ActivoController.php
    public function verActivoQR($id)
    {

        $activo = DB::connection('mysql')
            ->table('activo')
            ->join('users', 'activo.user_id', '=', 'users.id')
            ->join('categoria_activos', 'activo.categoria_id', '=', 'categoria_activos.id')
            ->join('subcategoria_activos', 'activo.categoria_id', '=', 'subcategoria_activos.id')
            ->join('bodegas_area', 'activo.ubicacion_id', '=', 'bodegas_area.id')
            ->select(
                'activo.*',
                'users.nombre as usuario',
                'categoria_activos.nombre as categoria',
                'subcategoria_activos.nombre as subcategoria',
                'bodegas_area.nombre as ubicacion',
            )
            ->where('activo.id', $id)
            ->first($id);
        return view('activos.qr', compact('activo'));
    }
}
