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
                ], 400);
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
                            'status' => 'duplicado',
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

    public function storeRFID(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rfid' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        #bucasmos si el rfid ya existe
        $rfidExistente = DB::connection('mysql')
            ->table('rfid')
            ->where('codigo', $request->rfid)
            ->first();


        if ($rfidExistente) {
            return response()->json([
                'status' => 'error',
                'message' => 'El RFID ya existe.'
            ], 400);
        }

        #aqui creamos el rfid en la tabla rfid
        DB::connection('mysql')
            ->table('rfid')
            ->insert([
                'codigo' => $request->rfid,
                'estado' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);



        return response()->json([
            'status' => 'success',
            'message' => 'RFID almacenado correctamente.'
        ]);
    }
}
