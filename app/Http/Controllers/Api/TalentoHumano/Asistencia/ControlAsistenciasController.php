<?php

namespace App\Http\Controllers\Api\TalentoHumano\Asistencia;

use App\Http\Controllers\Controller;
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
                    'empleado_id',
                    'identificacion'
                )
                ->where('identificacion', $request->cedula)
                ->where('estado', 1)
                ->first(); // Usar first() en lugar de get() para obtener un solo registro


            // Log::info((array) $empleado);

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



            //ver que no este en otra obra


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
                    ->select('nombre_completo') // Ajusta según tus campos
                    ->where('identificacion', $request->cedula)
                    ->first();
            } else {
                $empleadoInfo = DB::connection('mysql')
                    ->table('empleados_th')
                    ->select('nombre_completo') // Ajusta según tus campos
                    ->where('identificacion', $request->cedula)
                    ->first();
            }

            // Construir la respuesta
            $response = [
                'status' => 'success',
                'data' => [
                    'empleado' => [
                        'identificacion' => $empleado->identificacion,
                        'nombre' => $empleadoInfo->nombre_completo ?? 'No disponible',
                        // 'apellido' => $empleadoInfo->apellido ?? 'No disponible',
                        // 'foto_url' => $empleadoInfo->foto_url ?? null,
                        'tipo_empleado' => $empleado->tipo_empleado,
                        'empleado_id' => $empleado->empleado_id,
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
}
