<?php

namespace App\Http\Controllers\Api\TalentoHumano\AsistenObras;

use App\Http\Controllers\Controller;
use App\Models\AsistenciasObra;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AsistenciasObrasController extends Controller
{
    public function index()
    {
        $asistencia = DB::connection('mysql')
            ->table('asistencias_obra_create')
            ->join('personal', 'personal.id', '=', 'asistencias_obra_create.personal_id')
            ->leftJoin('users', 'users.id', '=', 'asistencias_obra_create.usuario_confirma')
            ->join('cargos', 'cargos.id', '=', 'personal.cargo_id')
            ->join('proyecto', 'proyecto.id', '=', 'asistencias_obra_create.proyecto_id')
            ->select(
                'asistencias_obra_create.*',
                'personal.nombres',
                'personal.apellidos',
                'cargos.nombre as cargo',
                'proyecto.descripcion_proyecto',
                'users.nombre as usurioConfirma'
            )
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $asistencia
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'proyecto_id' => ['required'],
                'fecha_programacion' => ['required', 'string'],
            ]);

            //validación falla
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            foreach ($request->personal_id as $usuario_id) {
                $asistencia = new AsistenciasObra();
                $asistencia->personal_id = $usuario_id;
                $asistencia->proyecto_id = $request->proyecto_id;
                $asistencia->fecha_programacion = Carbon::parse($request->fecha_programacion);
                $asistencia->usuario_asigna = Auth::id(); 
                $asistencia->save();
            }


            return response()->json([
                'status' => 'success',
                'data' => $asistencia
            ], 200);
        } catch (Exception $e) {
            // Manejo de errores
            return response()->json([
                'status' => 'error',
                'message' => 'error ' . $e->getMessage(),
            ], 500);
        }
    }

    // public function show($id)
    // {
    //     return response()->json(AsistenciasObra::find($id), 200);
    // }

    // public function update(Request $request, $id)
    // {
    //     try {
    //         $validator = Validator::make($request->all(), [
    //             'nombres' => ['required', 'string'],
    //             'apellidos' => ['required', 'string'],
    //             'cedula' => ['required', 'string'],
    //             'telefono' => ['required', 'string'],
    //             'cargo_id' => ['required', 'string'],
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json(['errors' => $validator->errors()], 400);
    //         }

    //         // Obtener la categoría actual
    //         $Personal = AsistenciasObra::findOrFail($id);
    //         $Personal->nombres = $request->nombres;
    //         $Personal->apellidos = $request->apellidos;
    //         $Personal->cedula = $request->cedula;
    //         $Personal->telefono = $request->telefono;
    //         $Personal->cargo_id = $request->cargo_id;
    //         // $Personal->id_user = Auth::id();
    //         $Personal->save();

    //         return response()->json([
    //             'status' => 'success',
    //             'data' => $Personal
    //         ], 200);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Error: ' . $e->getMessage(),
    //             'code' => $e->getCode()
    //         ], 500);
    //     }
    // }

    // public function destroy($id)
    // {
    //     $Personal = AsistenciasObra::find($id);

    //     $Personal->estado = !$Personal->estado;
    //     $Personal->update();
    // }

    public function proyectosActivos()
    {
        $proyectos = DB::connection('mysql')
            ->table('proyecto')
            ->where('estado', 1)
            ->select(
                'id',
                'descripcion_proyecto',
            )
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $proyectos
        ]);
    }

    public function empleados()
    {
        $empleados = DB::connection('mysql')
            ->table('personal')
            ->where('estado', 1)
            ->select(
                'id',
                'nombres',
                'apellidos',
            )
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $empleados
        ]);
    }
}
