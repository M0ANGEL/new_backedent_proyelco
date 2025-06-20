<?php

namespace App\Http\Controllers\Api\Proyectos;

use App\Http\Controllers\Controller;
use App\Models\CambioProcesoProyectos;
use App\Models\Clientes;
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


    // public function indexProgreso(Request $request)
    // {
    //     $proyectosDetalle = DB::connection('mysql')
    //         ->table('proyecto_detalle')
    //         ->leftJoin('users', 'proyecto_detalle.user_id', '=', 'users.id')
    //         ->leftJoin('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
    //         ->where('proyecto_detalle.proyecto_id', $request->id)
    //         ->select(
    //             'proyecto_detalle.torre',
    //             'proyecto_detalle.id',
    //             'proyecto_detalle.validacion',
    //             'proyecto_detalle.estado_validacion',
    //             'proyecto_detalle.consecutivo',
    //             'proyecto_detalle.orden_proceso',
    //             'proyecto_detalle.piso',
    //             'proyecto_detalle.apartamento',
    //             'proyecto_detalle.text_validacion',
    //             'proyecto_detalle.estado',
    //             'procesos_proyectos.nombre_proceso',
    //             'users.nombre as nombre'
    //         )
    //         ->get();

    //     $resultado = [];
    //     $torreResumen = []; // Nuevo arreglo para guardar totales por torre

    //     foreach ($proyectosDetalle as $item) {
    //         $torre = $item->torre;
    //         $orden_proceso = $item->orden_proceso;
    //         $nombre_proceso = $item->nombre_proceso;
    //         $text_validacion = $item->text_validacion;
    //         $validacion = $item->validacion;
    //         $estado_validacion = $item->estado_validacion;
    //         $consecutivo = $item->consecutivo;
    //         $piso = $item->piso;

    //         // Inicializar torre en resultado
    //         if (!isset($resultado[$torre])) {
    //             $resultado[$torre] = [];
    //         }

    //         // Inicializar resumen por torre
    //         if (!isset($torreResumen[$torre])) {
    //             $torreResumen[$torre] = [
    //                 'total_atraso' => 0,
    //                 'total_realizados' => 0,
    //                 'porcentaje_atraso' => 0,
    //             ];
    //         }

    //         // Inicializar proceso por torre
    //         if (!isset($resultado[$torre][$orden_proceso])) {
    //             $resultado[$torre][$orden_proceso] = [
    //                 'nombre_proceso' => $nombre_proceso,
    //                 'text_validacion' => $text_validacion,
    //                 'estado_validacion' => $estado_validacion,
    //                 'validacion' => $validacion,
    //                 'pisos' => [],
    //                 'total_apartamentos' => 0,
    //                 'apartamentos_atraso' => 0,
    //                 'apartamentos_realizados' => 0,
    //                 'porcentaje_atraso' => 0,
    //                 'porcentaje_avance' => 0,
    //             ];
    //         }

    //         if (!isset($resultado[$torre][$orden_proceso]['pisos'][$piso])) {
    //             $resultado[$torre][$orden_proceso]['pisos'][$piso] = [];
    //         }

    //         // Agregar apartamento
    //         $resultado[$torre][$orden_proceso]['pisos'][$piso][] = [
    //             'id' => $item->id,
    //             'apartamento' => $item->apartamento,
    //             'consecutivo' => $consecutivo,
    //             'estado' => $item->estado,
    //         ];

    //         // Contar total apartamentos
    //         $resultado[$torre][$orden_proceso]['total_apartamentos'] += 1;

    //         // Contar apartamentos en atraso (estado = 1)
    //         if ($item->estado == 1) {
    //             $resultado[$torre][$orden_proceso]['apartamentos_atraso'] += 1;
    //             $torreResumen[$torre]['total_atraso'] += 1; // Sumar al total de la torre
    //         }

    //         // Contar apartamentos realizados (estado = 2)
    //         if ($item->estado == 2) {
    //             $resultado[$torre][$orden_proceso]['apartamentos_realizados'] += 1;
    //             $torreResumen[$torre]['total_realizados'] += 1; // Sumar al total de la torre
    //         }
    //     }

    //     // Calcular porcentajes por proceso
    //     foreach ($resultado as $torre => $procesos) {
    //         foreach ($procesos as $orden_proceso => $proceso) {

    //             // Omitir el cálculo si el proceso es 1
    //             if ($orden_proceso == 1) {
    //                 $resultado[$torre][$orden_proceso]['porcentaje_atraso'] = 0;
    //                 $resultado[$torre][$orden_proceso]['porcentaje_avance'] = 0;
    //                 continue; // Saltar al siguiente proceso
    //             }
    //             $total_atraso = $proceso['apartamentos_atraso'];
    //             $total_realizados = $proceso['apartamentos_realizados'];
    //             $denominador = $total_atraso + $total_realizados;

    //             // Porcentaje de atraso por proceso
    //             $porcentaje_atraso = $denominador > 0 ? ($total_atraso / $denominador) * 100 : 0;

    //             // Porcentaje de avance por proceso
    //             $porcentaje_avance = $proceso['total_apartamentos'] > 0 ? ($total_realizados / $proceso['total_apartamentos']) * 100 : 0;

    //             $resultado[$torre][$orden_proceso]['porcentaje_atraso'] = round($porcentaje_atraso, 2);
    //             $resultado[$torre][$orden_proceso]['porcentaje_avance'] = round($porcentaje_avance, 2);
    //         }
    //     }

    //     // Calcular porcentaje de atraso por torre
    //     foreach ($torreResumen as $torre => $datos) {
    //         $total_atraso = $datos['total_atraso'];
    //         $total_realizados = $datos['total_realizados'];
    //         $denominador = $total_atraso + $total_realizados;

    //         $porcentaje_atraso = $denominador > 0 ? ($total_atraso / $denominador) * 100 : 0;

    //         $torreResumen[$torre]['porcentaje_atraso'] = round($porcentaje_atraso, 2);
    //     }

    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $resultado,
    //         'torreResumen' => $torreResumen // Aquí te envío el atraso por torre
    //     ]);
    // }

    public function indexProgreso(Request $request)
    {
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

            // Inicializar torre en resultado
            if (!isset($resultado[$torre])) {
                $resultado[$torre] = [];
            }

            // Inicializar resumen por torre
            if (!isset($torreResumen[$torre])) {
                $torreResumen[$torre] = [
                    'total_atraso' => 0,
                    'total_realizados' => 0,
                    'porcentaje_atraso' => 0,
                ];
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
                // Contar apartamentos en atraso (estado = 1)
                if ($item->estado == 1) {
                    $resultado[$torre][$orden_proceso]['apartamentos_atraso'] += 1;
                    $torreResumen[$torre]['total_atraso'] += 1; // Sumar al total de la torre
                }

                // Contar apartamentos realizados (estado = 2)
                if ($item->estado == 2) {
                    $resultado[$torre][$orden_proceso]['apartamentos_realizados'] += 1;
                    $torreResumen[$torre]['total_realizados'] += 1; // Sumar al total de la torre
                }
            } else {
                // 👉 Si es proceso 1, solo sumar en el detalle pero no en el resumen de torre
                if ($item->estado == 1) {
                    $resultado[$torre][$orden_proceso]['apartamentos_atraso'] += 1;
                }

                if ($item->estado == 2) {
                    $resultado[$torre][$orden_proceso]['apartamentos_realizados'] += 1;
                }
            }
        }

        // Calcular porcentajes por proceso
        foreach ($resultado as $torre => $procesos) {
            foreach ($procesos as $orden_proceso => $proceso) {

                // 👉 Si es proceso 1, poner porcentaje en 0
                if ($orden_proceso == 1) {
                    $resultado[$torre][$orden_proceso]['porcentaje_atraso'] = 0;
                    $resultado[$torre][$orden_proceso]['porcentaje_avance'] = 0;
                    continue;
                }

                $total_atraso = $proceso['apartamentos_atraso'];
                $total_realizados = $proceso['apartamentos_realizados'];
                $denominador = $total_atraso + $total_realizados;

                // Porcentaje de atraso por proceso
                $porcentaje_atraso = $denominador > 0 ? ($total_atraso / $denominador) * 100 : 0;

                // Porcentaje de avance por proceso
                $porcentaje_avance = $proceso['total_apartamentos'] > 0 ? ($total_realizados / $proceso['total_apartamentos']) * 100 : 0;

                $resultado[$torre][$orden_proceso]['porcentaje_atraso'] = round($porcentaje_atraso, 2);
                $resultado[$torre][$orden_proceso]['porcentaje_avance'] = round($porcentaje_avance, 2);
            }
        }

        // Calcular porcentaje de atraso por torre
        foreach ($torreResumen as $torre => $datos) {
            $total_atraso = $datos['total_atraso'];
            $total_realizados = $datos['total_realizados'];
            $denominador = $total_atraso + $total_realizados;

            $porcentaje_atraso = $denominador > 0 ? ($total_atraso / $denominador) * 100 : 0;

            $torreResumen[$torre]['porcentaje_atraso'] = round($porcentaje_atraso, 2);
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
                ->where('procesos_proyectos_id', 1)
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


    public function validarProceso(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'torre' => 'required|string',
                'proyecto' => 'required',
                'orden_proceso' => 'required|integer|min:1',
            ]);

            // se busca el numero para cambio de proceso, del proceso anterior
            $CambioProcesoProyectos = CambioProcesoProyectos::where('proyecto_id', $request->proyecto)
                ->where('proceso', $request->orden_proceso)->first();

            $pisosRequeridos = $CambioProcesoProyectos->numero;

            $pisosCompletadosProcesoAnterior = [];

            // Verificar procesos anteriores solo si no es el primer proceso
            if ($request->orden_proceso > 1) {
                $procesoAnteriorNum = $request->orden_proceso - 1;

                // 1. Verificar que el proceso anterior existe
                $existeProcesoAnterior = ProyectosDetalle::where('torre', $request->torre)
                    ->where('orden_proceso', $procesoAnteriorNum)
                    ->where('proyecto_id', $request->proyecto)
                    ->exists();

                if (!$existeProcesoAnterior) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No se puede validar este proceso porque el proceso anterior no existe',
                        'details' => [
                            'proceso_faltante' => $procesoAnteriorNum
                        ]
                    ], 400);
                }


                // // 2. Verificar que el proceso anterior completó los pisos requeridos
                // $pisosCompletadosProcesoAnterior = ProyectosDetalle::where('torre', $request->torre)
                //     ->where('orden_proceso', $procesoAnteriorNum)
                //     ->where('proyecto_id', $request->proyecto)
                //     ->where('estado', 2) // Solo pisos confirmados
                //     ->select('piso')
                //     ->distinct()
                //     ->orderBy('piso')
                //     ->pluck('piso')
                //     ->toArray();

                $InicioProceso = ProyectosDetalle::where('torre', $request->torre)
                    ->where('orden_proceso', $procesoAnteriorNum)
                    ->where('proyecto_id', $request->proyecto)
                    ->whereIn('piso', range(1, $pisosRequeridos))
                    ->get();

                $confirmarInicioProceso = $InicioProceso->isNotEmpty() && $InicioProceso->every(fn($apt) => $apt->estado == "2");


                if ($confirmarInicioProceso === false) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No se puede validar este proceso porque el Proceso ' . $procesoAnteriorNum . ' solo ha completado ' . count($pisosCompletadosProcesoAnterior) . ' de ' . $pisosRequeridos . ' pisos requeridos',
                    ], 400);
                }

                // // 3. Verificar específicamente el último piso requerido
                // $ultimoPisoRequerido = min(max($pisosCompletadosProcesoAnterior), $pisosRequeridos);
                // $aptosUltimoPisoRequerido = ProyectosDetalle::where('torre', $request->torre)
                //     ->where('orden_proceso', $procesoAnteriorNum)
                //     ->where('proyecto_id', $request->proyecto)
                //     ->where('piso', $ultimoPisoRequerido)
                //     ->get();

                // $todosConfirmados = $aptosUltimoPisoRequerido->every(fn($apt) => $apt->estado == 2);

                // if (!$todosConfirmados) {
                //     return response()->json([
                //         'status' => 'error',
                //         'message' => 'No se puede validar este proceso porque el Proceso ' . $procesoAnteriorNum . ' no ha completado el Piso ' . $ultimoPisoRequerido,
                //     ], 400);
                // }
            }

            // Marcar proceso actual como validado
            ProyectosDetalle::where('torre', $request->torre)
                ->where('orden_proceso', $request->orden_proceso)
                ->where('proyecto_id', $request->proyecto)
                ->update([
                    'estado_validacion' => 1,
                    'fecha_validacion' => now(),
                    'user_id' => Auth::id()
                ]);

            // Activar pisos en el proceso actual
            if ($request->orden_proceso > 1) {
                // foreach ($pisosCompletadosProcesoAnterior as $piso) {
                ProyectosDetalle::where('torre', $request->torre)
                    ->where('orden_proceso', $request->orden_proceso)
                    ->where('proyecto_id', $request->proyecto)
                    ->where('piso', 1)
                    ->where('estado', 0)
                    ->update(['estado' => 1]);
                // }
            } else {
                // Si es el primer proceso, solo activar piso 1
                ProyectosDetalle::where('torre', $request->torre)
                    ->where('orden_proceso', $request->orden_proceso)
                    ->where('proyecto_id', $request->proyecto)
                    ->where('piso', 1)
                    ->where('estado', 0)
                    ->update(['estado' => 1]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Proceso validado exitosamente'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al validar proceso',
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
            //buscamos los cambio de piso para proceso
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
            $todosConfirmados = $aptosDelPiso->every(fn($apt) => $apt->estado == 2);

            //si estan confirmado todo los apartamentos de ese piso sigue la logica, del resto termina aqui.
            if ($todosConfirmados) {

                if ($orden_proceso === 1) {
                    // Si es el primer proceso (no depende de ningún proceso anterior)
                    // Habilita el piso siguiente para el primer proceso (orden_proceso = 1)
                    ProyectosDetalle::where('torre', $torre)
                        ->where('orden_proceso', $orden_proceso)
                        ->where('piso', $piso + 1)
                        ->where('proyecto_id', $proyecto->id)
                        ->where('estado', 0)
                        ->update(['estado' => 1, 'fecha_habilitado' => now()]);

                    // Validación que el proyecto siguiente ya esta activo
                    $InicioProceso = ProyectosDetalle::where('torre', $torre)
                        ->where('orden_proceso', $orden_proceso + 1)
                        ->where('proyecto_id', $proyecto->id)
                        ->where('piso', 1)
                        ->get();

                    $confirmarInicioProceso = $InicioProceso->isNotEmpty() && $InicioProceso->every(fn($apt) => $apt->estado != 0);

                    //verificar si aun no hay ningun piso iniciado del proceso siguiente
                    if ($confirmarInicioProceso == true) {
                        $nuevoPisoParaActivar = $piso - ($pisosPorProceso - 1);
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
                        // Verificar si el proceso siguiente ya puede comenzar
                        if ($piso >= $pisosPorProceso) {
                            $nuevoProceso = $orden_proceso + 1;

                            // Validación para ver si tiene autorizacion
                            $procesoActual = ProyectosDetalle::where('torre', $torre)
                                ->where('orden_proceso', $nuevoProceso)
                                ->where('proyecto_id', $proyecto->id)
                                ->first();

                            if ($procesoActual->validacion == 1 && $procesoActual->estado_validacion == 0) {
                                DB::commit();
                                return response()->json([
                                    'success' => true,
                                ]);
                            }

                            $existeSiguienteProceso = ProyectosDetalle::where('torre', $torre)
                                ->where('orden_proceso', $nuevoProceso)
                                ->where('proyecto_id', $proyecto->id)
                                ->exists();

                            // Validación que la cantidad de pisos este activa para activar otro proceso
                            $InicioProceso = ProyectosDetalle::where('torre', $torre)
                                ->where('orden_proceso', $orden_proceso)
                                ->where('proyecto_id', $proyecto->id)
                                ->where('piso', $piso - 1)
                                ->get();

                            $confirmarInicioProceso = $InicioProceso->isNotEmpty() && $InicioProceso->every(fn($apt) => $apt->estado != 0);


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
                            $InicioProceso = ProyectosDetalle::where('torre', $torre)
                                ->where('orden_proceso', $orden_proceso)
                                ->where('proyecto_id', $proyecto->id)
                                ->whereIn('piso', range(1, $pisosPorProceso))
                                ->get();

                            $confirmarInicioProceso = $InicioProceso->isNotEmpty() && $InicioProceso->every(fn($apt) => $apt->estado == "2");


                            //si el proceso actual cumple con los pisos re pisosPorPrceos en estado 2, actiar
                            if ($confirmarInicioProceso == true) {
                                // Verificar si el proceso siguiente ya puede comenzar
                                $nuevoProceso = $orden_proceso + 1;

                                $existeSiguienteProceso = ProyectosDetalle::where('torre', $torre)
                                    ->where('orden_proceso', $nuevoProceso)
                                    ->where('proyecto_id', $proyecto->id)
                                    ->exists();

                                // Validación manual
                                $procesoActual = ProyectosDetalle::where('torre', $torre)
                                    ->where('orden_proceso', $orden_proceso + 1)
                                    ->where('proyecto_id', $proyecto->id)
                                    ->first();

                                if ($procesoActual->validacion == 1 && $procesoActual->estado_validacion == 0) {
                                    DB::commit();

                                    return response()->json([
                                        'success' => true,
                                    ]);
                                } else {

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
    //     $proyectoAptActivar = Proyectos::where('id', $proyectoId)->select('activador_pordia_apt')->first();

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

    //         // Obtener el primer registro pendiente y su piso
    //         $primerPendiente = $pendientesSiguiente->first();
    //         $piso = $primerPendiente->piso;
    //         $torre = $primerPendiente->torre;

    //         ProyectosDetalle::where('proyecto_id', $proyectoId)
    //             ->where('orden_proceso', $procesoSiguiente)
    //             ->where('piso', $piso)
    //             ->where('torre', $torre)
    //             ->where('estado', 0)
    //             ->update([
    //                 'fecha_habilitado' => $hoy,
    //                 'estado' => 1,
    //             ]);

    //         // info("Piso $piso habilitado en torre $torre para proceso $procesoSiguiente");

    //         break; // Solo habilitar un piso por día
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Validacion de apartamentos que requieren actualizacion por día, realizada',
    //     ]);
    // }

    public function activacionXDia(Request $request)
    {
        $proyectoId = $request->proyecto_id;

        // Cantidad de apartamentos a activar por día
        $proyectoAptActivar = Proyectos::where('id', $proyectoId)
            ->select('activador_pordia_apt')
            ->first();

        $cantidadActivar = $proyectoAptActivar->activador_pordia_apt;

        $procesos = ProyectosDetalle::where('proyecto_id', $proyectoId)
            ->select('orden_proceso')
            ->distinct()
            ->orderBy('orden_proceso')
            ->pluck('orden_proceso')
            ->values();

        for ($i = 0; $i < $procesos->count() - 1; $i++) {
            $procesoActual = $procesos[$i];
            $procesoSiguiente = $procesos[$i + 1];

            $registrosActual = ProyectosDetalle::where('proyecto_id', $proyectoId)
                ->where('orden_proceso', $procesoActual)
                ->where('torre', $request->torre)
                ->get();

            $completado = $registrosActual->every(fn($r) => $r->estado == "2");

            if (!$completado) {
                continue;
            }

            $pendientesSiguiente = ProyectosDetalle::where('proyecto_id', $proyectoId)
                ->where('orden_proceso', $procesoSiguiente)
                ->where('estado', 0)
                ->where('torre', $request->torre)
                ->orderBy('consecutivo')
                ->get();

            // Validación manual
            $procesoActual = ProyectosDetalle::where('torre', $request->torre)
                ->where('orden_proceso', $procesoSiguiente)
                ->where('proyecto_id', $proyectoId)
                ->first();

            if ($procesoActual->validacion == 1 && $procesoActual->estado_validacion == 0) {
                continue;
            }

            $pendientesSiguiente2 = ProyectosDetalle::where('proyecto_id', $proyectoId)
                ->where('orden_proceso', $procesoSiguiente)
                ->whereIn('estado', [1, 2])
                ->where('torre', $request->torre)
                ->orderBy('consecutivo')
                ->get();

            if ($pendientesSiguiente->isEmpty()) {
                continue;
            }

            $hoy = Carbon::now()->toDateString();
            $ya_habilitado_hoy = $pendientesSiguiente2->contains('fecha_habilitado', $hoy);

            if ($ya_habilitado_hoy) {
                continue;
            }

            // Obtener los primeros N apartamentos pendientes
            $apartamentosPorActivar = ProyectosDetalle::where('proyecto_id', $proyectoId)
                ->where('orden_proceso', $procesoSiguiente)
                ->where('estado', 0)
                ->where('torre', $request->torre)
                ->orderBy('consecutivo')
                ->limit($cantidadActivar) // 👉 Solo activar N apartamentos por día
                ->get();

            foreach ($apartamentosPorActivar as $apartamento) {
                $apartamento->update([
                    'fecha_habilitado' => $hoy,
                    'estado' => 1,
                ]);
            }

            // Solo habilitar N apartamentos por día, por eso hacemos break aquí
            break;
        }

        return response()->json([
            'success' => true,
            'message' => 'Validacion de apartamentos que requieren actualizacion por día, realizada',
        ]);
    }
}
