<?php

namespace App\Http\Controllers\Api\Proyectos;

use App\Http\Controllers\Controller;
use App\Models\AnulacionApt;
use App\Models\CambioProcesoProyectos;
use App\Models\ProcesosProyectos;
use App\Models\Proyectos;
use App\Models\ProyectosDetalle;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GestionProyectosController extends Controller
{
    public function index()
    {
        //consulta a la bd los proyectos
        $proyectosGestion = DB::connection('mysql')
            ->table('proyecto')
            ->join('tipos_de_proyectos', 'proyecto.tipoProyecto_id', '=', 'tipos_de_proyectos.id')
            ->join('clientes', 'proyecto.cliente_id', '=', 'clientes.id')
            ->join('users', 'proyecto.usuario_crea_id', '=', 'users.id')
            ->select(
                'proyecto.*',
                'tipos_de_proyectos.nombre_tipo',
                'users.nombre as nombre',
                'clientes.emp_nombre',
            )
            ->where(function ($query) {
                $query->where('proyecto.encargado_id', Auth::id())
                    ->orWhere('proyecto.ingeniero_id', Auth::id());
            })
            ->where('proyecto.estado', 1)
            ->get();

        // Calcular el porcentaje de atraso y avance para cada proyecto
        foreach ($proyectosGestion as $proyecto) {
            $detalles = DB::connection('mysql')
                ->table('proyecto_detalle')
                ->where('proyecto_id', $proyecto->id)
                ->where('orden_proceso', '!=', 1)
                ->get();

            // Cálculo del atraso (como lo tenías)
            $totalEjecutando = $detalles->where('estado', 1)->count();
            $totalTerminado = $detalles->where('estado', 2)->count();
            $total = $totalEjecutando + $totalTerminado;

            $porcentaje = $total > 0 ? ($totalEjecutando / $total) * 100 : 0;
            $proyecto->porcentaje = round($porcentaje, 2);

            // Cálculo del avance (nuevo)
            $totalApartamentos = $detalles->count();
            $apartamentosRealizados = $totalTerminado;

            $avance = $totalApartamentos > 0 ? ($apartamentosRealizados / $totalApartamentos) * 100 : 0;
            $proyecto->avance = round($avance, 2);
        }

        return response()->json([
            'status' => 'success',
            'data' => $proyectosGestion
        ]);
    }

    public function indexProgreso(Request $request)
    {
        // Obtener el listado de nombres de torre por código
        $torresConNombre = DB::table('nombre_xtore')
            ->where('proyecto_id', $request->id)
            ->pluck('nombre_torre', 'torre') // ['A' => 'Torre Norte', 'B' => 'Torre Sur', ...]
            ->toArray();

        $proyectosDetalle = DB::connection('mysql')
            ->table('proyecto_detalle')
            ->leftJoin('users', 'proyecto_detalle.user_id', '=', 'users.id')
            ->leftJoin('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
            ->where('proyecto_detalle.proyecto_id', $request->id)
            ->select(
                'proyecto_detalle.torre',
                'proyecto_detalle.id',
                'proyecto_detalle.validacion',
                'proyecto_detalle.estado_validacion',
                'proyecto_detalle.consecutivo',
                'proyecto_detalle.orden_proceso',
                'proyecto_detalle.piso',
                'proyecto_detalle.apartamento',
                'proyecto_detalle.text_validacion',
                'proyecto_detalle.estado',
                'procesos_proyectos.nombre_proceso',
                'users.nombre as nombre'
            )
            ->get();

        $resultado = [];
        $torreResumen = [];

        foreach ($proyectosDetalle as $item) {
            $torre = $item->torre;
            $orden_proceso = $item->orden_proceso;
            $nombre_proceso = $item->nombre_proceso;
            $text_validacion = $item->text_validacion;
            $validacion = $item->validacion;
            $estado_validacion = $item->estado_validacion;
            $consecutivo = $item->consecutivo;
            $piso = $item->piso;


            if (!isset($resultado[$torre])) {
                $resultado[$torre] = [];
            }


            // Inicializar resumen por torre
            if (!isset($torreResumen[$torre])) {
                $torreResumen[$torre] = [
                    'nombre_torre' => $torresConNombre[$torre] ?? $torre,
                    'total_atraso' => 0,
                    'total_realizados' => 0,
                    'porcentaje_atraso' => 0,
                    'porcentaje_avance' => 0,
                    'serial_avance' => '0/0',
                    'pisos_unicos' => []
                ];
            }

            // Registrar piso único por torre
            if (!in_array($piso, $torreResumen[$torre]['pisos_unicos'])) {
                $torreResumen[$torre]['pisos_unicos'][] = $piso;
            }

            // Inicializar proceso por torre
            if (!isset($resultado[$torre][$orden_proceso])) {
                $resultado[$torre][$orden_proceso] = [
                    'nombre_proceso' => $nombre_proceso,
                    'text_validacion' => $text_validacion,
                    'estado_validacion' => $estado_validacion,
                    'validacion' => $validacion,
                    'pisos' => [],
                    'total_apartamentos' => 0,
                    'apartamentos_atraso' => 0,
                    'apartamentos_realizados' => 0,
                    'porcentaje_atraso' => 0,
                    'porcentaje_avance' => 0,
                ];
            }

            if (!isset($resultado[$torre][$orden_proceso]['pisos'][$piso])) {
                $resultado[$torre][$orden_proceso]['pisos'][$piso] = [];
            }

            // Agregar apartamento
            $resultado[$torre][$orden_proceso]['pisos'][$piso][] = [
                'id' => $item->id,
                'apartamento' => $item->apartamento,
                'consecutivo' => $consecutivo,
                'estado' => $item->estado,
            ];

            // Contar total apartamentos
            $resultado[$torre][$orden_proceso]['total_apartamentos'] += 1;

            // 👉 Solo sumar al resumen de torre si el proceso NO es 1
            if ($orden_proceso != 1) {
                if ($item->estado == 1) {
                    $resultado[$torre][$orden_proceso]['apartamentos_atraso'] += 1;
                    $torreResumen[$torre]['total_atraso'] += 1;
                }
                if ($item->estado == 2) {
                    $resultado[$torre][$orden_proceso]['apartamentos_realizados'] += 1;
                    $torreResumen[$torre]['total_realizados'] += 1;
                }
            } else {
                if ($item->estado == 1) {
                    $resultado[$torre][$orden_proceso]['apartamentos_atraso'] += 1;
                }
                if ($item->estado == 2) {
                    $resultado[$torre][$orden_proceso]['apartamentos_realizados'] += 1;
                }
            }
        }

        // Calcular porcentajes por proceso
        foreach ($resultado as $torre => &$procesos) {
            foreach ($procesos as $orden_proceso => &$proceso) {
                // Ignorar campo extra "nombre_torre"
                if ($orden_proceso === 'nombre_torre') continue;

                if ($orden_proceso == 1) {
                    $proceso['porcentaje_atraso'] = 0;
                    $proceso['porcentaje_avance'] = 0;
                    continue;
                }

                $total_atraso = $proceso['apartamentos_atraso'];
                $total_realizados = $proceso['apartamentos_realizados'];
                $denominador = $total_atraso + $total_realizados;

                $porcentaje_atraso = $denominador > 0 ? ($total_atraso / $denominador) * 100 : 0;
                $porcentaje_avance = $proceso['total_apartamentos'] > 0 ? ($total_realizados / $proceso['total_apartamentos']) * 100 : 0;

                $proceso['porcentaje_atraso'] = round($porcentaje_atraso, 2);
                $proceso['porcentaje_avance'] = round($porcentaje_avance, 2);
            }
        }

        // Calcular porcentaje y avance textual por torre
        foreach ($torreResumen as $torre => &$datos) {
            $total_atraso = $datos['total_atraso'];
            $total_realizados = $datos['total_realizados'];
            $denominador = $total_atraso + $total_realizados;

            $porcentaje_atraso = $denominador > 0 ? ($total_atraso / $denominador) * 100 : 0;
            $porcentaje_avance = $denominador > 0 ? ($total_realizados / $denominador) * 100 : 0;

            $datos['porcentaje_atraso'] = round($porcentaje_atraso, 2);
            $datos['porcentaje_avance'] = round($porcentaje_avance, 2);
            $datos['serial_avance'] = $total_realizados . '/' . $denominador;
            $datos['total_pisos'] = count($datos['pisos_unicos']);

            unset($datos['pisos_unicos']); // eliminar si no deseas mostrar el array
        }

        return response()->json([
            'status' => 'success',
            'data' => $resultado,
            'torreResumen' => $torreResumen
        ]);
    }

    public function destroy($id)
    {
        $iniciarProyecto = Proyectos::find($id);

        $iniciarProyecto->fecha_ini_proyecto = now();
        $iniciarProyecto->update();
    }

    public function IniciarTorre(Request $request)
    {
        // Validar los datos de entrada
        $validated = $request->validate([
            'proyecto' => 'required|exists:proyecto,id',
            'torre' => 'required'
        ]);

        $proyectoId = $validated['proyecto'];
        $torre = $validated['torre'];

        DB::beginTransaction();
        try {
            // 1. Actualizar todos los apartamentos del piso 1, proceso 1 para esta torre
            $updated = DB::table('proyecto_detalle')
                ->where('proyecto_id', $proyectoId)
                ->where('torre', $torre)
                ->where('piso', '1')
                ->where('orden_proceso', 1)
                ->update([
                    'estado' => '1',
                    'fecha_habilitado' => now(),
                    'fecha_ini_torre' => now(),
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Torre iniciada correctamente',
                'data' => [
                    'proyecto_id' => $proyectoId,
                    'torre' => $torre,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar la torre: ' . $e->getMessage(),
                'error_details' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function infoProyecto($id)
    {
        $info = Proyectos::find($id);

        return response()->json([
            'success' => true,
            'data' => $info
        ]);
    }

    // public function validarProceso(Request $request)
    // {

    //     // iniciode trasnacion
    //     DB::beginTransaction();

    //     try {
    //         // datos que llegan
    //         $request->validate([
    //             'torre' => 'required|string',
    //             'proyecto' => 'required',
    //             'orden_proceso' => 'required|integer|min:1',
    //             'piso' => 'required|integer',
    //         ]);


    //         // asignamos datos
    //         $torre = $request->torre;
    //         $proyecto = (int) $request->proyecto;
    //         $ordenProceso = (int) $request->orden_proceso;
    //         $pisoActual = (int) $request->piso;

    //         // ver si ya esta confirmada la validacion(estado1 )
    //         $pisosPrevios = ProyectosDetalle::where('torre', $torre)
    //             ->where('orden_proceso', $ordenProceso)
    //             ->where('proyecto_id', $proyecto)
    //             ->where('piso', $pisoActual)
    //             ->first();


    //         // proyecto padre
    //         $proyectoPadre = Proyectos::where('id',$proyecto)->first();
    //         $aptMinimos = $proyectoPadre->minimoApt;


    //         // si esta valdado no hacer nada
    //         if ($pisosPrevios->validacion === 1 && $pisosPrevios->estado_validacion === 1) {
    //             DB::commit();
    //             return response()->json([
    //                 'success' => "Ya validado co: FP-6325.22",
    //             ]);
    //         }



    //         // Buscar configuración del proceso, para saber el numero de piso que se deben cumplir del proceso anterior para poder activar el piso
    //         // del proceso actual
    //         $configProceso = CambioProcesoProyectos::where('proyecto_id', $proyecto)
    //             ->where('proceso', $ordenProceso)
    //             ->first();

    //         // si no existe da error
    //         if (!$configProceso) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Configuración de proceso no encontrada.',
    //             ], 400);
    //         }

    //         // asignamos el numero de pisos minimo que se requieren
    //         $pisosRequeridos = (int) $configProceso->numero;

    //         // Si no es el primer proceso, validar requisitos del anterior
    //         if ($ordenProceso > 1) {
    //             $procesoAnterior = $ordenProceso - 1;

    //             // no puede que pase, pero por si las mocasas, valdia que si llega que es proceso 2, pero no existe proceso anterior hubo un problema en la creacion, ya que no se organizo del 1 en adelante
    //             $existeProcesoAnterior = ProyectosDetalle::where('torre', $torre)
    //                 ->where('orden_proceso', $procesoAnterior)
    //                 ->where('proyecto_id', $proyecto)
    //                 ->get();

    //             if (!$existeProcesoAnterior) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'No se puede validar porque el proceso anterior no existe.',
    //                     'details' => ['proceso_faltante' => $procesoAnterior]
    //                 ], 400);
    //             }

    //             $puedeValidarse = false;

    //             if ($pisoActual === 1) {
    //                 // Validación para piso 1: verificar los primeros N pisos del proceso anterior
    //                 $pisosPrevios = ProyectosDetalle::where('torre', $torre)
    //                     ->where('orden_proceso', $procesoAnterior)
    //                     ->where('proyecto_id', $proyecto)
    //                     ->whereIn('piso', range(1, $pisosRequeridos))
    //                     ->where('estado', '2')
    //                     ->get()
    //                     ->groupBy('piso');

    //                 $pisosCumplenMinimo = 0;

    //                 foreach ($pisosPrevios as $piso => $aptos) {
    //                     if ($aptos->count() >= $proyectoPadre->aptMinimos) {
    //                         $pisosCumplenMinimo++;
    //                     }
    //                 }

    //                 if ($pisosCumplenMinimo < $pisosRequeridos) {
    //                     DB::commit();
    //                     return response()->json([
    //                         'success' => false,
    //                         'message' => "No se puede validar este piso porque no se han confirmado al menos $aptMinimos apartamentos en cada uno de los $pisosRequeridos pisos requeridos.",
    //                     ], 400);
    //                 }

    //                 // return;
    //             } else {
    //                 // si no es el piso 1
    //                 // valdiar que el proceso anterior este confirmado todo (estado: 2)
    //                 $totalPisos = $existeProcesoAnterior->pluck('piso')->unique()->count();

    //                 $procesoAntCompletado = ProyectosDetalle::where('torre', $torre)
    //                     ->where('orden_proceso', $procesoAnterior)
    //                     ->where('proyecto_id', $proyecto)
    //                     ->whereIn('piso', range(1, $totalPisos))
    //                     ->get();


    //                 // si esta completo si seguir con la logica no quedar en el if
    //                 $ProcesoPasadoCompleto = $procesoAntCompletado->isNotEmpty() && $procesoAntCompletado->every(fn($apt) => $apt->estado === 2);

    //                 // Validación para piso > 1: verificar piso activador del proceso anterior
    //                 $pisoActivador = $pisoActual + ($pisosRequeridos - 1);

    //                 if ($pisoActual !== $totalPisos) {
    //                     $activador = ProyectosDetalle::where('torre', $torre)
    //                         ->where('orden_proceso', $procesoAnterior)
    //                         ->where('proyecto_id', $proyecto)
    //                         ->where('piso', $pisoActivador)
    //                         ->first();

    //                     $puedeValidarse = $activador && $activador->estado == 2;

    //                     if (!$puedeValidarse) {
    //                         DB::commit();
    //                         return response()->json([
    //                             'success' => false,
    //                             'message' => 'No se puede validar este piso porque no se cumplen los requisitos del proceso anterior.',
    //                         ], 400);
    //                     }
    //                 } else {
    //                     $activador = ProyectosDetalle::where('torre', $torre)
    //                         ->where('orden_proceso', $procesoAnterior)
    //                         ->where('proyecto_id', $proyecto)
    //                         ->where('piso', $pisoActivador)
    //                         ->first();

    //                     $puedeValidarse = $activador && $activador->estado == 2;

    //                     if ($ProcesoPasadoCompleto == null) {
    //                         return response()->json([
    //                             'success' => false,
    //                             'message' => 'No se puede validar este piso porque no se cumplen los requisitos del proceso anterior--.',
    //                         ], 400);
    //                     }
    //                 }
    //             }

    //             // if (!$puedeValidarse && !$ProcesoPasadoCompleto) {
    //             //     return response()->json([
    //             //         'success' => false,
    //             //         'message' => 'No se puede validar este piso porque no se cumplen los requisitos del proceso anterior.',
    //             //     ], 400);
    //             // }
    //         }

    //         // Activar el piso actual y marcar validación
    //         ProyectosDetalle::where('torre', $torre)
    //             ->where('orden_proceso', $ordenProceso)
    //             ->where('proyecto_id', $proyecto)
    //             ->where('piso', $pisoActual)
    //             ->update([
    //                 'estado_validacion' => 1,
    //                 'fecha_validacion' => now(),
    //                 'user_id' => Auth::id(),
    //                 'estado' => 1,
    //                 'fecha_habilitado' => now(),
    //             ]);

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Proceso validado exitosamente.'
    //         ], 200);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error al validar proceso.',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    public function validarProceso(Request $request)
    {
        DB::beginTransaction();

        try {
            // Validar datos de entrada
            $request->validate([
                'torre' => 'required|string',
                'proyecto' => 'required',
                'orden_proceso' => 'required|integer|min:1',
                'piso' => 'required|integer',
            ]);

            $torre = $request->torre;
            $proyecto = (int) $request->proyecto;
            $ordenProceso = (int) $request->orden_proceso;
            $pisoActual = (int) $request->piso;

            // Buscar información del piso actual
            $pisosPrevios = ProyectosDetalle::where('torre', $torre)
                ->where('orden_proceso', $ordenProceso)
                ->where('proyecto_id', $proyecto)
                ->where('piso', $pisoActual)
                ->first();

            // Proyecto padre
            $proyectoPadre = Proyectos::find($proyecto);
            $aptMinimos = $proyectoPadre->minimoApt;

            // Si ya está validado, no hacer nada
            if ($pisosPrevios->validacion === 1 && $pisosPrevios->estado_validacion === 1) {
                DB::commit();
                return response()->json([
                    'success' => "Ya validado co: FP-6325.22",
                ]);
            }

            // Obtener configuración del proceso
            $configProceso = CambioProcesoProyectos::where('proyecto_id', $proyecto)
                ->where('proceso', $ordenProceso)
                ->first();

            if (!$configProceso) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuración de proceso no encontrada.',
                ], 400);
            }

            $pisosRequeridos = (int) $configProceso->numero;

            if ($ordenProceso > 1) {
                $procesoAnterior = $ordenProceso - 1;

                $existeProcesoAnterior = ProyectosDetalle::where('torre', $torre)
                    ->where('orden_proceso', $procesoAnterior)
                    ->where('proyecto_id', $proyecto)
                    ->get();

                if ($existeProcesoAnterior->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede validar porque el proceso anterior no existe.',
                        'details' => ['proceso_faltante' => $procesoAnterior]
                    ], 400);
                }

                if ($pisoActual === 1) {
                    // Validación para piso 1
                    $pisosPrevios = ProyectosDetalle::where('torre', $torre)
                        ->where('orden_proceso', $procesoAnterior)
                        ->where('proyecto_id', $proyecto)
                        ->whereIn('piso', range(1, $pisosRequeridos))
                        ->where('estado', 2)
                        ->get()
                        ->groupBy('piso');

                    $pisosCumplenMinimo = 0;

                    foreach ($pisosPrevios as $piso => $aptos) {
                        if ($aptos->count() >= $aptMinimos) {
                            $pisosCumplenMinimo++;
                        }
                    }

                    if ($pisosCumplenMinimo < $pisosRequeridos) {
                        DB::commit();
                        return response()->json([
                            'success' => false,
                            'message' => "No se puede validar este piso porque no se han confirmado al menos $aptMinimos apartamentos en cada uno de los $pisosRequeridos pisos requeridos.",
                        ], 400);
                    }
                } else {
                    // Validación para piso > 1
                    $totalPisos = $existeProcesoAnterior->pluck('piso')->unique()->count();

                    $procesoAntCompletado = ProyectosDetalle::where('torre', $torre)
                        ->where('orden_proceso', $procesoAnterior)
                        ->where('proyecto_id', $proyecto)
                        ->whereIn('piso', range(1, $totalPisos))
                        ->get();

                    $ProcesoPasadoCompleto = $procesoAntCompletado->isNotEmpty() &&
                        $procesoAntCompletado->every(fn($apt) => $apt->estado === 2);

                    $pisoActivador = $pisoActual + ($pisosRequeridos - 1);

                    $activador = ProyectosDetalle::where('torre', $torre)
                        ->where('orden_proceso', $procesoAnterior)
                        ->where('proyecto_id', $proyecto)
                        ->where('piso', $pisoActivador)
                        ->where('estado', 2)
                        ->get();

                    $puedeValidarse = $activador->count() >= $aptMinimos;

                    if (
                        ($pisoActual !== $totalPisos && !$puedeValidarse) ||
                        ($pisoActual === $totalPisos && (!$puedeValidarse || !$ProcesoPasadoCompleto))
                    ) {
                        return response()->json([
                            'success' => false,
                            'message' => "No se puede validar este piso porque no se cumplen los requisitos del proceso anterior.",
                        ], 400);
                    }
                }
            }

            // Validación exitosa: habilitar piso actual
            ProyectosDetalle::where('torre', $torre)
                ->where('orden_proceso', $ordenProceso)
                ->where('proyecto_id', $proyecto)
                ->where('piso', $pisoActual)
                ->update([
                    'estado_validacion' => 1,
                    'fecha_validacion' => now(),
                    'user_id' => Auth::id(),
                    'estado' => 1,
                    'fecha_habilitado' => now(),
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Proceso validado exitosamente.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al validar proceso.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // confirmar con secuencia de desfase
    public function confirmarApt($id)
    {
        DB::beginTransaction();
        //inicio de flujo

        try {

            //buscamos el detalle del proyeto para ese piso
            $info = ProyectosDetalle::findOrFail($id);


            $numeroCambio = $info->orden_proceso + "1";
            //buscamos los cambio de piso para proceso, el numero minimo de pisos que debe cumplir el proecso anterior
            $CambioProcesoProyectos = CambioProcesoProyectos::where('proyecto_id', $info->proyecto_id)
                ->where('proceso', $numeroCambio)->first();

            //vemos si ese piso ya esta confirmado, si lo esta se envia mensaje de error
            if ($info->estado == 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este apartamento ya está confirmado'
                ], 400);
            }

            //definimos datos numericos
            $torre = $info->torre;
            $orden_proceso = (int) $info->orden_proceso;
            $piso = (int) $info->piso;

            //buscamos el proyecto padre
            $proyecto = Proyectos::findOrFail($info->proyecto_id);
            $pisosPorProceso = $CambioProcesoProyectos ? (int) $CambioProcesoProyectos->numero : 0;
            $aptMinimos = (int) $proyecto->minimoApt; //pisos minimos para tomarlo como completo




            if ($orden_proceso === 1) {
                // Confirmar el apartamento sin reglas si es proceso 1
                $info->estado = 2;
                $info->fecha_fin = now();
                $info->user_id = Auth::id();
                $info->save();
            } else {

                // Verificar si el mismo apartamento esta confirmado en el proceso anterior
                $verPisoAnteriorConfirmado = ProyectosDetalle::where('torre', $torre)
                    ->where('orden_proceso', $orden_proceso - 1)
                    ->where('proyecto_id', $proyecto->id)
                    ->where('consecutivo', $info->consecutivo)
                    ->where('piso', $piso)
                    ->first();


                // Confirmar el apartamento si el mismo apartamento esta confirmado en proceso anterior estado=2
                if ($verPisoAnteriorConfirmado->estado == "2") {
                    $info->estado = 2;
                    $info->fecha_fin = now();
                    $info->user_id = Auth::id();
                    $info->save();
                } else {
                    // si no esta confirmado no se confirma, se envia mensaje de error
                    return response()->json([
                        'success' => false,
                        'message' => 'El apartamento no puede ser confirmado por que el apartamento en proceso anterior no esta confirmado'
                    ], 400);
                }
            }

            // Verificar si todos los aptos de este piso ya están confirmados
            $aptosDelPiso = ProyectosDetalle::where('torre', $torre)
                ->where('orden_proceso', $orden_proceso)
                ->where('proyecto_id', $proyecto->id)
                ->where('piso', $piso)
                ->get();

            //validar que todo los apt de ese piso esten confirmados

            // $todosConfirmados = $aptosDelPiso->every(fn($apt) => $apt->estado == 2);
            $numAptos = $aptosDelPiso->count(); // Total de aptos
            $confirmados = $aptosDelPiso->where('estado', 2)->count(); // Confirmados
            $esValido = $confirmados >= $aptMinimos;


            //si estan confirmado todo los apartamentos de ese piso sigue la logica, del resto termina aqui.
            if ($esValido) {

                if ($orden_proceso === 1) {
                    // Si es el primer proceso (no depende de ningún proceso anterior)
                    ProyectosDetalle::where('torre', $torre)
                        ->where('orden_proceso', $orden_proceso)
                        ->where('piso', $piso + 1)
                        ->where('proyecto_id', $proyecto->id)
                        ->where('estado', 0)
                        ->update(['estado' => 1, 'fecha_habilitado' => now()]);

                    // Validación que el proyecto siguiente ya esta activo, es decir piso 1 iniciado estado diferente a 0
                    $InicioProceso = ProyectosDetalle::where('torre', $torre)
                        ->where('orden_proceso', $orden_proceso + 1)
                        ->where('proyecto_id', $proyecto->id)
                        ->where('piso', 1)
                        ->get();


                    $confirmarInicioProceso = $InicioProceso->isNotEmpty() && $InicioProceso->every(fn($apt) => $apt->estado != 0);


                    //si ya fue iniciado, habilitamos piso de acuerdo al orden de la secuanecia
                    if ($confirmarInicioProceso == true) {
                        // calculo del piso que se debe validar
                        $nuevoPisoParaActivar = $piso - ($pisosPorProceso - 1);

                        // solo para procesos con validacion
                        $vervalidacionPiso = ProyectosDetalle::where('torre', $torre)
                            ->where('orden_proceso', $orden_proceso + 1)
                            ->where('proyecto_id', $proyecto->id)
                            ->where('piso', $nuevoPisoParaActivar)
                            // ->where('consecutivo', $info->consecutivo)
                            ->get();

                        // Verificar que TODOS estén en estado validación = 1 y estado_validacion = 0
                        $todosPendientes = $vervalidacionPiso->every(function ($apt) {
                            return $apt->validacion == 1 && $apt->estado_validacion == 0;
                        });

                        if ($todosPendientes) {
                            DB::commit();
                            return response()->json([
                                'success' => true,
                                'message' => 'Todos los apartamentos del piso están validados y pendientes por habilitación.',
                            ]);
                        }
                        ProyectosDetalle::where('torre', $torre)
                            ->where('orden_proceso', $orden_proceso + 1)
                            ->where('proyecto_id', $proyecto->id)
                            ->where('piso', $nuevoPisoParaActivar)
                            ->where('estado', 0)
                            ->update([
                                'estado' => 1,
                                'fecha_habilitado' => now(),
                            ]);
                    } else {
                        // si no esta inicializado, se debe revisar si el proceso actual cumple con lso requisitos del minimo de pisos para que el proceso pueda ser incializado
                        if ($piso >= $pisosPorProceso) {
                            $nuevoProceso = $orden_proceso + 1;

                            // Se revisa si el prcceso siguiente que se peinsa habilitar necesita validacion
                            $procesoActual = ProyectosDetalle::where('torre', $torre)
                                ->where('orden_proceso', $nuevoProceso)
                                ->where('proyecto_id', $proyecto->id)
                                ->first();

                            // si necesita validacion pero no esta validadio no hacer nada
                            if ($procesoActual->validacion == 1 && $procesoActual->estado_validacion == 0) {
                                DB::commit();
                                return response()->json([
                                    'success' => true,
                                ]);
                            }

                            // revisar si hay proceso siguiente, para evitar bug
                            $existeSiguienteProceso = ProyectosDetalle::where('torre', $torre)
                                ->where('orden_proceso', $nuevoProceso)
                                ->where('proyecto_id', $proyecto->id)
                                ->exists();

                            // Validación que el piso anterior no este en estado 0 (bug)
                            $InicioProceso = ProyectosDetalle::where('torre', $torre)
                                ->where('orden_proceso', $orden_proceso)
                                ->where('proyecto_id', $proyecto->id)
                                ->where('piso', $piso - 1)
                                ->get();

                            // si todo los apt estan en estado diferente a 0 esto sera true
                            $confirmarInicioProceso = $InicioProceso->isNotEmpty() && $InicioProceso->every(fn($apt) => $apt->estado != 0);


                            // si es true, entramos al IF
                            if ($existeSiguienteProceso) {
                                // Activar los apt del piso del siguiente proceso
                                if ($InicioProceso) {
                                    ProyectosDetalle::where('torre', $torre)
                                        ->where('orden_proceso', $nuevoProceso)
                                        ->where('proyecto_id', $proyecto->id)
                                        ->where('piso', 1)
                                        ->where('estado', 0)
                                        ->update([
                                            'estado' => 1,
                                            'fecha_habilitado' => now(),
                                        ]);
                                }
                            }
                        }
                    }
                } else {
                    // si no es proceso 1, debe cumplir ciertas validaciones
                    // buscamos proceso siguiente, si tiene datos es por que hay mas procesos
                    $InicioProceso = ProyectosDetalle::where('torre', $torre)
                        ->where('orden_proceso', $orden_proceso + 1)
                        ->where('proyecto_id', $proyecto->id)
                        ->where('piso', 1)
                        ->get();

                    // hay mas procesos segiur logica, si no hay mas, no hacer nada, se valida si el array esta vacio no hay mas procesos
                    if ($InicioProceso->isNotEmpty()) {
                        // validamos si el proceso sigueiente ya tiene el primer piso con estados de los apt distintos a 0, significa que ya se inicio
                        $confirmarInicioProceso = $InicioProceso->isNotEmpty() && $InicioProceso->every(fn($apt) => $apt->estado != "0");

                        //esta iniciado el proceso siguiente?
                        if ($confirmarInicioProceso == true) {

                            $nuevoPisoParaActivar = $piso - ($pisosPorProceso - 1);

                            // se busca si el prococeso siguiente ya tiene confirmado el apt, estado diferente a 0
                            $BusquedaConfirmacionDEValdiacion = ProyectosDetalle::where('torre', $torre)
                                ->where('orden_proceso', $orden_proceso + 1)
                                ->where('proyecto_id', $proyecto->id)
                                ->where('piso', $nuevoPisoParaActivar)
                                // ->where('estado', 1)
                                ->first();

                            // se compara, si la validaion esta confirmada sigue su flujo, si no no hacer nada
                            if ($BusquedaConfirmacionDEValdiacion->validacion == 1 && $BusquedaConfirmacionDEValdiacion->estado_validacion == 0) {
                                DB::commit();
                                return response()->json([
                                    'success' => true,
                                ]);
                            }

                            ProyectosDetalle::where('torre', $torre)
                                ->where('orden_proceso', $orden_proceso + 1)
                                ->where('proyecto_id', $proyecto->id)
                                ->where('piso', $nuevoPisoParaActivar)
                                ->where('estado', 0)
                                ->update([
                                    'estado' => 1,
                                    'fecha_habilitado' => now(),
                                ]);
                        } else {

                            // Se valida que el proceso actual tenga confirmado los pisos de pisosPorProceso, si lo esta, iniciar proceso siguiente
                            // $InicioProceso = ProyectosDetalle::where('torre', $torre)
                            //     ->where('orden_proceso', $orden_proceso)
                            //     ->where('proyecto_id', $proyecto->id)
                            //     ->whereIn('piso', range(1, $pisosPorProceso))
                            //     ->get();

                            // $confirmarInicioProceso = $InicioProceso->isNotEmpty() && $InicioProceso->every(fn($apt) => $apt->estado == "2");


                            //logica nuerva de acuerdo a minimo de apt
                            $InicioProceso = ProyectosDetalle::where('torre', $torre)
                                ->where('orden_proceso', $orden_proceso)
                                ->where('proyecto_id', $proyecto->id)
                                ->whereIn('piso', range(1, $pisosPorProceso))
                                ->get();

                            // Agrupar por piso
                            $pisos = $InicioProceso->groupBy('piso');

                            // Verificar si se cumplen todos los pisos
                            $confirmarInicioProceso = $pisos->count() == $pisosPorProceso &&
                                $pisos->every(function ($aptosDelPiso) use ($aptMinimos) {
                                    return $aptosDelPiso->where('estado', 2)->count() >= $aptMinimos;
                                });

                            //si el proceso actual cumple con los pisos re pisosPorPrceos en estado 2, activar
                            if ($confirmarInicioProceso == true) {
                                // Verificar si el proceso siguiente ya puede comenzar
                                $nuevoProceso = $orden_proceso + 1;

                                $existeSiguienteProceso = ProyectosDetalle::where('torre', $torre)
                                    ->where('orden_proceso', $nuevoProceso)
                                    ->where('proyecto_id', $proyecto->id)
                                    ->exists();

                                // Validación manual para revisar si neceita validacion
                                $procesoActual = ProyectosDetalle::where('torre', $torre)
                                    ->where('orden_proceso', $orden_proceso + 1)
                                    ->where('proyecto_id', $proyecto->id)
                                    ->first();

                                // si requiere validacion no hacer nada, estos se validan por otra funcion
                                if ($procesoActual->validacion == 1 && $procesoActual->estado_validacion == 0) {
                                    DB::commit();

                                    return response()->json([
                                        'success' => true,
                                    ]);
                                } else {
                                    // si no necesita validacion validar el piso 1 todo los apt pasarlos a estado 1, habilitado
                                    if ($existeSiguienteProceso) {
                                        // Activar el primer piso del siguiente proceso
                                        ProyectosDetalle::where('torre', $torre)
                                            ->where('orden_proceso', $nuevoProceso)
                                            ->where('proyecto_id', $proyecto->id)
                                            ->where('piso', 1)
                                            ->where('estado', 0)
                                            ->update([
                                                'estado' => 1,
                                                'fecha_habilitado' => now(),
                                            ]);
                                    }
                                }
                            }


                            //buscamos los cambio de piso para proceso
                            $CambioProcesoProyectosActivador = CambioProcesoProyectos::where('proyecto_id', $info->proyecto_id)
                                ->where('proceso', $info->orden_proceso)->first();
                            // activador manual en caso de que aplique el bug
                            $pisoDeActivador = $piso + $CambioProcesoProyectosActivador->numero;

                            // se trata de manejar bug, se valida si el activador del piso = pisoDeActivador del proceso anteriro fue activado, si lo es activar manual
                            $EstadoActivador = ProyectosDetalle::where('torre', $torre)
                                ->where('orden_proceso', $orden_proceso - 1)
                                ->where('proyecto_id', $proyecto->id)
                                ->where('piso', $pisoDeActivador)
                                ->get();



                            // se trata de manejar bug, se valida si el piso siguiente del mismo proceso fue activado, si lo es activar manual
                            $EstadoSiguienteInactivo = ProyectosDetalle::where('torre', $torre)
                                ->where('orden_proceso', $orden_proceso)
                                ->where('proyecto_id', $proyecto->id)
                                ->where('piso', $piso + 1)
                                ->get();




                            $confirmarActivarPisoSiguiente = $EstadoActivador->isNotEmpty() && $EstadoActivador->every(fn($apt) => $apt->estado == 2);
                            $pisoSiguienteNoActivo = $EstadoSiguienteInactivo->isNotEmpty() && $EstadoSiguienteInactivo->every(fn($apt) => $apt->estado == 0);


                            if ($confirmarActivarPisoSiguiente && $pisoSiguienteNoActivo) {
                                ProyectosDetalle::where('torre', $torre)
                                    ->where('orden_proceso', $orden_proceso)
                                    ->where('proyecto_id', $proyecto->id)
                                    ->where('piso', $piso + 1)
                                    ->where('estado', 0)
                                    ->update([
                                        'estado' => 1,
                                        'fecha_habilitado' => now(),
                                    ]);
                            }
                        }
                    }
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'data' => $info
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al confirmar apartamento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // public function activacionXDia(Request $request)
    // {
    //     $proyectoId = $request->proyecto_id;

    //     // Cantidad de apartamentos a activar por día
    //     $proyectoAptActivar = Proyectos::where('id', $proyectoId)
    //         ->select('activador_pordia_apt')
    //         ->first();

    //     $cantidadActivar = $proyectoAptActivar->activador_pordia_apt;

    //     $procesos = ProyectosDetalle::where('proyecto_id', $proyectoId)
    //         ->select('orden_proceso')
    //         ->distinct()
    //         ->orderBy('orden_proceso')
    //         ->pluck('orden_proceso')
    //         ->values();

    //     for ($i = 0; $i < $procesos->count() - 1; $i++) {
    //         $procesoActual = $procesos[$i];
    //         $procesoSiguiente = $procesos[$i + 1];

    //         $registrosActual = ProyectosDetalle::where('proyecto_id', $proyectoId)
    //             ->where('orden_proceso', $procesoActual)
    //             ->where('torre', $request->torre)
    //             ->get();

    //         $completado = $registrosActual->every(fn($r) => $r->estado == "2");

    //         if (!$completado) {
    //             continue;
    //         }

    //         $pendientesSiguiente = ProyectosDetalle::where('proyecto_id', $proyectoId)
    //             ->where('orden_proceso', $procesoSiguiente)
    //             ->where('estado', 0)
    //             ->where('torre', $request->torre)
    //             ->orderBy('consecutivo')
    //             ->get();

    //         // Validación manual
    //         $procesoActual = ProyectosDetalle::where('torre', $request->torre)
    //             ->where('orden_proceso', $procesoSiguiente)
    //             ->where('proyecto_id', $proyectoId)
    //             ->first();

    //         if ($procesoActual->validacion == 1 && $procesoActual->estado_validacion == 0) {
    //             continue;
    //         }

    //         $pendientesSiguiente2 = ProyectosDetalle::where('proyecto_id', $proyectoId)
    //             ->where('orden_proceso', $procesoSiguiente)
    //             ->whereIn('estado', [1, 2])
    //             ->where('torre', $request->torre)
    //             ->orderBy('consecutivo')
    //             ->get();

    //         if ($pendientesSiguiente->isEmpty()) {
    //             continue;
    //         }

    //         $hoy = Carbon::now()->toDateString();
    //         $ya_habilitado_hoy = $pendientesSiguiente2->contains('fecha_habilitado', $hoy);

    //         if ($ya_habilitado_hoy) {
    //             continue;
    //         }

    //         // Obtener los primeros N apartamentos pendientes
    //         $apartamentosPorActivar = ProyectosDetalle::where('proyecto_id', $proyectoId)
    //             ->where('orden_proceso', $procesoSiguiente)
    //             ->where('estado', 0)
    //             ->where('torre', $request->torre)
    //             ->orderBy('consecutivo')
    //             ->limit($cantidadActivar) // 👉 Solo activar N apartamentos por día
    //             ->get();

    //         foreach ($apartamentosPorActivar as $apartamento) {
    //             $apartamento->update([
    //                 'fecha_habilitado' => $hoy,
    //                 'estado' => 1,
    //             ]);
    //         }

    //         // Solo habilitar N apartamentos por día, por eso hacemos break aquí
    //         break;
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Validacion de apartamentos que requieren actualizacion por día, realizada',
    //     ]);
    // }

    public function InformeDetalladoProyectos($id)
    {
        $proyectoId = $id;

        if (!$proyectoId) {
            return response()->json([
                'success' => false,
                'message' => 'ID de proyecto no proporcionado.',
            ], 400);
        }

        // Obtener todos los detalles del proyecto incluyendo torre y proceso
        $detalles = DB::table('proyecto_detalle')
            ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
            ->select(
                'proyecto_detalle.torre',
                'proyecto_detalle.estado',
                'procesos_proyectos.nombre_proceso as proceso'
            )
            ->where('proyecto_detalle.proyecto_id', $proyectoId)
            ->get();

        if ($detalles->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron detalles para el proyecto.',
            ], 404);
        }

        // Agrupar por proceso
        $procesos = $detalles->groupBy('proceso');
        $torres = $detalles->pluck('torre')->unique()->sort()->values(); // torre1, torre2, etc.

        $resultado = [];

        foreach ($procesos as $proceso => $itemsProceso) {
            $fila = ['proceso' => $proceso];
            $totalGlobal = 0;
            $terminadosGlobal = 0;

            foreach ($torres as $torre) {
                $filtrados = $itemsProceso->where('torre', $torre);
                $total = $filtrados->count();
                $terminados = $filtrados->where('estado', 2)->count();

                $porcentaje = $total > 0 ? round(($terminados / $total) * 100, 2) : 0;
                $fila["torre_{$torre}"] = "{$terminados}/{$total} ({$porcentaje}%)";

                $totalGlobal += $total;
                $terminadosGlobal += $terminados;
            }

            // Agregar total general por proceso
            $porcentajeGlobal = $totalGlobal > 0 ? round(($terminadosGlobal / $totalGlobal) * 100, 2) : 0;
            $fila["total"] = "{$terminadosGlobal}/{$totalGlobal} ({$porcentajeGlobal}%)";

            $resultado[] = $fila;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'torres' => $torres,
                'reporte' => $resultado
            ]
        ]);
    }

    // cambio estado de apartamentos confirado por erro-mentira
    public function CambioEstadosApt(Request $request)
    {

        DB::beginTransaction();
        //inicio de flujo

        try {

            //buscamos el detalle del proyeto para ese piso
            $info = ProyectosDetalle::findOrFail($request->aptId);

            $numeroCambio = $info->orden_proceso + "1";
            //buscamos los cambio de piso para proceso, el numero minimo de pisos que debe cumplir el proecso anterior
            $CambioProcesoProyectos = CambioProcesoProyectos::where('proyecto_id', $info->proyecto_id)
                ->where('proceso', $numeroCambio)->first();

            $pisosPorProceso = $CambioProcesoProyectos ? (int) $CambioProcesoProyectos->numero : 0;




            // se registra historial de cambio de estado del apt
            $LogCambioEstadoApt = new AnulacionApt();
            $LogCambioEstadoApt->motivo = $request->detalle;
            $LogCambioEstadoApt->piso = (int) $info->piso;
            $LogCambioEstadoApt->apt =  $request->aptId;
            $LogCambioEstadoApt->fecha_confirmo = $info->fecha_fin;
            $LogCambioEstadoApt->userConfirmo_id = $info->user_id;
            $LogCambioEstadoApt->user_id = Auth::id();
            $LogCambioEstadoApt->proyecto_id = $info->proyecto_id;
            $LogCambioEstadoApt->save();



            // se actualiza el estado del apt
            $info->estado = 1;
            $info->fecha_habilitado =  now();
            $info->fecha_fin = null;
            $info->user_id = null;
            $info->update();

            DB::commit();
            return response()->json([
                'success' => true,
                'data' => $info
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al confirmar apartamento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // cambio estado de apartamentos confirado por erro-mentira informativo
    public function CambioEstadosAptInformativo(Request $request)
    {
        try {
            $info = ProyectosDetalle::findOrFail($request->aptId);
            $proyectoId = $info->proyecto_id;
            $pisoActual = (int) $info->piso;
            $ordenProcesoActual = (int) $info->orden_proceso;

            $numeroCambio = $ordenProcesoActual + 1;

            $CambioProcesoProyectos = CambioProcesoProyectos::where('proyecto_id', $proyectoId)
                ->where('proceso', $numeroCambio)
                ->first();

            $pisosPorProceso = $CambioProcesoProyectos ? (int) $CambioProcesoProyectos->numero : 0;

            // Simulación del impacto
            $pisosAfectados = [];
            $procesosAfectados = [];

            // Buscamos los pisos siguientes del mismo proceso
            $pisosPosteriores = ProyectosDetalle::where('proyecto_id', $proyectoId)
                ->where('orden_proceso', $ordenProcesoActual)
                ->where('piso', '>', $pisoActual)
                ->where('estado', 0)
                ->get();

            foreach ($pisosPosteriores as $piso) {
                $pisosAfectados[] = [
                    'id' => $piso->id,
                    'piso' => $piso->piso,
                    'nuevo_estado' => 0
                ];
            }

            // Si el proceso siguiente ya tiene activaciones, también afectarlo
            $procesosSiguientes = ProyectosDetalle::where('proyecto_id', $proyectoId)
                ->where('orden_proceso', $numeroCambio)
                ->where('estado', 0)
                ->get();

            foreach ($procesosSiguientes as $proc) {
                $procesosAfectados[] = [
                    'id' => $proc->id,
                    'piso' => $proc->piso,
                    'orden_proceso' => $proc->orden_proceso,
                    'nuevo_estado' => 0
                ];
            }

            return response()->json([
                'success' => true,
                'mensaje' => 'Esta es una vista previa. No se han aplicado cambios.',
                'afecta_pisos' => $pisosAfectados,
                'afecta_procesos' => $procesosAfectados,
                'apartamento_original' => $info
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en consulta informativa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function simularAnulacion(Request $request)
    {
        $aptId = $request->aptId;

        $apt = ProyectosDetalle::findOrFail($aptId);

        $anulado = [
            'id' => $apt->id,
            'proceso' => $apt->orden_proceso,
            'piso' => $apt->piso,
        ];

        $proyecto_id = $apt->proyecto_id;

        $afectados = [];

        // Obtener el proceso actual
        $procesoActual = $apt->orden_proceso;
        $pisoActual = $apt->piso;

        // Buscar si afecta pisos posteriores del mismo proceso
        $pisosPosteriores = ProyectosDetalle::where('proyecto_id', $proyecto_id)
            ->where('orden_proceso', $procesoActual)
            ->where('piso', '>', $pisoActual)
            ->where('estado', '!=', 0)
            ->get();

        foreach ($pisosPosteriores as $piso) {
            $afectados[] = [
                'id' => $piso->id,
                'proceso' => $piso->orden_proceso,
                'piso' => $piso->piso,
                'razon' => 'Piso posterior del mismo proceso',
            ];
        }

        // Buscar si afecta al siguiente proceso
        $procesoSiguiente = $procesoActual + 1;
        $configProceso = CambioProcesoProyectos::where('proyecto_id', $proyecto_id)
            ->where('proceso', $procesoSiguiente)
            ->first();

        if ($configProceso) {
            $pisosRequeridos = (int) $configProceso->numero;

            // Verifica si el pisoActual es uno de los necesarios
            if ($pisoActual <= $pisosRequeridos) {
                $pisosSiguienteProceso = ProyectosDetalle::where('proyecto_id', $proyecto_id)
                    ->where('orden_proceso', $procesoSiguiente)
                    ->where('estado', '!=', 0)
                    ->get();

                foreach ($pisosSiguienteProceso as $piso) {
                    $afectados[] = [
                        'id' => $piso->id,
                        'proceso' => $piso->orden_proceso,
                        'piso' => $piso->piso,
                        'razon' => 'No se cumplen los pisos requeridos del proceso anterior',
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'resultado' => [
                'anulado' => $anulado,
                'afectados' => $afectados,
            ],
        ]);
    }

    // se va a trabajar en una nueva logica para el tema de activavion de apt
    // confirmar con secuencia de desfase
    public function confirmarAptNuevaLogica($id)
    {

        DB::beginTransaction();
        //inicio de flujo

        try {
            //buscamos el detalle del proyeto para ese piso
            $info = ProyectosDetalle::findOrFail($id);

            //definimos datos numericos
            $torre = $info->torre;
            $orden_proceso = (int) $info->orden_proceso;
            $piso = (int) $info->piso;
            $numeroCambio = $info->orden_proceso + "1";

            //buscamos los cambio de piso para el proceso, el numero minimo de pisos que debe cumplir el proecso anterior
            $CambioProcesoProyectos = CambioProcesoProyectos::where('proyecto_id', $info->proyecto_id)
                ->where('proceso', $numeroCambio)->first();

            //buscamos el proyecto padre
            $proyecto = Proyectos::findOrFail($info->proyecto_id);
            $pisosPorProceso = $CambioProcesoProyectos ? (int) $CambioProcesoProyectos->numero : 0;
            $aptMinimos = (int) $proyecto->minimoApt; //pisos minimos para tomarlo como completo

            //buscamos el nombre del proceso para la validacion
            $TipoProceso = strtolower(ProcesosProyectos::where('id', $info->procesos_proyectos_id)->value('nombre_proceso'));

            $info->estado = 2;
            $info->fecha_fin = now();
            $info->user_id = Auth::id();
            $info->save();


            // Verificar si todos los aptos de este piso ya están confirmados
            $aptosDelPiso = ProyectosDetalle::where('torre', $torre)
                ->where('orden_proceso', $orden_proceso)
                ->where('proyecto_id', $proyecto->id)
                ->where('piso', $piso)
                ->get();

            // si el proceso es fundida este debe tener todo el piso completo para habliar el siguiente piso del mismo
            if ($TipoProceso ===  'fundida') {
                $confirmarInicioProceso = $aptosDelPiso->isNotEmpty() && $aptosDelPiso->every(fn($apt) => $apt->estado == "2");
                if ($confirmarInicioProceso) {
                    $esValido = true; //true
                } else {
                    $esValido = false; //false
                }
            } else {
                //validar que todo los apt de ese piso esten confirmados, al menos >= a los pisos minimos
                $confirmados = $aptosDelPiso->where('estado', 2)->count(); // Confirmados
                $esValido = $confirmados >= $aptMinimos;
            }




            if ($esValido) {
                // aqui va a ir el swich de comparaciones
                switch ($TipoProceso) {
                    case 'fundida':
                        // AL entrar en fundida, este no depende de nadie, el solo confirma y se auto habilita a si mismo
                        ProyectosDetalle::where('torre', $torre)
                            ->where('orden_proceso', $orden_proceso)
                            ->where('piso', $piso + 1)
                            ->where('proyecto_id', $proyecto->id)
                            ->where('estado', 0)
                            ->update(['estado' => 1, 'fecha_habilitado' => now()]);

                        // se debe hacer dos validaciones en dos procesos, destapada y prolongacion
                        // destapada
                        // Validación si el procesotiene el piso 1 activo
                        $InicioProcesoDestapada = DB::connection('mysql')
                            ->table('proyecto_detalle')
                            ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
                            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', ['destapada']) // nombre en minúsculas
                            ->where('proyecto_detalle.torre', $torre)
                            ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                            ->where('proyecto_detalle.piso', 1)
                            ->select('proyecto_detalle.*')
                            ->get();

                        $confirmarInicioProcesoDestapada = $InicioProcesoDestapada->isNotEmpty() && $InicioProcesoDestapada->every(fn($apt) => $apt->estado != 0);


                        //si ya fue iniciado, habilitamos piso de acuerdo al orden de la secuanecia
                        if ($confirmarInicioProcesoDestapada == true) {
                            // calculo del piso que se debe validar
                            $nuevoPisoParaActivar = $piso - ($pisosPorProceso - 1);

                            // solo para procesos con validacion
                            $vervalidacionPiso = ProyectosDetalle::where('torre', $torre)
                                ->where('orden_proceso', $orden_proceso + 1)
                                ->where('proyecto_id', $proyecto->id)
                                ->where('piso', $nuevoPisoParaActivar)
                                // ->where('consecutivo', $info->consecutivo)
                                ->get();

                            // Verificar que TODOS estén en estado validación = 1 y estado_validacion = 0
                            $todosPendientes = $vervalidacionPiso->every(function ($apt) {
                                return $apt->validacion == 1 && $apt->estado_validacion == 0;
                            });

                            if ($todosPendientes) {
                                DB::commit();
                                return response()->json([
                                    'success' => true,
                                    'message' => 'Todos los apartamentos del piso están validados y pendientes por habilitación.',
                                ]);
                            }
                            ProyectosDetalle::where('torre', $torre)
                                ->where('orden_proceso', $orden_proceso + 1)
                                ->where('proyecto_id', $proyecto->id)
                                ->where('piso', $nuevoPisoParaActivar)
                                ->where('estado', 0)
                                ->update([
                                    'estado' => 1,
                                    'fecha_habilitado' => now(),
                                ]);
                        } else {
                            // si no esta inicializado, se debe revisar si el proceso actual cumple con lso requisitos del minimo de pisos para que el proceso pueda ser incializado
                            if ($piso >= $pisosPorProceso) {
                                $nuevoProceso = $orden_proceso + 1;

                                // Se revisa si el prcceso siguiente que se piensa habilitar necesita validacion
                                $procesoActualDestapada = DB::connection('mysql')
                                    ->table('proyecto_detalle')
                                    ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
                                    ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', ['destapada']) // nombre en minúsculas
                                    ->where('proyecto_detalle.torre', $torre)
                                    ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                                    ->select('proyecto_detalle.*')
                                    ->first();

                                // si necesita validacion pero no esta validadio no hacer nada
                                if ($procesoActualDestapada->validacion == 1 && $procesoActualDestapada->estado_validacion == 0) {
                                    DB::commit();
                                    return response()->json([
                                        'success' => true,
                                    ]);
                                }

                                ProyectosDetalle::where('torre', $torre)
                                    ->where('orden_proceso', $nuevoProceso)
                                    ->where('proyecto_id', $proyecto->id)
                                    ->where('piso', 1)
                                    ->where('estado', 0)
                                    ->update([
                                        'estado' => 1,
                                        'fecha_habilitado' => now(),
                                    ]);
                            }
                        }



                        //------------------------------------------------------------------------------------------------------
                        // prolongacion

                        // Validación si el proceso prolongacion tiene el piso 1 activo
                        $InicioProcesoProlongacion = DB::connection('mysql')
                            ->table('proyecto_detalle')
                            ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
                            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', ['prolongacion']) // nombre en minúsculas
                            ->where('proyecto_detalle.torre', $torre)
                            ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                            ->where('proyecto_detalle.piso', 1)
                            ->select('proyecto_detalle.*')
                            ->get();

                        $confirmarInicioProcesoProlongacion = $InicioProcesoProlongacion->isNotEmpty() && $InicioProcesoProlongacion->every(fn($apt) => $apt->estado != 0);


                        //si ya fue iniciado, habilitamos piso de acuerdo al orden de la secuanecia
                        if ($confirmarInicioProcesoProlongacion == true) {
                            // calculo del piso que se debe validar
                            $nuevoPisoParaActivar = $piso - ($pisosPorProceso - 1);

                            // solo para procesos con validacion
                            $vervalidacionPiso = ProyectosDetalle::where('torre', $torre)
                                ->where('orden_proceso', $orden_proceso + 1)
                                ->where('proyecto_id', $proyecto->id)
                                ->where('piso', $nuevoPisoParaActivar)
                                // ->where('consecutivo', $info->consecutivo)
                                ->get();

                            // Verificar que TODOS estén en estado validación = 1 y estado_validacion = 0
                            $todosPendientes = $vervalidacionPiso->every(function ($apt) {
                                return $apt->validacion == 1 && $apt->estado_validacion == 0;
                            });

                            if ($todosPendientes) {
                                DB::commit();
                                return response()->json([
                                    'success' => true,
                                    'message' => 'Todos los apartamentos del piso están validados y pendientes por habilitación.',
                                ]);
                            }
                            ProyectosDetalle::where('torre', $torre)
                                ->where('orden_proceso', $orden_proceso + 1)
                                ->where('proyecto_id', $proyecto->id)
                                ->where('piso', $nuevoPisoParaActivar)
                                ->where('estado', 0)
                                ->update([
                                    'estado' => 1,
                                    'fecha_habilitado' => now(),
                                ]);
                        } else {
                            // si no esta inicializado, se debe revisar si el proceso actual cumple con lso requisitos del minimo de pisos para que el proceso pueda ser incializado
                            if ($piso >= $pisosPorProceso) {
                                $nuevoProceso = $orden_proceso + 1;

                                // Se revisa si el prcceso siguiente que se piensa habilitar necesita validacion
                                $procesoActualProlongacion = DB::connection('mysql')
                                    ->table('proyecto_detalle')
                                    ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
                                    ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', ['prolongacion']) // nombre en minúsculas
                                    ->where('proyecto_detalle.torre', $torre)
                                    ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                                    ->select('proyecto_detalle.*')
                                    ->first();

                                // si necesita validacion pero no esta validadio no hacer nada
                                if ($procesoActualProlongacion->validacion == 1 && $procesoActualProlongacion->estado_validacion == 0) {
                                    DB::commit();
                                    return response()->json([
                                        'success' => true,
                                    ]);
                                }



                                // Activar los apt del piso del siguiente proceso
                                ProyectosDetalle::where('torre', $torre)
                                    ->where('orden_proceso', $nuevoProceso)
                                    ->where('proyecto_id', $proyecto->id)
                                    ->where('piso', 1)
                                    ->where('estado', 0)
                                    ->update([
                                        'estado' => 1,
                                        'fecha_habilitado' => now(),
                                    ]);
                            }
                        }



                        break;

                    case 'destapada':

                        return response()->json([
                            'success' => true,
                            'data' => 'en destapada'
                        ]);

                        break;
                    case 'prolongacion':

                        return response()->json([
                            'success' => true,
                            'data' => 'en prolongacion'
                        ]);

                        break;
                    case 'alambrada':

                        return response()->json([
                            'success' => true,
                            'data' => 'en alambrada'
                        ]);

                        break;
                    case 'aparateada':

                        return response()->json([
                            'success' => true,
                            'data' => 'en aparateada'
                        ]);

                        break;
                    case 'pruebas':

                        return response()->json([
                            'success' => true,
                            'data' => 'en pruebas'
                        ]);

                        break;
                    case 'retie':

                        return response()->json([
                            'success' => true,
                            'data' => 'en retie'
                        ]);

                        break;
                    case 'ritel':

                        return response()->json([
                            'success' => true,
                            'data' => 'en ritel'
                        ]);

                        break;
                    case 'entrega':

                        return response()->json([
                            'success' => true,
                            'data' => 'en entrega'
                        ]);

                        break;





                    default:
                        return response()->json([
                            'success' => true,
                            'data' => 'ERROR, PROCESO NO EXISTENTE, COMUNICATE CON TI'
                        ]);
                        break;
                }
            }


            DB::commit();
            return response()->json([
                'success' => true,
                'data' => $info
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al confirmar apartamento',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
