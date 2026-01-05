<?php

namespace App\Http\Controllers\Api\LectorRFID;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LectorRFIDController extends Controller
{

    // public function registrarMarcacionRFID(Request $request)
    // {
    //     // Validar los datos requeridos
    //     $validator = Validator::make($request->all(), [
    //         'rfid' => 'required|string',
    //         'location' => 'nullable|string',  // Ubicación del lector
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Datos inválidos',
    //             'errors' => $validator->errors()
    //         ], 400);
    //     }

    //     try {
    //         DB::beginTransaction();

    //         // Buscar el empleado
    //         $empleado = DB::connection('mysql')
    //             ->table('ficha_th')
    //             ->where('rfid', $request->rfid)
    //             ->where('estado', 1)
    //             ->first();

    //         if (!$empleado) {
    //             Log::warning('RFID no encontrado o empleado inactivo', ['rfid' => $request->rfid]);
                
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Tarjeta no registrada o empleado inactivo'
    //             ], 404);
    //         }

    //         $fechaActual = now()->format('Y-m-d');
    //         $horaActual = now();
    //         $usuario = Auth::id() ?? 1; // Si no hay auth, usar sistema

    //         // VERIFICAR: ¿Tiene entrada sin salida hoy?
    //         $ultimoRegistro = DB::connection('mysql')
    //             ->table('asistencias_th')
    //             ->where('tipo_empleado', $empleado->tipo_empleado)
    //             ->where('empleado_id', $empleado->empleado_id)
    //             ->whereDate('fecha_ingreso', $fechaActual)
    //             ->orderBy('fecha_ingreso', 'desc')
    //             ->orderBy('hora_ingreso', 'desc')
    //             ->first();

    //         $esEntrada = true; // Por defecto es entrada
            
    //         if ($ultimoRegistro && is_null($ultimoRegistro->fecha_salida)) {
    //             // Tiene entrada sin salida → ES SALIDA
    //             $esEntrada = false;
                
    //             // Calcular horas laborales
    //             $horaEntrada = Carbon::createFromFormat(
    //                 'Y-m-d H:i:s',
    //                 $ultimoRegistro->fecha_ingreso . ' ' . $ultimoRegistro->hora_ingreso
    //             );

    //             $horaSalida = $horaActual;

    //             // Calcular diferencia en segundos
    //             $diferenciaSegundos = $horaSalida->diffInSeconds($horaEntrada);

    //             // Convertir segundos a formato time (HH:MM:SS)
    //             $horas = floor($diferenciaSegundos / 3600);
    //             $minutos = floor(($diferenciaSegundos % 3600) / 60);
    //             $segundos = $diferenciaSegundos % 60;

    //             $horasLaboralesTime = sprintf('%02d:%02d:%02d', $horas, $minutos, $segundos);

    //             // También calcular horas decimales
    //             $horasDecimales = round($diferenciaSegundos / 3600, 2);

    //             // Actualizar registro existente con salida
    //             DB::connection('mysql')
    //                 ->table('asistencias_th')
    //                 ->where('id', $ultimoRegistro->id)
    //                 ->update([
    //                     'fecha_salida' => $fechaActual,
    //                     'hora_salida' => $horaActual->format('H:i:s'),
    //                     'horas_laborales' => $horasLaboralesTime,
    //                     'updated_at' => $horaActual,
    //                 ]);
                
    //         } else {
    //             // NO tiene entrada hoy o ya tiene salida → ES ENTRADA
                
    //             // Validar si ya tiene 2 registros hoy (entrada/salida completos)
    //             $registrosHoy = DB::connection('mysql')
    //                 ->table('asistencias_th')
    //                 ->where('tipo_empleado', $empleado->tipo_empleado)
    //                 ->where('empleado_id', $empleado->empleado_id)
    //                 ->whereDate('fecha_ingreso', $fechaActual)
    //                 ->count();

    //             // Opcional: Limitar a 2 registros por día (entrada/salida)
    //             if ($registrosHoy >= 2) {
    //                 return response()->json([
    //                     'status' => 'error',
    //                     'message' => 'Ya completaste tu jornada de hoy'
    //                 ], 400);
    //             }

    //             // Registrar nueva entrada
    //             $asistenciaId = DB::connection('mysql')
    //                 ->table('asistencias_th')
    //                 ->insertGetId([
    //                     'user_id' => $usuario,
    //                     'tipo_empleado' => $empleado->tipo_empleado,
    //                     'empleado_id' => $empleado->empleado_id,
    //                     'identificacion' => $empleado->identificacion,
    //                     'fecha_ingreso' => $fechaActual,
    //                     'hora_ingreso' => $horaActual->format('H:i:s'),
    //                     'obra_id' => 1,
    //                     'created_at' => $horaActual,
    //                     'updated_at' => $horaActual,
    //                 ]);

    //             $mensaje = "✅ Entrada registrada correctamente";
    //         }

    //         // Registrar log de la operación RFID
    //         // DB::connection('mysql')
    //         //     ->table('rfid_logs') // Crea esta tabla si no existe
    //         //     ->insert([
    //         //         'rfid_code' => $request->rfid,
    //         //         'empleado_id' => $empleado->empleado_id,
    //         //         'tipo_empleado' => $empleado->tipo_empleado,
    //         //         'nombre_completo' => $empleado->nombre ?? 'N/A',
    //         //         'tipo_marcacion' => $esEntrada ? 'entrada' : 'salida',
    //         //         'ip_address' => $request->ip(),
    //         //         'user_agent' => $request->userAgent(),
    //         //         'created_at' => $horaActual,
    //         //     ]);

    //         DB::commit();

    //         // Preparar respuesta
    //         $respuesta = [
    //             'status' => 'success',
    //             'message' => 'full' ,
    //         ];

    //         return response()->json($respuesta);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
            
    //         Log::error('Error al procesar RFID:', [
    //             'rfid' => $request->rfid,
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Error en el sistema: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function registrarMarcacionRFID(Request $request)
{
    // Validar los datos requeridos
    $validator = Validator::make($request->all(), [
        'rfid' => 'required|string',
        'location' => 'nullable|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'message' => 'Datos inválidos',
            'errors' => $validator->errors()
        ], 400);
    }

    try {
        DB::beginTransaction();

        // Buscar el empleado
        $empleado = DB::connection('mysql')
            ->table('ficha_th')
            ->where('rfid', $request->rfid)
            ->where('estado', 1)
            ->first();

        if (!$empleado) {
            Log::warning('RFID no encontrado o empleado inactivo', ['rfid' => $request->rfid]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Tarjeta no registrada o empleado inactivo'
            ], 404);
        }

        $fechaActual = now()->format('Y-m-d');
        $horaActual = now();
        $horaActualFormatted = $horaActual->format('H:i:s');
        $usuario = Auth::id() ?? 1;

        // Obtener el último registro del día (no importa si tiene salida o no)
        $ultimoRegistro = DB::connection('mysql')
            ->table('asistencias_th')
            ->where('tipo_empleado', $empleado->tipo_empleado)
            ->where('empleado_id', $empleado->empleado_id)
            ->whereDate('fecha_ingreso', $fechaActual)
            ->orderBy('hora_ingreso', 'desc')
            ->first();

        $esEntrada = true; // Por defecto es entrada
        
        // LÓGICA PRINCIPAL: Determinar si es entrada o salida
        if ($ultimoRegistro) {
            // Si el último registro tiene fecha_salida NULL → ES SALIDA
            // Si el último registro tiene fecha_salida NOT NULL → ES ENTRADA
            $esEntrada = !is_null($ultimoRegistro->fecha_salida);
            
            // PREVENIR DUPLICADOS: Verificar que no sea la misma hora para el mismo tipo
            if (!$esEntrada) {
                // Si es salida y la última entrada fue hace menos de 1 minuto, prevenir duplicado
                $ultimaEntradaHora = Carbon::createFromFormat('H:i:s', $ultimoRegistro->hora_ingreso);
                if ($horaActual->diffInSeconds($ultimaEntradaHora) < 60) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Registro duplicado. Espere unos segundos.'
                    ], 400);
                }
            }
        }

        if ($esEntrada) {
            // REGISTRAR NUEVA ENTRADA
            $asistenciaId = DB::connection('mysql')
                ->table('asistencias_th')
                ->insertGetId([
                    'user_id' => $usuario,
                    'tipo_empleado' => $empleado->tipo_empleado,
                    'empleado_id' => $empleado->empleado_id,
                    'identificacion' => $empleado->identificacion,
                    'fecha_ingreso' => $fechaActual,
                    'hora_ingreso' => $horaActualFormatted,
                    'fecha_salida' => null,
                    'hora_salida' => null,
                    'obra_id' => 1,
                    'created_at' => $horaActual,
                    'updated_at' => $horaActual,
                ]);
            
            $mensaje = "✅ Entrada registrada";
            $tipoMarca = "entrada";
            
        } else {
            // REGISTRAR SALIDA en el último registro abierto
            if (!$ultimoRegistro) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'No hay entrada pendiente para cerrar'
                ], 400);
            }
            
            // Calcular horas laborales
            $horaEntrada = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $ultimoRegistro->fecha_ingreso . ' ' . $ultimoRegistro->hora_ingreso
            );
            
            // Verificar que no sea salida antes de la entrada (error lógico)
            if ($horaActual->lt($horaEntrada)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'La hora de salida no puede ser anterior a la entrada'
                ], 400);
            }
            
            $diferenciaSegundos = $horaActual->diffInSeconds($horaEntrada);
            
            // Convertir a formato time (HH:MM:SS)
            $horas = floor($diferenciaSegundos / 3600);
            $minutos = floor(($diferenciaSegundos % 3600) / 60);
            $segundos = $diferenciaSegundos % 60;
            $horasLaboralesTime = sprintf('%02d:%02d:%02d', $horas, $minutos, $segundos);
            
            // Actualizar registro con salida
            DB::connection('mysql')
                ->table('asistencias_th')
                ->where('id', $ultimoRegistro->id)
                ->update([
                    'fecha_salida' => $fechaActual,
                    'hora_salida' => $horaActualFormatted,
                    'horas_laborales' => $horasLaboralesTime,
                    'updated_at' => $horaActual,
                ]);
            
            $mensaje = "✅ Salida registrada";
            $tipoMarca = "salida";
        }

        // Opcional: Registrar en logs
        // DB::connection('mysql')
        //     ->table('rfid_logs')
        //     ->insert([
        //         'rfid_code' => $request->rfid,
        //         'empleado_id' => $empleado->empleado_id,
        //         'tipo_empleado' => $empleado->tipo_empleado,
        //         'nombre_completo' => $empleado->nombre ?? 'N/A',
        //         'tipo_marcacion' => $tipoMarca,
        //         'estado' => $esEntrada ? 'ABIERTO' : 'CERRADO',
        //         'ip_address' => $request->ip(),
        //         'user_agent' => $request->userAgent(),
        //         'created_at' => $horaActual,
        //     ]);

        DB::commit();

        // Obtener estadísticas del día
        $registrosHoy = DB::connection('mysql')
            ->table('asistencias_th')
            ->where('tipo_empleado', $empleado->tipo_empleado)
            ->where('empleado_id', $empleado->empleado_id)
            ->whereDate('fecha_ingreso', $fechaActual)
            ->get();

        $abiertos = $registrosHoy->whereNull('fecha_salida')->count();
        $cerrados = $registrosHoy->whereNotNull('fecha_salida')->count();

        // Preparar respuesta detallada
        $respuesta = [
            'status' => 'success',
            'message' => $mensaje,
            'data' => [
                'empleado' => [
                    'id' => $empleado->empleado_id,
                    'nombre' => $empleado->nombre ?? 'N/A',
                    'identificacion' => $empleado->identificacion,
                ],
                'marcacion' => [
                    'tipo' => $tipoMarca,
                    'estado' => $esEntrada ? 'ABIERTO' : 'CERRADO',
                    'fecha' => $fechaActual,
                    'hora' => $horaActualFormatted,
                    'ubicacion' => $request->location,
                ],
                'resumen_dia' => [
                    'total_marcas' => $registrosHoy->count(),
                    'abiertos' => $abiertos,
                    'cerrados' => $cerrados,
                    'proximo_tipo' => $esEntrada ? 'SALIDA' : 'ENTRADA',
                ]
            ]
        ];

        return response()->json($respuesta);

    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Error al procesar RFID:', [
            'rfid' => $request->rfid,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Error en el sistema: ' . $e->getMessage()
        ], 500);
    }
}
    
    /**
     * Método para obtener el estado actual de un empleado
     * Útil para mostrar en pantallas o apps
     */
    public function estadoEmpleadoRFID($rfid)
    {
        try {
            $empleado = DB::connection('mysql')
                ->table('ficha_th')
                ->where('rfid', $rfid)
                ->where('estado', 1)
                ->first();

            if (!$empleado) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Empleado no encontrado'
                ], 404);
            }

            $fechaActual = now()->format('Y-m-d');
            
            $ultimoRegistro = DB::connection('mysql')
                ->table('asistencias_th')
                ->where('tipo_empleado', $empleado->tipo_empleado)
                ->where('empleado_id', $empleado->empleado_id)
                ->whereDate('fecha_ingreso', $fechaActual)
                ->orderBy('fecha_ingreso', 'desc')
                ->orderBy('hora_ingreso', 'desc')
                ->first();

            $estado = 'NO_REGISTRADO'; // No ha marcado hoy
            
            if ($ultimoRegistro) {
                $estado = is_null($ultimoRegistro->fecha_salida) ? 'DENTRO' : 'FUERA';
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'empleado' => [
                        'id' => $empleado->empleado_id,
                        'nombre' => $empleado->nombre,
                        'identificacion' => $empleado->identificacion,
                        'tipo_empleado' => $empleado->tipo_empleado,
                    ],
                    'estado_actual' => $estado,
                    'ultima_marcacion' => $ultimoRegistro ? [
                        'tipo' => is_null($ultimoRegistro->fecha_salida) ? 'ENTRADA' : 'SALIDA',
                        'hora' => is_null($ultimoRegistro->fecha_salida) 
                            ? $ultimoRegistro->hora_ingreso 
                            : $ultimoRegistro->hora_salida,
                        'fecha' => $ultimoRegistro->fecha_ingreso,
                    ] : null
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
