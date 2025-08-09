<?php

namespace App\Http\Controllers\Api\TalentoHumano\AsistenObras;

use App\Http\Controllers\Controller;
use App\Models\AsistenciasObra;
use App\Models\Proyectos;
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
                'personal.cedula',
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
                'fecha_programacion' => ['required', 'array', 'size:2'], // Esperas rango: inicio y fin
                'personal_id' => ['required', 'array'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // Tomar la fecha de inicio y la fecha fin
            $fechaInicio = Carbon::parse($request->fecha_programacion[0]);
            $fechaFin = Carbon::parse($request->fecha_programacion[1]);

            // Validación: la fecha de inicio debe ser menor o igual que la fecha fin
            if ($fechaInicio->greaterThan($fechaFin)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La fecha de inicio no puede ser mayor a la fecha fin.',
                ], 400);
            }

            // Generar todas las fechas del rango
            $fechas = [];
            for ($date = $fechaInicio->copy(); $date->lte($fechaFin); $date->addDay()) {
                $fechas[] = $date->copy();
            }

            // Crear registros por cada fecha y cada persona
            foreach ($fechas as $fecha) {
                foreach ($request->personal_id as $usuario_id) {
                    $asistencia = new AsistenciasObra();
                    $asistencia->personal_id = $usuario_id;
                    $asistencia->proyecto_id = $request->proyecto_id;
                    $asistencia->fecha_programacion = $fecha;
                    $asistencia->usuario_asigna = Auth::id();
                    $asistencia->save();
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Asistencias creadas correctamente.',
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
        return response()->json(AsistenciasObra::find($id), 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'personal_id' => ['required', 'string'],
                'proyecto_id' => ['required', 'string'],
                'fecha_programacion' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // Obtener la categoría actual
            $Personal = AsistenciasObra::findOrFail($id);
            $Personal->personal_id = $request->personal_id;
            $Personal->proyecto_id = $request->proyecto_id;
            $Personal->fecha_programacion = Carbon::parse($request->fecha_programacion);


            $Personal->save();

            return response()->json([
                'status' => 'success',
                'data' => $Personal
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
                'code' => $e->getCode()
            ], 500);
        }
    }

    public function confirmarAsistencias(Request $request)
    {
        $Personal = AsistenciasObra::find($request->id);

        $Personal->detalle = $request->detalle ? $request->detalle : "Sin detalle";
        $Personal->usuario_confirma = Auth::id();
        $Personal->fecha_confirmacion = now();
        $Personal->confirmacion = "1";
        $Personal->update();
    }

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
                'cedula',
            )
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $empleados
        ]);
    }

    public function UsuarioConfirmarAsistencia()
    {
        // Obtengo las obras donde el usuario autenticado es el encargado
        // $obras = Proyectos::where('encargado_id', Auth::id())->pluck('id'); // Solo IDs
        $obras = Proyectos::whereJsonContains('encargado_id', Auth::id())
        ->pluck('id');


        // Fecha actual
        $hoy = Carbon::now()->toDateString();

        // Consulto las asistencias de esas obras
        $asistencias = DB::connection('mysql')
            ->table('asistencias_obra_create')
            ->join('personal', 'personal.id', '=', 'asistencias_obra_create.personal_id')
            ->leftJoin('users', 'users.id', '=', 'asistencias_obra_create.usuario_confirma')
            ->join('cargos', 'cargos.id', '=', 'personal.cargo_id')
            ->join('proyecto', 'proyecto.id', '=', 'asistencias_obra_create.proyecto_id')
            ->whereIn('proyecto_id', $obras) // Aquí debe ir solo IDs
            ->where('fecha_programacion', $hoy) // para que solo muestre los usuarios de el dia actual
            ->select(
                'asistencias_obra_create.*',
                'personal.nombres',
                'personal.apellidos',
                'personal.cedula',
                'cargos.nombre as cargo',
                'proyecto.descripcion_proyecto',
                'proyecto.id as proyecto_id',
                'users.nombre as usurioConfirma'
            )
            ->get();

        // Agregar columna virtual 'activo' al resultado
        $asistencias->transform(function ($item) use ($hoy) {
            $item->activo = ($item->fecha_programacion == $hoy) ? "1" : "0";
            return $item;
        });

        return response()->json([
            'status' => 'success',
            'data' => $asistencias,
        ]);
    }

    public function confirmarNoAsistencias(Request $request)
    {
        $Personal = AsistenciasObra::find($request->id);

        $Personal->detalle = $request->motivo . ($request->detalle ?? "Sin detalle");
        $Personal->usuario_confirma = Auth::id();
        $Personal->fecha_confirmacion = now();
        $Personal->confirmacion = "2";
        $Personal->update();
    }

    public function cambioProyectoAsistencia(Request $request)
    {
        $Personal = AsistenciasObra::find($request->id);
        $Personal->proyecto_id = $request->proyecto_id;
        $Personal->update();
    }
}
