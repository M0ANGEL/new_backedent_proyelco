<?php

namespace App\Http\Controllers\Api\ActivosFijos;

use App\Http\Controllers\Controller;
use App\Models\Activo;
use App\Models\MantenimientoActivos;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MantenimientoActivosController extends Controller
{
    public function index()
    {
        //consulta a la bd los clientes
        $clientes = DB::connection('mysql')
            ->table('mantenimiento_activos')
            ->join('activo', 'mantenimiento_activos.activo_id', 'activo.id')
            ->select(
                'mantenimiento_activos.*',
                'activo.numero_activo',
            )
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $clientes
        ]);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'valor' => ['required'],
                'fecha_inicio' => ['required'],
                'fecha_fin' => ['required', 'string'],
                'observacion' => ['required', 'string'],
                'activo_id' => ['required'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $mantenimiento = new MantenimientoActivos();
            $mantenimiento->valor = $request->valor;
            $mantenimiento->fecha_inicio = Carbon::parse($request->fecha_inicio)->format('Y-m-d');
            $mantenimiento->fecha_fin = Carbon::parse($request->fecha_fin)->format('Y-m-d');
            $mantenimiento->activo_id = $request->activo_id;
            $mantenimiento->observaciones = $request->observacion;
            $mantenimiento->user_id = Auth::id();
            $mantenimiento->save();

            $cliente = Activo::find($request->activo_id);

            if (!$cliente) {
                throw new Exception("Activo no encontrado.");
            }

            $cliente->aceptacion = 4; // mantenimiento
            $cliente->save();

            DB::commit(); // ✅ 

            return response()->json([
                'status' => 'success',
                'data' => $cliente
            ], 200);
        } catch (Exception $e) {
            DB::rollBack(); // ❌ 

            return response()->json([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
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
                'valor' => ['required'],
                'fecha_inicio' => ['required'],
                'fecha_fin' => ['required', 'string'],
                'observacion' => ['required', 'string'],
                'activo_id' => ['required'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $mantenimiento = new MantenimientoActivos();
            $mantenimiento->valor = $request->valor;
            $mantenimiento->fecha_inicio = Carbon::parse($request->fecha_inicio)->format('Y-m-d');
            $mantenimiento->fecha_fin = Carbon::parse($request->fecha_fin)->format('Y-m-d');
            $mantenimiento->activo_id = $request->activo_id;
            $mantenimiento->observaciones = $request->observacion;
            $mantenimiento->user_id = Auth::id();
            $mantenimiento->save();

            $cliente = Activo::find($request->activo_id);

            if (!$cliente) {
                throw new Exception("Activo no encontrado.");
            }

            $cliente->aceptacion = 4; // mantenimiento
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
        DB::beginTransaction();

        try {
            $mantenimiento = MantenimientoActivos::find($id);

            if (!$mantenimiento) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Mantenimiento no encontrado.',
                ], 404);
            }

            // Toggle estado
            $mantenimiento->estado = 0;
            $mantenimiento->update();

            $activo = Activo::find($mantenimiento->activo_id);

            if (!$activo) {
                throw new Exception("Activo relacionado no encontrado.");
            }

            $activo->aceptacion = 0; // Termina el mantenimiento
            $activo->ubicacion_destino_id = null; // Termina el mantenimiento
            $activo->update();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Mantenimiento actualizado y activo restaurado.'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }


    public function activosBodegaPrincipal()
    {

        $data = DB::connection('mysql')
            ->table('activo')
            ->whereIn('aceptacion', ['0', '3'])
            ->select('numero_activo', 'id')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }
}
