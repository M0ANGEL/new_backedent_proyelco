<?php

namespace App\Http\Controllers\Api\TalentoHumano\Asistencia;

use App\Http\Controllers\Controller;
use App\Models\EmpleadoProyelco;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Exports\ReporteCompletoConCalculoHorasExport;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;

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

            //vamoa escluir usuarios con perfil de ingenieros
            //buscamos usuairo 
            // Buscar si el empleado tiene usuario en la tabla users
            $usuario = User::where('cedula', $empleado->identificacion)->first();

            // Si el usuario NO existe o su rol NO es "INGENIERO OBRA", se valida si está registrado en otra obra
            if (!$usuario || ($usuario->rol !== "Ingeniero Obra" && $usuario->rol !== "sst")) {
                if ($ultimaAsistencia) {
                    if ($ultimaAsistencia->fecha_ingreso && !$ultimaAsistencia->fecha_salida) {

                        if ($ultimaAsistencia->obra_id !== $request->obra_id) {

                            $proyecto = DB::connection('mysql')
                                ->table('bodegas_area')
                                ->select('nombre')
                                ->where('id', $ultimaAsistencia->obra_id)
                                ->first();

                            return response()->json([
                                'status' => 'error',
                                'message' => 'El usuario se encuentra registrado en otra obra: ' . '[' . $proyecto->nombre . ']' . '. Comunícate con el encargado de la obra y registra la salida.'
                            ], 404);
                        }
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

                        break; // Salir del loop cuando encuentre una foto
                    }
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

            // Log::info((array) $empleado);



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

                // Log::info((array) $entradaPendiente);


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
                    'message' => "Salida registrada correctamente.",
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

        $totalPersonalProyelco = EmpleadoProyelco::where('estado', 1)->count();

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
            //proyecto - AHORA SOLO CON bodegas_area
            ->leftJoin('bodegas_area as ba', 'asistencias_th.obra_id', '=', 'ba.id')
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

                // Información de la obra - AHORA SOLO DESDE bodegas_area
                'ba.nombre as nombre_obra',

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
        ")
            )
            ->whereBetween('asistencias_th.fecha_ingreso', [$fechaInicio, $fechaFin])
            ->orderBy('asistencias_th.fecha_ingreso', 'desc')
            ->orderBy('asistencias_th.hora_ingreso', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $asistencias,
            'totalPersonalProyelco' => $totalPersonalProyelco
        ]);
    }

    public function exportReporteCompletoAsistenciasTH(Request $request)
    {
        try {
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
                throw new \Exception('El rango de fechas no puede ser mayor a 4 meses');
            }

            // ===== PARTE 1: ASISTENCIAS REGISTRADAS =====
            $asistenciasRegistradas = DB::connection('mysql')
                ->table('asistencias_th')
                ->leftJoin('empleados_proyelco_th as ep', function ($join) {
                    $join->on('asistencias_th.empleado_id', '=', 'ep.id')
                        ->where('asistencias_th.tipo_empleado', 1);
                })
                ->leftJoin('empleados_th as et', function ($join) {
                    $join->on('asistencias_th.empleado_id', '=', 'et.id')
                        ->where('asistencias_th.tipo_empleado', 2);
                })
                ->leftJoin('bodegas_area as ba', 'asistencias_th.obra_id', '=', 'ba.id')
                ->leftJoin('cargos_th as c', function ($join) {
                    $join->on('ep.cargo_id', '=', 'c.id')
                        ->orOn('et.cargo_id', '=', 'c.id');
                })
                ->leftJoin('ficha_th as f', 'asistencias_th.identificacion', '=', 'f.identificacion')
                ->leftJoin('contratistas_th as cont', 'f.contratista_id', '=', 'cont.id')
                ->select(
                    'asistencias_th.id',
                    'asistencias_th.fecha_ingreso',
                    'asistencias_th.hora_ingreso',
                    'asistencias_th.fecha_salida',
                    'asistencias_th.hora_salida',
                    'asistencias_th.horas_laborales',
                    'asistencias_th.tipo_empleado',
                    'asistencias_th.empleado_id',

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
                    'ba.nombre as nombre_obra',
                    'cont.contratista as nombre_contratista',
                    'c.cargo',
                    DB::raw("
            CASE 
                WHEN asistencias_th.tipo_empleado = 1 THEN 'Empleado Proyelco'
                WHEN asistencias_th.tipo_empleado = 2 THEN 'Empleado No Proyelco'
                ELSE 'Desconocido'
            END as tipo_empleado_texto
        ")
                )
                ->whereBetween('asistencias_th.fecha_ingreso', [$fechaInicio, $fechaFin])
                ->orderBy('asistencias_th.fecha_ingreso', 'desc')
                ->orderBy('asistencias_th.hora_ingreso', 'desc')
                ->get();

            // ===== CALCULAR HORAS POR EMPLEADO PARA NUEVA HOJA =====
            $horasCalculadasData = [];
            $empleadosProcesados = [];

            foreach ($asistenciasRegistradas as $asistencia) {
                $key = $asistencia->empleado_id . '_' . $asistencia->identificacion;

                // Solo procesar cada empleado una vez
                if (in_array($key, $empleadosProcesados)) {
                    continue;
                }

                $empleadosProcesados[] = $key;

                // Obtener todas las asistencias de este empleado en el rango de fechas
                $asistenciasEmpleado = DB::connection('mysql')
                    ->table('asistencias_th')
                    ->where('empleado_id', $asistencia->empleado_id)
                    ->where('tipo_empleado', $asistencia->tipo_empleado)
                    ->whereBetween('fecha_ingreso', [$fechaInicio, $fechaFin])
                    ->orderBy('fecha_ingreso', 'asc')
                    ->orderBy('hora_ingreso', 'asc')
                    ->get();

                // Agrupar asistencias por día
                $asistenciasPorDia = [];
                foreach ($asistenciasEmpleado as $registro) {
                    $fecha = $registro->fecha_ingreso;
                    if (!isset($asistenciasPorDia[$fecha])) {
                        $asistenciasPorDia[$fecha] = [];
                    }
                    $asistenciasPorDia[$fecha][] = $registro;
                }

                // Calcular horas por cada día
                $totalHorasCalculadas = 0;
                $totalHorasNormales = 0;
                $totalHorasExtras = 0;
                $totalHorasNocturnas = 0;
                $totalHorasExtrasNocturnas = 0;
                $tieneSalidaCompleta = true;
                $primerIngresoGlobal = null;
                $ultimaSalidaGlobal = null;

                foreach ($asistenciasPorDia as $fecha => $registrosDia) {
                    // Encontrar primera entrada y última salida del día
                    $primerIngresoDia = null;
                    $ultimaSalidaDia = null;

                    foreach ($registrosDia as $registro) {
                        if (!$primerIngresoDia) {
                            $primerIngresoDia = [
                                'fecha' => $registro->fecha_ingreso,
                                'hora' => $registro->hora_ingreso
                            ];
                            if (!$primerIngresoGlobal) {
                                $primerIngresoGlobal = $primerIngresoDia;
                            }
                        }

                        if ($registro->hora_salida) {
                            $ultimaSalidaDia = [
                                'fecha' => $registro->fecha_salida,
                                'hora' => $registro->hora_salida
                            ];
                            $ultimaSalidaGlobal = $ultimaSalidaDia;
                        } else {
                            $tieneSalidaCompleta = false;
                        }
                    }

                    // Calcular horas para este día si tiene salida completa
                    if ($primerIngresoDia && $ultimaSalidaDia) {
                        $horaIngreso = Carbon::parse($primerIngresoDia['fecha'] . ' ' . $primerIngresoDia['hora']);
                        $horaSalida = Carbon::parse($ultimaSalidaDia['fecha'] . ' ' . $ultimaSalidaDia['hora']);

                        // Verificar si es salida especial (23:59:59)
                        if ($ultimaSalidaDia['hora'] == '23:59:59') {
                            // Tratar como salida normal sin horas extras
                            $diferenciaMinutos = $horaIngreso->diffInMinutes($horaSalida);

                            // Restar 1 hora (60 minutos) para almuerzo
                            $diferenciaMinutos -= 60;

                            if ($diferenciaMinutos > 0) {
                                $totalHorasCalculadas += $diferenciaMinutos;
                                $totalHorasNormales += $diferenciaMinutos;
                            }
                        } else {
                            // Calcular horas normales, extras y nocturnas para este día
                            list($horasTotales, $horasNormales, $horasExtras, $horasNocturnas, $horasExtrasNocturnas) =
                                $this->calcularHorasDetalladas($horaIngreso, $horaSalida, $fecha);

                            // Sumar al total
                            $totalHorasCalculadas += $this->horasAMinutos($horasTotales);
                            $totalHorasNormales += $this->horasAMinutos($horasNormales);
                            $totalHorasExtras += $this->horasAMinutos($horasExtras);
                            $totalHorasNocturnas += $this->horasAMinutos($horasNocturnas);
                            $totalHorasExtrasNocturnas += $this->horasAMinutos($horasExtrasNocturnas);
                        }
                    }
                }

                // Convertir minutos totales a formato HH:MM
                $formatearHoras = function ($minutos) {
                    if ($minutos <= 0) return '00:00';
                    $horas = floor($minutos / 60);
                    $minutosRestantes = $minutos % 60;
                    return sprintf("%02d:%02d", $horas, $minutosRestantes);
                };

                $horasCalculadasData[] = [
                    'N°' => count($horasCalculadasData) + 1,
                    'Nombre Completo' => $asistencia->nombre_completo,
                    'Identificación' => $asistencia->identificacion,
                    'Tipo Documento' => $asistencia->tipo_documento,
                    'Cargo' => $asistencia->cargo,
                    'Ubicación' => $asistencia->nombre_obra ?: 'Sin obra asignada',
                    'Contratista' => $asistencia->nombre_contratista ?: 'No asignado',
                    'Primera Entrada' => $primerIngresoGlobal ? Carbon::parse($primerIngresoGlobal['fecha'])->format('d-m-Y') . ' ' . $primerIngresoGlobal['hora'] : 'N/A',
                    'Última Salida' => $ultimaSalidaGlobal ? Carbon::parse($ultimaSalidaGlobal['fecha'])->format('d-m-Y') . ' ' . $ultimaSalidaGlobal['hora'] : 'N/A',
                    'Horas Calculadas' => $formatearHoras($totalHorasCalculadas),
                    'Horas Normales' => $formatearHoras($totalHorasNormales),
                    'Horas Extras' => $formatearHoras($totalHorasExtras),
                    'Horas Nocturnas' => $formatearHoras($totalHorasNocturnas),
                    'Horas Extras Nocturnas' => $formatearHoras($totalHorasExtrasNocturnas),
                    'Estado' => $tieneSalidaCompleta ? 'COMPLETADO' : 'EN CURSO'
                ];
            }

            // ===== PARTE 2: EMPLEADOS SIN ASISTENCIA (SOLO PROYELCO) =====
            $empleadosProyelco = DB::connection('mysql')
                ->table('empleados_proyelco_th')
                ->where('estado', 1)
                ->select(
                    'id',
                    'nombre_completo',
                    'identificacion',
                    'tipo_documento',
                    'telefono_celular',
                    'cargo_id'
                )
                ->get();

            // Obtener IDs de empleados que SÍ tienen asistencia en el rango
            $empleadosConAsistencia = DB::connection('mysql')
                ->table('asistencias_th')
                ->whereBetween('fecha_ingreso', [$fechaInicio, $fechaFin])
                ->where('tipo_empleado', 1)
                ->pluck('empleado_id')
                ->toArray();

            // Filtrar empleados que NO tienen asistencia
            $empleadosSinAsistencia = $empleadosProyelco->filter(function ($empleado) use ($empleadosConAsistencia) {
                return !in_array($empleado->id, $empleadosConAsistencia);
            });

            // Preparar datos para Excel - HOJA 1: ASISTENCIAS REGISTRADAS (MANTENER ORIGINAL)
            $excelDataAsistencias = [];
            foreach ($asistenciasRegistradas as $index => $asistencia) {
                $estado = ($asistencia->hora_salida && $asistencia->fecha_salida) ? 'COMPLETADA' : 'EN CURSO';

                $excelDataAsistencias[] = [
                    'N°' => $index + 1,
                    'Tipo' => 'ASISTENCIA REGISTRADA',
                    'Estado' => $estado,
                    'Fecha Ingreso' => Carbon::parse($asistencia->fecha_ingreso)->format('d-m-Y'),
                    'Hora Ingreso' => $asistencia->hora_ingreso,
                    'Fecha Salida' => $asistencia->fecha_salida ? Carbon::parse($asistencia->fecha_salida)->format('d-m-Y') : 'En curso',
                    'Hora Salida' => $asistencia->hora_salida ?: 'En curso',
                    'Horas Laboradas' => $asistencia->horas_laborales ?: 'No calculada',
                    'Nombre Completo' => $asistencia->nombre_completo,
                    'Identificación' => $asistencia->identificacion,
                    'Tipo Documento' => $asistencia->tipo_documento,
                    'Teléfono' => $asistencia->telefono_celular,
                    'Cargo' => $asistencia->cargo,
                    'Ubicación' => $asistencia->nombre_obra ?: 'Sin obra asignada',
                    'Contratista' => $asistencia->nombre_contratista ?: 'No asignado',
                    'Tipo Empleado' => $asistencia->tipo_empleado_texto,
                ];
            }

            // Preparar datos para Excel - HOJA 2: SIN ASISTENCIA (MANTENER ORIGINAL)
            $excelDataSinAsistencia = [];
            foreach ($empleadosSinAsistencia as $index => $empleado) {
                $cargo = DB::connection('mysql')
                    ->table('cargos_th')
                    ->where('id', $empleado->cargo_id)
                    ->value('cargo');

                $excelDataSinAsistencia[] = [
                    'N°' => $index + 1,
                    'Tipo' => 'SIN ASISTENCIA',
                    'Estado' => 'SIN REGISTRO',
                    'Fecha Ingreso' => 'N/A',
                    'Hora Ingreso' => 'N/A',
                    'Fecha Salida' => 'N/A',
                    'Hora Salida' => 'N/A',
                    'Horas Laboradas' => 'N/A',
                    'Nombre Completo' => $empleado->nombre_completo,
                    'Identificación' => $empleado->identificacion,
                    'Tipo Documento' => $empleado->tipo_documento,
                    'Teléfono' => $empleado->telefono_celular,
                    'Cargo' => $cargo ?: 'No asignado',
                    'Ubicación' => 'Sin Ubicación',
                    'Contratista' => 'Proyelco S.A.S',
                    'Tipo Empleado' => 'Empleado Proyelco',
                ];
            }

            // Combinar ambos conjuntos de datos para la primera hoja
            $excelDataCompleto = array_merge($excelDataAsistencias, $excelDataSinAsistencia);

            $fileName = 'reporte_completo_asistencias_' . Carbon::now()->format('Y_m_d_His') . '.xlsx';

            if (ob_get_length()) {
                ob_end_clean();
            }

            // Devolver múltiples hojas
            return Excel::download(new ReporteCompletoConCalculoHorasExport(
                $excelDataCompleto,
                $horasCalculadasData
            ), $fileName);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    // NUEVA FUNCIÓN PARA CALCULAR HORAS DETALLADAS (MEJORADA)
    private function calcularHorasDetalladas($horaIngreso, $horaSalida, $fecha)
    {
        $diferenciaMinutos = $horaIngreso->diffInMinutes($horaSalida);

        // Restar 1 hora (60 minutos) para almuerzo solo si la jornada es mayor a 4 horas
        if ($diferenciaMinutos > 240) { // 4 horas = 240 minutos
            $diferenciaMinutos -= 60;
        }

        if ($diferenciaMinutos <= 0) {
            return ['00:00', '00:00', '00:00', '00:00', '00:00'];
        }

        // Determinar horario laboral según el día de la semana
        $diaSemana = Carbon::parse($fecha)->dayOfWeek;
        $esViernes = ($diaSemana == 5); // 5 = viernes

        // Horarios laborales
        $horaInicioLaboral = Carbon::parse($fecha . ' 07:00:00');
        $horaFinLaboral = $esViernes ?
            Carbon::parse($fecha . ' 16:00:00') :
            Carbon::parse($fecha . ' 17:00:00');
        $horaInicioNocturno = Carbon::parse($fecha . ' 19:00:00'); // 7 PM

        $minutosNormales = 0;
        $minutosExtras = 0;
        $minutosNocturnos = 0;
        $minutosExtrasNocturnos = 0;

        // Calcular por cada minuto
        $minutoActual = $horaIngreso->copy();

        while ($minutoActual < $horaSalida) {
            $minutoSiguiente = $minutoActual->copy()->addMinute();

            // Verificar si es hora nocturna (después de las 7 PM o antes de las 6 AM)
            $esNocturna = ($minutoActual->hour >= 19 || $minutoActual->hour < 6);

            // Verificar si es hora extra (fuera del horario laboral)
            $horaEnHorarioLaboral = ($minutoActual >= $horaInicioLaboral && $minutoActual < $horaFinLaboral);

            if ($horaEnHorarioLaboral) {
                // Dentro del horario laboral
                if ($esNocturna) {
                    $minutosExtrasNocturnos += 1;
                } else {
                    $minutosNormales += 1;
                }
            } else {
                // Fuera del horario laboral (horas extras)
                if ($esNocturna) {
                    $minutosExtrasNocturnos += 1;
                } else {
                    $minutosExtras += 1;
                }
            }

            $minutoActual = $minutoSiguiente;
        }

        // Convertir minutos a formato HH:MM
        $formatearHoras = function ($minutos) {
            if ($minutos <= 0) return '00:00';
            $horas = floor($minutos / 60);
            $minutosRestantes = $minutos % 60;
            return sprintf("%02d:%02d", $horas, $minutosRestantes);
        };

        $horasTotales = $formatearHoras($diferenciaMinutos);
        $horasNormales = $formatearHoras($minutosNormales);
        $horasExtras = $formatearHoras($minutosExtras);
        $horasNocturnas = $formatearHoras($minutosNocturnos);
        $horasExtrasNocturnas = $formatearHoras($minutosExtrasNocturnos);

        return [$horasTotales, $horasNormales, $horasExtras, $horasNocturnas, $horasExtrasNocturnas];
    }

    // NUEVA FUNCIÓN AUXILIAR PARA CONVERTIR HH:MM A MINUTOS
    private function horasAMinutos($horasFormato)
    {
        if ($horasFormato === '00:00' || $horasFormato === 'Sin calcular') {
            return 0;
        }

        list($horas, $minutos) = explode(':', $horasFormato);
        return (int)$horas * 60 + (int)$minutos;
    }
}
