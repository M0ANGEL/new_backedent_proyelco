<?php

namespace App\Http\Controllers\Api\TalentoHumano\Asistencia;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ControlAsistenciasController extends Controller
{

    public function consultarUsuario(Request $request)
    {
        // Validar que la cédula venga en el request
        if (!$request->has('cedula') || empty($request->cedula)) {
            return response()->json([
                'status' => 'error',
                'message' => 'El número de cédula es requerido'
            ], 400);
        }

        try {
            // Buscar el empleado en ficha_th
            $empleado = DB::connection('mysql')
                ->table('ficha_th')
                ->select(
                    'tipo_empleado',
                    'id',
                    'empleado_id',
                    'identificacion'
                )
                ->where('identificacion', $request->cedula)
                ->where('estado', 1)
                ->first();

            // Si el empleado no existe o no está activo
            if (!$empleado) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La cédula no existe o el usuario no está activo. Comuníquese con el encargado de salud y seguridad en el trabajo.'
                ], 404);
            }

            // Obtener la fecha actual (solo fecha, sin hora)
            $fechaActual = now()->format('Y-m-d');

            // Buscar el último registro de asistencia del día actual
            $ultimaAsistencia = DB::connection('mysql')
                ->table('asistencias_th')
                ->where('tipo_empleado', $empleado->tipo_empleado)
                ->where('empleado_id', $empleado->empleado_id)
                ->where('identificacion', $empleado->identificacion)
                ->whereDate('fecha_ingreso', $fechaActual)
                ->orderBy('fecha_ingreso', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($ultimaAsistencia) {
                // Si ya tiene registro de entrada pero no tiene fecha de salida
                if ($ultimaAsistencia->fecha_ingreso && !$ultimaAsistencia->fecha_salida) {
                    if ($ultimaAsistencia->tipo_obra !== $request->tipo_obra && $ultimaAsistencia->obra_id !== $request->obra_id) {

                        if ($ultimaAsistencia->tipo_obra == 1) {
                            $proyecto = DB::connection('mysql')
                                ->table('proyecto')
                                ->select('descripcion_proyecto')
                                ->where('id', $ultimaAsistencia->obra_id)
                                ->first();
                        } else {
                            $proyecto = DB::connection('mysql')
                                ->table('proyectos_casas')
                                ->select('descripcion_proyecto')
                                ->where('id', $ultimaAsistencia->obra_id)
                                ->first();
                        }

                        return response()->json([
                            'status' => 'error',
                            'message' => 'El usuario se encuntra registrado en otra obra: ' . '[' . $proyecto->descripcion_proyecto . ']' . ' comunicate con el encargado de la obra y registre la salida'
                        ], 404);
                    }
                }
            }

            // Determinar el tipo de marcación
            $tipoMarcacion = 1; // Por defecto entrada

            if ($ultimaAsistencia) {
                // Si ya tiene registro de entrada pero no tiene fecha de salida
                if ($ultimaAsistencia->fecha_ingreso && !$ultimaAsistencia->fecha_salida) {
                    $tipoMarcacion = 2; // Salida
                }
                // Si ya tiene ambos registros (entrada y salida), nuevo registro de entrada
                else if ($ultimaAsistencia->fecha_ingreso && $ultimaAsistencia->fecha_salida) {
                    $tipoMarcacion = 1; // Nueva entrada
                }
            }

            // Obtener información adicional del empleado para la respuesta
            if ($empleado->tipo_empleado == 1) {
                $empleadoInfo = DB::connection('mysql')
                    ->table('empleados_proyelco_th')
                    ->select('nombre_completo')
                    ->where('identificacion', $request->cedula)
                    ->first();
            } else {
                $empleadoInfo = DB::connection('mysql')
                    ->table('empleados_th')
                    ->select('nombre_completo')
                    ->where('identificacion', $request->cedula)
                    ->first();
            }

            // 🔹 BUSCAR FOTO EN CARPETA SST POR EMPLEADO_ID
            $fotoUrl = null;
            $empleadoId = $empleado->id;

            if ($empleadoId) {
                // Definir los patrones de búsqueda
                $patrones = [
                    "empleado_{$empleadoId}.*", // Formato: empleado_id.extensión
                    "{$empleadoId}.*",          // Formato: id.extensión
                    "empleado_{$empleadoId}_*.*" // Formato: empleado_id_timestamp.extensión
                ];

                $rutaBase = storage_path('app/public/SST/');

                foreach ($patrones as $patron) {
                    $archivos = glob($rutaBase . $patron);

                    if (!empty($archivos)) {
                        // Tomar el primer archivo que coincida
                        $archivoFoto = basename($archivos[0]);
                        $fotoUrl = asset('storage/SST/' . $archivoFoto);

                        Log::info("Foto encontrada para empleado_id {$empleadoId}: " . $archivoFoto);
                        break; // Salir del loop cuando encuentre una foto
                    }
                }

                if (!$fotoUrl) {
                    Log::info("No se encontró foto para empleado_id: " . $empleadoId);
                    Log::info("Archivos en SST: " . json_encode(scandir($rutaBase)));
                }
            }

            // Construir la respuesta
            $response = [
                'status' => 'success',
                'data' => [
                    'empleado' => [
                        'identificacion' => $empleado->identificacion,
                        'nombre' => $empleadoInfo->nombre_completo ?? 'No disponible',
                        'tipo_empleado' => $empleado->tipo_empleado,
                        'empleado_id' => $empleado->empleado_id,
                        'foto_url' => $fotoUrl, // 🔹 URL de la foto encontrada
                    ],
                    'asistencia' => $ultimaAsistencia ? [
                        'fecha_ingreso' => $ultimaAsistencia->fecha_ingreso,
                        'hora_ingreso' => $ultimaAsistencia->hora_ingreso,
                        'fecha_salida' => $ultimaAsistencia->fecha_salida,
                        'hora_salida' => $ultimaAsistencia->hora_salida,
                        'ultima_actualizacion' => $ultimaAsistencia->updated_at ?? null,
                    ] : null,
                    'tipoMarcacion' => $tipoMarcacion,
                    'mensaje' => $tipoMarcacion == 1 ?
                        'Listo para registrar entrada' :
                        'Listo para registrar salida'
                ]
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            // Manejo de errores
            Log::error('Error en consultarUsuario: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al consultar la información del usuario: ' . $e->getMessage()
            ], 500);
        }
    }


    public function registrarMarcacion(Request $request)
    {
        // Validar los datos requeridos
        $validator = Validator::make($request->all(), [
            'cedula' => 'required|string',
            'tipo_marcacion' => 'required|in:1,2', // 1: Entrada, 2: Salida
            'serial' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Buscar el empleado
            $empleado = DB::connection('mysql')
                ->table('ficha_th')
                ->where('identificacion', $request->cedula)
                ->where('estado', 1)
                ->first();

            Log::info((array) $empleado);



            if (!$empleado) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Empleado no encontrado o inactivo'
                ], 404);
            }

            $fechaActual = now()->format('Y-m-d');
            $horaActual = now();
            $usuario = Auth::id();

            if ($request->tipo_marcacion == 1) {
                // Verificar si ya tiene una entrada sin salida hoy
                $entradaPendiente = DB::connection('mysql')
                    ->table('asistencias_th')
                    ->where('empleado_id', $empleado->empleado_id)
                    ->where('tipo_empleado', $empleado->tipo_empleado)
                    ->where('tipo_empleado', $empleado->tipo_empleado)
                    ->whereDate('fecha_ingreso', $fechaActual)
                    ->whereNull('fecha_salida')
                    ->first();

                Log::info((array) $entradaPendiente);


                if ($entradaPendiente) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Ya tienes una entrada registrada hoy. Debes registrar salida primero.'
                    ], 400);
                }

                // Registrar entrada
                $asistenciaId = DB::connection('mysql')
                    ->table('asistencias_th')
                    ->insertGetId([
                        'user_id' => $usuario,
                        'tipo_empleado' => $empleado->tipo_empleado,
                        'empleado_id' => $empleado->empleado_id,
                        'identificacion' => $empleado->identificacion,
                        'fecha_ingreso' => $fechaActual,
                        'hora_ingreso' => $horaActual->format('H:i:s'),
                        'tipo_obra' => $request->tipo_obra,
                        'obra_id' => $request->obra_id,
                    ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Entrada registrada correctamente',
                    'asistencia_id' => $asistenciaId,
                    'hora_entrada' => $horaActual->format('H:i:s')
                ]);
            } else {
                // Registrar salida - buscar el último registro de entrada del día
                $ultimaEntrada = DB::connection('mysql')
                    ->table('asistencias_th')
                    ->where('tipo_empleado', $empleado->tipo_empleado)
                    ->where('empleado_id', $empleado->empleado_id)
                    ->whereDate('fecha_ingreso', $fechaActual)
                    ->whereNull('fecha_salida')
                    ->orderBy('fecha_ingreso', 'desc')
                    ->first();

                if (!$ultimaEntrada) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No se encontró un registro de entrada para hoy'
                    ], 400);
                }

                // Calcular horas laborales
                $horaEntrada = \Carbon\Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $ultimaEntrada->fecha_ingreso . ' ' . $ultimaEntrada->hora_ingreso
                );

                $horaSalida = $horaActual;

                // Calcular diferencia en segundos
                $diferenciaSegundos = $horaSalida->diffInSeconds($horaEntrada);

                // Convertir segundos a formato time (HH:MM:SS)
                $horas = floor($diferenciaSegundos / 3600);
                $minutos = floor(($diferenciaSegundos % 3600) / 60);
                $segundos = $diferenciaSegundos % 60;

                $horasLaboralesTime = sprintf('%02d:%02d:%02d', $horas, $minutos, $segundos);

                // También calcular horas decimales para el mensaje
                $horasDecimales = round($diferenciaSegundos / 3600, 2);

                // Actualizar con la salida
                DB::connection('mysql')
                    ->table('asistencias_th')
                    ->where('id', $ultimaEntrada->id)
                    ->update([
                        'fecha_salida' => $fechaActual,
                        'hora_salida' => $horaActual->format('H:i:s'),
                        'horas_laborales' => $horasLaboralesTime,
                    ]);

                return response()->json([
                    'status' => 'success',
                    'message' => "Salida registrada correctamente. Horas laborales: $horasDecimales horas",
                    'horas_laborales' => $horasDecimales,
                    'hora_entrada' => $ultimaEntrada->hora_ingreso,
                    'hora_salida' => $horaActual->format('H:i:s')
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al registrar la marcación: ' . $e->getMessage()
            ], 500);
        }
    }

    //   public function reporteAsistencia(Request $request)
    // {
    //     try {
    //         $reporte = DB::table('asistencias_th as a')
    //             // Empleados Proyelco
    //             ->leftJoin('empleados_proyelco_th as ep', function ($join) {
    //                 $join->on('a.empleado_id', '=', 'ep.id')
    //                     ->where('a.tipo_empleado', 1);
    //             })
    //             // Empleados No Proyelco
    //             ->leftJoin('empleados_th as enp', function ($join) {
    //                 $join->on('a.empleado_id', '=', 'enp.id')
    //                     ->where('a.tipo_empleado', 2);
    //             })
    //             // Contratista (desde ficha_th)
    //             ->leftJoin('ficha_th as f', function ($join) {
    //                 $join->on('a.identificacion', '=', 'f.identificacion');
    //             })
    //             ->leftJoin('contratistas_th as cont', 'f.contratista_id', '=', 'cont.id')
    //             // Proyectos
    //             ->leftJoin('proyecto as p', function ($join) {
    //                 $join->on('a.obra_id', '=', 'p.id')
    //                     ->where('a.tipo_obra', 1);
    //             })
    //             ->leftJoin('proyectos_casas as pc', function ($join) {
    //                 $join->on('a.obra_id', '=', 'pc.id')
    //                     ->where('a.tipo_obra', 2);
    //             })
    //             // Cargo (unificado)
    //             ->leftJoin('cargos_th as c', function ($join) {
    //                 $join->on('ep.cargo_id', '=', 'c.id')
    //                     ->orOn('enp.cargo_id', '=', 'c.id');
    //             })

    //             // SELECT
    //             ->select(
    //                 'a.id as asistencia_id',
    //                 'a.fecha_ingreso',
    //                 'a.hora_ingreso',
    //                 'a.fecha_salida',
    //                 'a.hora_salida',
    //                 'a.horas_laborales',
    //                 DB::raw("
    //                     CASE 
    //                         WHEN a.tipo_obra = 1 THEN p.nombre 
    //                         WHEN a.tipo_obra = 2 THEN pc.nombre 
    //                         ELSE 'N/A' 
    //                     END as obra
    //                 "),
    //                 DB::raw("
    //                     CASE 
    //                         WHEN a.tipo_empleado = 1 THEN ep.nombre_completo
    //                         WHEN a.tipo_empleado = 2 THEN enp.nombre_completo
    //                         ELSE 'Desconocido'
    //                     END as nombre_empleado
    //                 "),
    //                 DB::raw("
    //                     CASE 
    //                         WHEN a.tipo_empleado = 1 THEN ep.identificacion
    //                         WHEN a.tipo_empleado = 2 THEN enp.identificacion
    //                         ELSE 'N/A'
    //                     END as identificacion
    //                 "),
    //                 DB::raw("
    //                     CASE 
    //                         WHEN a.tipo_empleado = 1 THEN ep.telefono_celular
    //                         WHEN a.tipo_empleado = 2 THEN enp.telefono_celular
    //                         ELSE 'N/A'
    //                     END as telefono
    //                 "),
    //                 'c.cargo',
    //                 'cont.contratista as nombre_contratista',
    //                 DB::raw("
    //                     CASE 
    //                         WHEN a.tipo_empleado = 1 THEN 'Empleado Proyelco'
    //                         WHEN a.tipo_empleado = 2 THEN 'Empleado No Proyelco'
    //                         ELSE 'Desconocido'
    //                     END as tipo_empleado_texto
    //                 "),
    //                 DB::raw("
    //                     CASE 
    //                         WHEN a.tipo_obra = 1 THEN 'Apartamentos'
    //                         WHEN a.tipo_obra = 2 THEN 'Casas'
    //                         ELSE 'Desconocido'
    //                     END as tipo_obra_texto
    //                 ")
    //             )

    //             // FILTROS OPCIONALES
    //             ->when($request->filled('fecha_inicio') && $request->filled('fecha_fin'), function ($q) use ($request) {
    //                 $q->whereBetween('a.fecha_ingreso', [$request->fecha_inicio, $request->fecha_fin]);
    //             })
    //             ->when($request->filled('obra_id'), function ($q) use ($request) {
    //                 $q->where('a.obra_id', $request->obra_id);
    //             })
    //             ->when($request->filled('tipo_empleado'), function ($q) use ($request) {
    //                 $q->where('a.tipo_empleado', $request->tipo_empleado);
    //             })
    //             ->when($request->filled('tipo_obra'), function ($q) use ($request) {
    //                 $q->where('a.tipo_obra', $request->tipo_obra);
    //             })

    //             ->orderBy('a.fecha_ingreso', 'desc')
    //             ->orderBy('a.hora_ingreso', 'desc')
    //             ->get();

    //         return response()->json([
    //             'status' => 'success',
    //             'total' => $reporte->count(),
    //             'data' => $reporte
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Error al generar el reporte: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function reporteAsistencia(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio'
        ]);

        $fechaInicio = $request->fecha_inicio;
        $fechaFin = $request->fecha_fin;

        // Validar que no sea más de 4 meses
        $start = Carbon::parse($fechaInicio);
        $end = Carbon::parse($fechaFin);

        if ($start->diffInMonths($end) > 4) {
            return response()->json([
                'status' => 'error',
                'message' => 'El rango de fechas no puede ser mayor a 4 meses'
            ], 400);
        }


        $asistencias = DB::connection('mysql')
            ->table('asistencias_th')
            //empleado
            ->leftJoin('empleados_proyelco_th as ep', function ($join) {
                $join->on('asistencias_th.empleado_id', '=', 'ep.id')
                    ->where('asistencias_th.tipo_empleado', 1);
            })
            ->leftJoin('empleados_th as et', function ($join) {
                $join->on('asistencias_th.empleado_id', '=', 'et.id')
                    ->where('asistencias_th.tipo_empleado', 2);
            })
            //proyecto
            ->leftJoin('proyecto', function ($join) {
                $join->on('asistencias_th.obra_id', '=', 'proyecto.id')
                    ->where('asistencias_th.tipo_obra', 1);
            })
            ->leftJoin('proyectos_casas', function ($join) {
                $join->on('asistencias_th.obra_id', '=', 'proyectos_casas.id')
                    ->where('asistencias_th.tipo_obra', 2);
            })
            //cargo
            ->leftJoin('cargos_th as c', function ($join) {
                $join->on('ep.cargo_id', '=', 'c.id')
                    ->orOn('et.cargo_id', '=', 'c.id');
            })
            //contratista
            ->leftJoin('ficha_th as f', 'asistencias_th.identificacion', '=', 'f.identificacion')
            ->leftJoin('contratistas_th as cont', 'f.contratista_id', '=', 'cont.id')
            ->select(
                // Datos básicos de asistencias_th
                'asistencias_th.id',
                'asistencias_th.fecha_ingreso',
                'asistencias_th.hora_ingreso',
                'asistencias_th.fecha_salida',
                'asistencias_th.hora_salida',
                'asistencias_th.horas_laborales',
                'asistencias_th.tipo_obra',
                'asistencias_th.tipo_empleado',

                // Datos del empleado
                DB::raw("
                CASE 
                    WHEN asistencias_th.tipo_empleado = 1 THEN ep.nombre_completo
                    WHEN asistencias_th.tipo_empleado = 2 THEN et.nombre_completo
                END as nombre_completo
            "),
                DB::raw("
                CASE 
                    WHEN asistencias_th.tipo_empleado = 1 THEN ep.identificacion
                    WHEN asistencias_th.tipo_empleado = 2 THEN et.identificacion
                END as identificacion
            "),
                DB::raw("
                CASE 
                    WHEN asistencias_th.tipo_empleado = 1 THEN ep.tipo_documento
                    WHEN asistencias_th.tipo_empleado = 2 THEN et.tipo_documento
                END as tipo_documento
            "),
                DB::raw("
                CASE 
                    WHEN asistencias_th.tipo_empleado = 1 THEN ep.telefono_celular
                    WHEN asistencias_th.tipo_empleado = 2 THEN et.telefono_celular
                END as telefono_celular
            "),

                // Información de la obra
                DB::raw("
                CASE 
                    WHEN asistencias_th.tipo_obra = 1 THEN proyecto.descripcion_proyecto
                    WHEN asistencias_th.tipo_obra = 2 THEN proyectos_casas.descripcion_proyecto
                    ELSE 'Sin obra asignada'
                END as nombre_obra
            "),

                // Información del contratista
                'cont.contratista as nombre_contratista',
                'cont.nit as nit_contratista',

                // Cargo del empleado
                'c.cargo',

                // Tipo de empleado como texto
                DB::raw("
                CASE 
                    WHEN asistencias_th.tipo_empleado = 1 THEN 'Empleado Proyelco'
                    WHEN asistencias_th.tipo_empleado = 2 THEN 'Empleado No Proyelco'
                    ELSE 'Desconocido'
                END as tipo_empleado_texto
            "),

                // Tipo de obra como texto
                DB::raw("
                CASE 
                    WHEN asistencias_th.tipo_obra = 1 THEN 'Apartamentos'
                    WHEN asistencias_th.tipo_obra = 2 THEN 'Casas'
                    ELSE 'Desconocido'
                END as tipo_obra_texto
            ")
            )
            ->whereBetween('asistencias_th.fecha_ingreso', [$fechaInicio, $fechaFin])
            ->orderBy('asistencias_th.fecha_ingreso', 'desc')
            ->orderBy('asistencias_th.hora_ingreso', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $asistencias
        ]);
    }
}
