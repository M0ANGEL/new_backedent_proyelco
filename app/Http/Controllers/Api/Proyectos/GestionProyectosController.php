<?php

namespace App\Http\Controllers\Api\Proyectos;

use App\Exports\InformeProyectoExport;
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
use Maatwebsite\Excel\Facades\Excel;

class GestionProyectosController extends Controller
{
    public function index()
    {
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
                $userId = Auth::id();
                $query->whereRaw("JSON_CONTAINS(proyecto.encargado_id, '\"$userId\"')")
                    ->orWhereRaw("JSON_CONTAINS(proyecto.ingeniero_id, '\"$userId\"')");
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

            foreach ($proyectosGestion as $proyecto) {
                $AVANCE = DB::connection('mysql')
                    ->table('proyecto_detalle')
                    ->where('proyecto_id', $proyecto->id)
                    ->get();

                $totalEjecutando = $AVANCE->where('estado', 1)->count();
                $totalTerminado = $AVANCE->where('estado', 2)->count();
                $total = $totalEjecutando + $totalTerminado;

                // Cálculo del avance (nuevo)
                $totalApartamentos = $AVANCE->count();
                $apartamentosRealizados = $totalTerminado;

                $avance = $totalApartamentos > 0 ? ($apartamentosRealizados / $totalApartamentos) * 100 : 0;
                $proyecto->avance = round($avance, 2);
            }
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


    // public function indexProgreso(Request $request)
    // {
    //     // 1. CONFIGURACIÓN DE PROCESOS - Obtiene cuántos pisos se requieren completar para cada proceso
    //     $procesosConfig = DB::table('proyecto_detalle')
    //         ->join('cambio_procesos_x_proyecto', function ($join) {
    //             $join->on('cambio_procesos_x_proyecto.proyecto_id', '=', 'proyecto_detalle.proyecto_id')
    //                 ->on('cambio_procesos_x_proyecto.proceso', '=', 'proyecto_detalle.procesos_proyectos_id');
    //         })
    //         ->where('proyecto_detalle.proyecto_id', $request->id)
    //         ->select(
    //             'proyecto_detalle.orden_proceso',
    //             'proyecto_detalle.procesos_proyectos_id',
    //             'proyecto_detalle.proyecto_id',
    //             'cambio_procesos_x_proyecto.numero as pisos_requeridos'
    //         )
    //         ->get()
    //         ->keyBy('orden_proceso');


    //     // 2. NOMBRES DE TORRES - Obtiene los nombres personalizados de las torres
    //     $torresConNombre = DB::table('nombre_xtore')
    //         ->where('proyecto_id', $request->id)
    //         ->pluck('nombre_torre', 'torre')
    //         ->toArray();

    //     // 3. DATOS DEL PROYECTO - Obtiene el estado actual de todos los apartamentos
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
    //         ->orderBy('proyecto_detalle.orden_proceso')
    //         ->orderBy('proyecto_detalle.piso')
    //         ->orderBy('proyecto_detalle.apartamento')
    //         ->get();
            

    //         info($proyectosDetalle);

    //     $resultado = []; // Almacenará todos los datos estructurados
    //     $torreResumen = []; // Resumen por torre

    //     // 4. PROCESAR CADA REGISTRO - Organiza la información por torre, proceso, piso y apartamento
    //     foreach ($proyectosDetalle as $item) {
    //         $torre = $item->torre;
    //         $orden_proceso = $item->orden_proceso;
    //         $piso = $item->piso;

    //         // Inicializar estructuras si no existen
    //         if (!isset($resultado[$torre])) {
    //             $resultado[$torre] = [];
    //         }
    //         if (!isset($torreResumen[$torre])) {
    //             $torreResumen[$torre] = [
    //                 'nombre_torre' => $torresConNombre[$torre] ?? $torre,
    //                 'total_atraso' => 0,
    //                 'total_realizados' => 0,
    //                 'porcentaje_atraso' => 0,
    //                 'porcentaje_avance' => 0,
    //                 'serial_avance' => '0/0',
    //                 'pisos_unicos' => [] // Para contar pisos únicos
    //             ];
    //         }

    //         // Registrar pisos únicos por torre
    //         if (!in_array($piso, $torreResumen[$torre]['pisos_unicos'])) {
    //             $torreResumen[$torre]['pisos_unicos'][] = $piso;
    //         }

    //         // Inicializar proceso si no existe
    //         if (!isset($resultado[$torre][$orden_proceso])) {
    //             $resultado[$torre][$orden_proceso] = [
    //                 'nombre_proceso' => $item->nombre_proceso,
    //                 'text_validacion' => $item->text_validacion,
    //                 'estado_validacion' => $item->estado_validacion,
    //                 'validacion' => $item->validacion,
    //                 'pisos' => [],
    //                 'total_apartamentos' => 0,
    //                 'apartamentos_atraso' => 0,
    //                 'apartamentos_realizados' => 0,
    //                 'porcentaje_atraso' => 0,
    //                 'porcentaje_avance' => 0,
    //                 'pisos_completados' => 0,
    //                 'pisos_requeridos' => $procesosConfig[$orden_proceso]->pisos_requeridos ?? 0
    //             ];
    //         }

    //         // Inicializar piso si no existe
    //         if (!isset($resultado[$torre][$orden_proceso]['pisos'][$piso])) {
    //             $resultado[$torre][$orden_proceso]['pisos'][$piso] = [];
    //         }

    //         // 5. DETERMINAR ESTADO BLANCO (EB) - Solo para procesos dependientes (no Fundida)
    //         $eb = false;
    //         if ($orden_proceso != 1 && $item->estado == 0) {
    //             $eb = $this->determinarEstadoBlanco(
    //                 $resultado,
    //                 $torre,
    //                 $orden_proceso,
    //                 $piso,
    //                 $item->apartamento,
    //                 $procesosConfig
    //             );
    //         }

    //         // 6. AGREGAR APARTAMENTO AL RESULTADO
    //         $resultado[$torre][$orden_proceso]['pisos'][$piso][] = [
    //             'id' => $item->id,
    //             'apartamento' => $item->apartamento,
    //             'consecutivo' => $item->consecutivo,
    //             'estado' => $item->estado,
    //             'eb' => $eb, // Estado Blanco (depende de procesos anteriores)
    //         ];

    //         // 7. ACTUALIZAR CONTADORES
    //         $this->actualizarContadores($resultado, $torreResumen, $torre, $orden_proceso, $item->estado);

    //         // 8. VERIFICAR SI TODO EL PISO ESTÁ COMPLETO
    //         $this->verificarPisoCompleto($resultado, $torre, $orden_proceso, $piso);
    //     }

    //     // 9. CALCULAR PORCENTAJES FINALES
    //     $this->calcularPorcentajes($resultado, $torreResumen);

    //     // 10. RETORNAR RESULTADO FINAL
    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $resultado,
    //         'torreResumen' => $torreResumen
    //     ]);
    // }


    // private function determinarEstadoBlanco($resultado, $torre, $orden_proceso, $piso, $apartamento, $procesosConfig)
    // {
    //     // Pisos requeridos para habilitar este proceso
    //     $pisosRequeridos = $procesosConfig[$orden_proceso]->pisos_requeridos ?? 0;
    //     /* 
    //      * LÓGICA DE DEPENDENCIAS ENTRE PROCESOS:
    //      * 
    //      * Ejemplo para torre de 5 pisos con cambio cada 2 pisos:
    //      * - Si Fundida está en piso 4 (completada):
    //      *   - Destapada y Prolongación deben estar habilitadas en pisos 1, 2 y 3
    //      *   - Alambrada debe estar habilitada en pisos 1 y 2
    //      *   - Si no están completos los procesos anteriores, marca EB
    //      */

    //     // PROCESOS 2 Y 3 (DESTAPADA Y PROLONGACIÓN) - DEPENDEN DE FUNDIDA
    //     if (in_array($orden_proceso, [2, 3])) {
    //         // Habilita si el piso está completo en Fundida (proceso 1)
    //         return $this->verificarPisoCompletoEnProceso($resultado, $torre, 1, $piso);
    //     }

    //     // PROCESO 4 (ALAMBRADA) - DEPENDE DE DESTAPADA Y PROLONGACIÓN
    //     if ($orden_proceso == 4) {
    //         // Verifica que se hayan completado los pisos mínimos requeridos en ambos procesos
    //         $cumpleMinPisos = $this->verificarMinPisosCompletados($resultado, $torre, 2, $pisosRequeridos) &&
    //             $this->verificarMinPisosCompletados($resultado, $torre, 3, $pisosRequeridos);

    //         // Verifica que este piso específico esté completo en ambos procesos
    //         $pisoCompletoEnDependencias = $this->verificarPisoCompletoEnProceso($resultado, $torre, 2, $piso) &&
    //             $this->verificarPisoCompletoEnProceso($resultado, $torre, 3, $piso);

    //         // Habilita si se cumplen ambos: pisos mínimos y este piso completo
    //         return $cumpleMinPisos && $pisoCompletoEnDependencias;
    //     }

    //     // PROCESO 5 (APARATEADA) - DEPENDE DE ALAMBRADA
    //     if ($orden_proceso == 5) {
    //         return $this->verificarPisoCompletoEnProceso($resultado, $torre, 4, $piso);
    //     }

    //     // PROCESO 6 (APARATEADA FASE 2) - DEPENDE DE APARATEADA
    //     if ($orden_proceso == 6) {
    //         return $this->verificarPisoCompletoEnProceso($resultado, $torre, 5, $piso);
    //     }

    //     // PROCESO 7 (PRUEBAS) - DEPENDE DE APARATEADA O APARATEADA FASE 2
    //     if ($orden_proceso == 7) {
    //         if (isset($resultado[$torre][6])) {
    //             return $this->verificarPisoCompletoEnProceso($resultado, $torre, 6, $piso);
    //         }
    //         return $this->verificarPisoCompletoEnProceso($resultado, $torre, 5, $piso);
    //     }

    //     // PROCESOS 8 Y 9 (RETIE Y RITEL) - DEPENDEN DE PRUEBAS
    //     if (in_array($orden_proceso, [8, 9])) {
    //         return $this->verificarPisoCompletoEnProceso($resultado, $torre, 7, $piso);
    //     }

    //     // PROCESO 10 (ENTREGA) - DEPENDE DE RETIE Y RITEL
    //     if ($orden_proceso == 10) {
    //         $retieCompletado = $this->verificarApartamentoCompletoEnProceso($resultado, $torre, 8, $piso, $apartamento);
    //         $ritelCompletado = $this->verificarApartamentoCompletoEnProceso($resultado, $torre, 9, $piso, $apartamento);
    //         return $retieCompletado && $ritelCompletado;
    //     }

    //     return false;
    // }

    // //Verifica si todo un piso está completo (estado=2) para un proceso
    // private function verificarPisoCompletoEnProceso($resultado, $torre, $ordenProceso, $piso)
    // {
    //     if (!isset($resultado[$torre][$ordenProceso]['pisos'][$piso])) {
    //         return false;
    //     }

    //     foreach ($resultado[$torre][$ordenProceso]['pisos'][$piso] as $apt) {
    //         if ($apt['estado'] != 2 ) { // 2 = Completado
    //             return false;
    //         }
    //     }
    //     return true;
    // }

    // //Verifica si un apartamento específico está completo (estado=2) en un proceso
    // private function verificarApartamentoCompletoEnProceso($resultado, $torre, $ordenProceso, $piso, $apartamento)
    // {
    //     if (!isset($resultado[$torre][$ordenProceso]['pisos'][$piso])) {
    //         return false;
    //     }

    //     foreach ($resultado[$torre][$ordenProceso]['pisos'][$piso] as $apt) {
    //         if ($apt['apartamento'] == $apartamento) {
    //             return $apt['estado'] == 2; // 2 = Completado
    //         }
    //     }

    //     return false;
    // }

    // //Verifica si se han completado los pisos mínimos requeridos para un proceso
    // private function verificarMinPisosCompletados($resultado, $torre, $ordenProceso, $minPisos)
    // {
    //     return isset($resultado[$torre][$ordenProceso]['pisos_completados']) &&
    //         $resultado[$torre][$ordenProceso]['pisos_completados'] >= $minPisos;
    // }

    // //Actualiza los contadores de realizados y atrasos
    // private function actualizarContadores(&$resultado, &$torreResumen, $torre, $orden_proceso, $estado)
    // {
    //     $resultado[$torre][$orden_proceso]['total_apartamentos']++;

    //     if ($estado == 1) { // 1 = Atraso
    //         $resultado[$torre][$orden_proceso]['apartamentos_atraso']++;
    //         if ($orden_proceso != 1) { // No contar Fundida en resumen general
    //             $torreResumen[$torre]['total_atraso']++;
    //         }
    //     } elseif ($estado == 2) { // 2 = Completado
    //         $resultado[$torre][$orden_proceso]['apartamentos_realizados']++;
    //         if ($orden_proceso != 1) { // No contar Fundida en resumen general
    //             $torreResumen[$torre]['total_realizados']++;
    //         }
    //     }
    // }

    // //Verifica si todo un piso está completo y actualiza el contador
    // private function verificarPisoCompleto(&$resultado, $torre, $orden_proceso, $piso)
    // {
    //     if (!isset($resultado[$torre][$orden_proceso]['pisos'][$piso])) {
    //         return;
    //     }

    //     $completo = true;
    //     foreach ($resultado[$torre][$orden_proceso]['pisos'][$piso] as $apt) {
    //         if ($apt['estado'] != 2) { // 2 = Completado
    //             $completo = false;
    //             break;
    //         }
    //     }

    //     if ($completo) {
    //         $resultado[$torre][$orden_proceso]['pisos_completados']++;
    //     }
    // }

    // //Calcula porcentajes de avance y atraso para procesos y torres
    // private function calcularPorcentajes(&$resultado, &$torreResumen)
    // {
    //     // Porcentajes por proceso
    //     foreach ($resultado as $torre => &$procesos) {
    //         foreach ($procesos as $orden_proceso => &$proceso) {
    //             if ($orden_proceso === 'nombre_torre') continue;

    //             // Proceso Fundida (1) no lleva porcentajes
    //             if ($orden_proceso == 1) {
    //                 $proceso['porcentaje_atraso'] = 0;
    //                 $proceso['porcentaje_avance'] = 0;
    //                 continue;
    //             }

    //             $total_atraso = $proceso['apartamentos_atraso'];
    //             $total_realizados = $proceso['apartamentos_realizados'];
    //             $denominador = $total_atraso + $total_realizados;

    //             // % Atraso = (Atrasos / Total iniciados) * 100
    //             $proceso['porcentaje_atraso'] = $denominador > 0 ? round(($total_atraso / $denominador) * 100, 2) : 0;

    //             // % Avance = (Realizados / Total apartamentos) * 100
    //             $proceso['porcentaje_avance'] = $proceso['total_apartamentos'] > 0 ?
    //                 round(($total_realizados / $proceso['total_apartamentos']) * 100, 2) : 0;
    //         }
    //     }

    //     // Porcentajes por torre
    //     foreach ($torreResumen as $torre => &$datos) {
    //         $total_atraso = $datos['total_atraso'];
    //         $total_realizados = $datos['total_realizados'];
    //         $denominador = $total_atraso + $total_realizados;

    //         $datos['porcentaje_atraso'] = $denominador > 0 ? round(($total_atraso / $denominador) * 100, 2) : 0;
    //         $datos['porcentaje_avance'] = $denominador > 0 ? round(($total_realizados / $denominador) * 100, 2) : 0;
    //         $datos['serial_avance'] = $total_realizados . '/' . $denominador;
    //         $datos['total_pisos'] = count($datos['pisos_unicos']);

    //         unset($datos['pisos_unicos']); // Eliminar campo auxiliar
    //     }
    // }




















































    //------------------------------------------------------------------------------------
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
    //     DB::beginTransaction();

    //     try {
    //         // Validar datos de entrada
    //         $request->validate([
    //             'torre' => 'required|string',
    //             'proyecto' => 'required',
    //             'orden_proceso' => 'required|integer|min:1',
    //             'piso' => 'required|integer',
    //         ]);

    //         $torre = $request->torre;
    //         $proyecto = (int) $request->proyecto;
    //         $ordenProceso = (int) $request->orden_proceso;
    //         $pisoActual = (int) $request->piso;

    //         // Buscar información del piso actual
    //         $pisosPrevios = ProyectosDetalle::where('torre', $torre)
    //             ->where('orden_proceso', $ordenProceso)
    //             ->where('proyecto_id', $proyecto)
    //             ->where('piso', $pisoActual)
    //             ->first();

    //         // Proyecto padre
    //         $proyectoPadre = Proyectos::find($proyecto);
    //         $aptMinimos = $proyectoPadre->minimoApt;

    //         // Si ya está validado, no hacer nada
    //         if ($pisosPrevios->validacion === 1 && $pisosPrevios->estado_validacion === 1) {
    //             DB::commit();
    //             return response()->json([
    //                 'success' => "Ya validado co: FP-6325.22",
    //             ]);
    //         }

    //         // Obtener configuración del proceso
    //         $configProceso = CambioProcesoProyectos::where('proyecto_id', $proyecto)
    //             ->where('proceso', $ordenProceso)
    //             ->first();

    //         if (!$configProceso) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Configuración de proceso no encontrada.',
    //             ], 400);
    //         }

    //         $pisosRequeridos = (int) $configProceso->numero;

    //         if ($ordenProceso > 1) {
    //             $procesoAnterior = $ordenProceso - 1;

    //             $existeProcesoAnterior = ProyectosDetalle::where('torre', $torre)
    //                 ->where('orden_proceso', $procesoAnterior)
    //                 ->where('proyecto_id', $proyecto)
    //                 ->get();

    //             if ($existeProcesoAnterior->isEmpty()) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'No se puede validar porque el proceso anterior no existe.',
    //                     'details' => ['proceso_faltante' => $procesoAnterior]
    //                 ], 400);
    //             }

    //             if ($pisoActual === 1) {
    //                 // Validación para piso 1
    //                 $pisosPrevios = ProyectosDetalle::where('torre', $torre)
    //                     ->where('orden_proceso', $procesoAnterior)
    //                     ->where('proyecto_id', $proyecto)
    //                     ->whereIn('piso', range(1, $pisosRequeridos))
    //                     ->where('estado', 2)
    //                     ->get()
    //                     ->groupBy('piso');

    //                 $pisosCumplenMinimo = 0;

    //                 foreach ($pisosPrevios as $piso => $aptos) {
    //                     if ($aptos->count() >= $aptMinimos) {
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
    //             } else {
    //                 // Validación para piso > 1
    //                 $totalPisos = $existeProcesoAnterior->pluck('piso')->unique()->count();

    //                 $procesoAntCompletado = ProyectosDetalle::where('torre', $torre)
    //                     ->where('orden_proceso', $procesoAnterior)
    //                     ->where('proyecto_id', $proyecto)
    //                     ->whereIn('piso', range(1, $totalPisos))
    //                     ->get();

    //                 $ProcesoPasadoCompleto = $procesoAntCompletado->isNotEmpty() &&
    //                     $procesoAntCompletado->every(fn($apt) => $apt->estado === 2);

    //                 $pisoActivador = $pisoActual + ($pisosRequeridos - 1);

    //                 $activador = ProyectosDetalle::where('torre', $torre)
    //                     ->where('orden_proceso', $procesoAnterior)
    //                     ->where('proyecto_id', $proyecto)
    //                     ->where('piso', $pisoActivador)
    //                     ->where('estado', 2)
    //                     ->get();

    //                 $puedeValidarse = $activador->count() >= $aptMinimos;

    //                 if (
    //                     ($pisoActual !== $totalPisos && !$puedeValidarse) ||
    //                     ($pisoActual === $totalPisos && (!$puedeValidarse || !$ProcesoPasadoCompleto))
    //                 ) {
    //                     return response()->json([
    //                         'success' => false,
    //                         'message' => "No se puede validar este piso porque no se cumplen los requisitos del proceso anterior.",
    //                     ], 400);
    //                 }
    //             }
    //         }

    //         // Validación exitosa: habilitar piso actual
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

        // Obtener el listado de nombres de torre por código
        $torresConNombre = DB::table('nombre_xtore')
            ->where('proyecto_id', $proyectoId)
            ->pluck('nombre_torre', 'torre') // [codigo => nombre]
            ->toArray();

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

        // Obtener lista de torres con código y nombre
        $torres = $detalles->pluck('torre')->unique()->sort()->values()->map(function ($codigoTorre) use ($torresConNombre) {
            return [
                'codigo' => $codigoTorre,
                'nombre' => $torresConNombre[$codigoTorre] ?? "Torre {$codigoTorre}"
            ];
        });

        $resultado = [];

        foreach ($procesos as $proceso => $itemsProceso) {
            $fila = ['proceso' => $proceso];
            $totalGlobal = 0;
            $terminadosGlobal = 0;

            foreach ($torres as $torre) {
                $codigo = $torre['codigo'];
                $nombre = $torre['nombre'];

                $filtrados = $itemsProceso->where('torre', $codigo);
                $total = $filtrados->count();
                $terminados = $filtrados->where('estado', 2)->count();

                $porcentaje = $total > 0 ? round(($terminados / $total) * 100, 2) : 0;
                $fila[$nombre] = "{$terminados}/{$total} ({$porcentaje}%)";

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
                'torres' => $torres->pluck('nombre'),
                'reporte' => $resultado,
                'proyecto_id' => $proyectoId
            ]
        ]);
    }

    // cambio estado de apartamentos confirado por erro-mentira
    public function CambioEstadosApt(Request $request)
    {
        DB::beginTransaction();

        try {
            $info = ProyectosDetalle::findOrFail($request->aptId);

            // Obtener todos los APT con estado 2 que coincidan en proyecto, torre y consecutivo
            $aptosRelacionados = ProyectosDetalle::where('proyecto_id', $info->proyecto_id)
                ->where('torre', $info->torre)
                ->where('consecutivo', $info->consecutivo)
                ->where('estado', 2)
                ->where('orden_proceso', '>=', $info->orden_proceso)
                ->get();

            // Cambiar estado a 1 y limpiar campos
            $idsAfectados = [];
            foreach ($aptosRelacionados as $apt) {
                $apt->estado = 1;
                $apt->fecha_habilitado = now();
                $apt->fecha_fin = null;
                $apt->user_id = null;
                $apt->update();

                $idsAfectados[] = $apt->id;
            }

            // Guardar log de anulación
            $LogCambioEstadoApt = new AnulacionApt();
            $LogCambioEstadoApt->motivo = $request->detalle;
            $LogCambioEstadoApt->piso = (int) $info->piso;
            $LogCambioEstadoApt->apt = $request->aptId;
            $LogCambioEstadoApt->fecha_confirmo = $info->fecha_fin;
            $LogCambioEstadoApt->userConfirmo_id = $info->user_id;
            $LogCambioEstadoApt->user_id = Auth::id();
            $LogCambioEstadoApt->proyecto_id = $info->proyecto_id;
            $LogCambioEstadoApt->apt_afectados = json_encode($idsAfectados); // <<< aquí guardamos los IDs afectados
            $LogCambioEstadoApt->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $aptosRelacionados // devolvemos todos los afectados, no solo uno
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

    //---------------------------------------------------------
    // //logica bien

    // public function confirmarAptNuevaLogica($id)
    // {
    //     DB::beginTransaction();

    //     try {
    //         $info = ProyectosDetalle::findOrFail($id);

    //         $torre = $info->torre;
    //         $orden_proceso = (int) $info->orden_proceso;
    //         $piso = (int) $info->piso;

    //         $proyecto = Proyectos::findOrFail($info->proyecto_id);
    //         $TipoProceso = strtolower(ProcesosProyectos::where('id', $info->procesos_proyectos_id)->value('nombre_proceso'));

    //         $info->estado = 2;
    //         $info->fecha_fin = now();
    //         $info->user_id = Auth::id();
    //         $info->save();

    //         switch ($TipoProceso) {
    //             case 'fundida':
    //                 $this->confirmarFundida($proyecto, $torre, $orden_proceso, $piso);
    //                 break;
    //             case 'destapada':
    //             case 'prolongacion':
    //                 $this->intentarHabilitarAlambrada($info);
    //                 break;

    //             case 'alambrada':
    //                 $this->validarYHabilitarPorPiso($proyecto, $torre, $piso, 'alambrada', 'aparateada');
    //                 break;
    //             case 'aparateada':
    //                 $fase2 = DB::table('proyecto_detalle')
    //                     ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
    //                     ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', ['aparateada fase 2'])
    //                     ->where('proyecto_detalle.torre', $torre)
    //                     ->where('proyecto_detalle.proyecto_id', $proyecto->id)
    //                     ->exists();

    //                 $siguienteProceso = $fase2 ? 'aparateada fase 2' : 'pruebas';
    //                 $this->validarYHabilitarPorPiso($proyecto, $torre, $piso, 'aparateada', $siguienteProceso);
    //                 break;

    //             case 'aparateada fase 2':
    //                 $this->validarYHabilitarPorPiso($proyecto, $torre, $piso, 'alambrada', 'pruebas');
    //                 break;
    //             case 'pruebas':
    //                 $this->confirmarPruebas($proyecto, $torre, $orden_proceso, $piso);
    //                 break;

    //             case 'retie':
    //             case 'ritel':
    //                 $this->intentarHabilitarEntrega($info); // esta función no habilita entrega directamente, solo revisa
    //                 break;

    //             case 'entrega':
    //                 break;

    //             default:
    //                 return response()->json([
    //                     'success' => false,
    //                     'data' => 'ERROR, PROCESO NO EXISTENTE, COMUNICATE CON TI'
    //                 ]);
    //         }

    //         DB::commit();
    //         return response()->json([
    //             'success' => true,
    //             'data' => $info
    //         ]);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error al confirmar apartamento',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // private function confirmarFundida($proyecto, $torre, $orden_proceso, $piso)
    // {
    //     // Revisar si todo el piso de fundida esta completo
    //     $aptosDelPiso = ProyectosDetalle::where('torre', $torre)
    //         ->where('orden_proceso', $orden_proceso)
    //         ->where('proyecto_id', $proyecto->id)
    //         ->where('piso', $piso)
    //         ->get();

    //     $confirmarInicioProceso = $aptosDelPiso->isNotEmpty() && $aptosDelPiso->every(fn($apt) => $apt->estado == 2);

    //     if ($confirmarInicioProceso) {
    //         // Habilitar siguiente piso fundida
    //         ProyectosDetalle::where('torre', $torre)
    //             ->where('orden_proceso', $orden_proceso)
    //             ->where('piso', $piso + 1)
    //             ->where('proyecto_id', $proyecto->id)
    //             ->where('estado', 0)
    //             ->update(['estado' => 1, 'fecha_habilitado' => now()]);

    //         // Validar y habilitar procesos dependientes
    //         $this->validarYHabilitarProceso($proyecto, $torre, $piso, 'destapada');
    //         $this->validarYHabilitarProceso($proyecto, $torre, $piso, 'prolongacion');
    //     }
    // }

    // private function validarYHabilitarProceso($proyecto, $torre, $piso, $procesoNombre)
    // {
    //     //Buscamos los pisos minimo pro proceso para poder activar este proceso
    //     $CambioProceso = DB::table('cambio_procesos_x_proyecto')
    //         ->join('procesos_proyectos', 'procesos_proyectos.id', '=', 'cambio_procesos_x_proyecto.proceso')
    //         ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
    //         ->where('cambio_procesos_x_proyecto.proyecto_id', $proyecto->id)
    //         ->select('cambio_procesos_x_proyecto.*')
    //         ->first();

    //     $pisosRequeridos = $CambioProceso ? (int) $CambioProceso->numero : 0;

    //     //validamos si ya el proceso esta iniciado, es decir que tenga en piso 1 un estado diferente a 0
    //     $inicioProceso = DB::table('proyecto_detalle')
    //         ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
    //         ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
    //         ->where('proyecto_detalle.torre', $torre)
    //         ->where('proyecto_detalle.proyecto_id', $proyecto->id)
    //         ->where('proyecto_detalle.piso', 1)
    //         ->get();

    //     $yaIniciado = $inicioProceso->isNotEmpty() && $inicioProceso->every(fn($apt) => $apt->estado != 0);

    //     //se compara, si tiene estado diferente a 0 entra en el if
    //     if ($yaIniciado) {

    //         $nuevoPiso = $piso - ($pisosRequeridos - 1);

    //         $verValidacion = DB::table('proyecto_detalle')
    //             ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
    //             ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
    //             ->where('proyecto_detalle.torre', $torre)
    //             ->where('proyecto_detalle.proyecto_id', $proyecto->id)
    //             ->where('proyecto_detalle.piso', $nuevoPiso)
    //             ->select('proyecto_detalle.*')
    //             ->get();

    //         $todosPendientes = $verValidacion->every(fn($apt) => $apt->validacion == 1 && $apt->estado_validacion == 0);

    //         if ($todosPendientes) {
    //             return; // espera validación externa
    //         }

    //         ProyectosDetalle::where('torre', $torre)
    //             ->where('proyecto_id', $proyecto->id)
    //             ->where('piso', $nuevoPiso)
    //             ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoNombre]))
    //             ->where('estado', 0)
    //             ->update(['estado' => 1, 'fecha_habilitado' => now()]);
    //     } elseif ($piso >= $pisosRequeridos) {

    //         // Aún no iniciado, inicia en piso 1
    //         $detalle = DB::table('proyecto_detalle')
    //             ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
    //             ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
    //             ->where('proyecto_detalle.torre', $torre)
    //             ->where('proyecto_detalle.proyecto_id', $proyecto->id)
    //             ->select('proyecto_detalle.*')
    //             ->first();

    //         if ($detalle && $detalle->validacion == 1 && $detalle->estado_validacion == 0) {
    //             return; // espera validación externa
    //         }


    //         DB::table('proyecto_detalle')
    //             ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
    //             ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
    //             ->where('proyecto_detalle.torre', $torre)
    //             ->where('proyecto_detalle.proyecto_id', $proyecto->id)
    //             ->where('proyecto_detalle.piso', 1)
    //             ->where('estado', 0)
    //             ->update(['estado' => 1, 'fecha_habilitado' => now()]);
    //     }
    // }

    // private function intentarHabilitarAlambrada($info)
    // {
    //     $torre = $info->torre;
    //     $proyectoId = $info->proyecto_id;
    //     $piso = $info->piso;

    //     $procesos = ['destapada', 'prolongacion'];

    //     // Validar que ambos procesos tengan todo el piso confirmado (estado == 2)
    //     foreach ($procesos as $proceso) {
    //         $aptos = ProyectosDetalle::where('torre', $torre)
    //             ->where('proyecto_id', $proyectoId)
    //             ->where('piso', $piso)
    //             ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$proceso]))
    //             ->get();

    //         // Si hay apartamentos y alguno NO está en estado 2, aún no se puede habilitar
    //         if ($aptos->isEmpty() || $aptos->contains(fn($apt) => $apt->estado != 2)) {
    //             return; // No se cumple la condición, no hacemos nada
    //         }
    //     }

    //     // Si ambos procesos están completos, habilitar alambrada en ese piso
    //     $Validacion = ProyectosDetalle::where('torre', $torre)
    //         ->where('proyecto_id', $proyectoId)
    //         ->where('piso', $piso)
    //         ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada']))
    //         ->where('estado', 0)
    //         ->get();

    //     //si necesita validacion no validar
    //     if ($Validacion->contains(function ($item) {
    //         return $item->validacion == 1 && $item->estado_validacion == 0;
    //     })) {
    //         return response()->json([
    //             'success' => true,
    //             'message' => 'espera validación externa',
    //         ], 200);
    //     }


    //     $Validacion = ProyectosDetalle::where('torre', $torre)
    //         ->where('proyecto_id', $proyectoId)
    //         ->where('piso', $piso)
    //         ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada']))
    //         ->where('estado', 0)
    //         ->update([
    //             'estado' => 1,
    //             'fecha_habilitado' => now()
    //         ]);
    // }

    // private function validarYHabilitarPorPiso($proyecto, $torre, $piso, $procesoOrigen, $procesoDestino)
    // {
    //     // 1. Revisar que todo el piso del proceso origen esté confirmado (estado 2)
    //     $aptos = ProyectosDetalle::where('torre', $torre)
    //         ->where('proyecto_id', $proyecto->id)
    //         ->where('piso', $piso)
    //         ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoOrigen]))
    //         ->get();

    //     if ($aptos->isEmpty() || $aptos->contains(fn($apt) => $apt->estado != 2)) {
    //         return; // Piso no está completo aún
    //     }

    //     // 2. Obtener la cantidad mínima de pisos requeridos
    //     $minimos = DB::table('cambio_procesos_x_proyecto')
    //         ->join('procesos_proyectos', 'cambio_procesos_x_proyecto.proceso', '=', 'procesos_proyectos.id')
    //         ->where('cambio_procesos_x_proyecto.proyecto_id', $proyecto->id)
    //         ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoDestino])
    //         ->value('numero');

    //     // $pisosMinimos = $minimos ? (int)$minimos : 0;
    //     // if ($piso < $pisosMinimos) return;

    //     // $PisoCambioProceso = $piso - ($minimos - 1);

    //     // 3. Validar si requiere validación
    //     $detalleDestino = ProyectosDetalle::where('torre', $torre)
    //         ->where('proyecto_id', $proyecto->id)
    //         ->where('piso', $piso)
    //         ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoDestino]))
    //         ->first();

    //     if ($detalleDestino->validacion == 1 && $detalleDestino->estado_validacion == 0) {
    //         return; // espera validación externa
    //     }

    //     // 4. Activar el proceso destino en ese piso
    //     ProyectosDetalle::where('torre', $torre)
    //         ->where('proyecto_id', $proyecto->id)
    //         ->where('piso', $piso)
    //         ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoDestino]))
    //         ->where('estado', 0)
    //         ->update([
    //             'estado' => 1,
    //             'fecha_habilitado' => now()
    //         ]);
    // }

    // private function intentarHabilitarEntrega($info)
    // {
    //     $torre = $info->torre;
    //     $proyectoId = $info->proyecto_id;
    //     $piso = $info->piso;
    //     $consecutivo = $info->consecutivo;

    //     $procesos = ['retie', 'ritel'];

    //     foreach ($procesos as $proceso) {
    //         // Solo buscar el apartamento 1 en cada proceso
    //         $apto = ProyectosDetalle::where('torre', $torre)
    //             ->where('proyecto_id', $proyectoId)
    //             ->where('piso', $piso)
    //             ->where('consecutivo', $consecutivo) // <-- Solo el apto 1
    //             ->whereHas(
    //                 'proceso',
    //                 fn($q) =>
    //                 $q->whereRaw('LOWER(nombre_proceso) = ?', [$proceso])
    //             )
    //             ->first();

    //         // Si no existe el apto 1 o no está en estado 2, no habilitar entrega
    //         if (!$apto || $apto->estado != 2) {
    //             return;
    //         }
    //     }

    //     // Si ambos procesos para el apto 1 están completos, habilitar entrega
    //     ProyectosDetalle::where('torre', $torre)
    //         ->where('proyecto_id', $proyectoId)
    //         ->where('piso', $piso)
    //         ->where('consecutivo', $consecutivo)
    //         ->whereHas(
    //             'proceso',
    //             fn($q) =>
    //             $q->whereRaw('LOWER(nombre_proceso) = ?', ['entrega'])
    //         )
    //         ->where('estado', 0)
    //         ->update([
    //             'estado' => 1,
    //             'fecha_habilitado' => now()
    //         ]);
    // }

    // private function confirmarPruebas($proyecto, $torre, $orden_proceso, $piso)
    // {

    //     // Confirmar todo el piso pruebas
    //     $aptosDelPiso = ProyectosDetalle::where('torre', $torre)
    //         ->where('orden_proceso', $orden_proceso)
    //         ->where('proyecto_id', $proyecto->id)
    //         ->where('piso', $piso)
    //         ->get();

    //     $confirmarInicioProceso = $aptosDelPiso->isNotEmpty() && $aptosDelPiso->every(fn($apt) => $apt->estado == 2);

    //     if ($confirmarInicioProceso) {
    //         // Validar y habilitar procesos dependientes
    //         $this->validarYHabilitarRetieYRitel($proyecto, $torre, $piso, 'retie');
    //         $this->validarYHabilitarRetieYRitel($proyecto, $torre, $piso, 'ritel');
    //     }
    // }

    // private function validarYHabilitarRetieYRitel($proyecto, $torre, $piso, $procesoNombre)
    // {
    //     //se buscan los pisos minimos para activar este proceso
    //     $CambioProceso = DB::table('cambio_procesos_x_proyecto')
    //         ->join('procesos_proyectos', 'procesos_proyectos.id', '=', 'cambio_procesos_x_proyecto.proceso')
    //         ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
    //         ->where('cambio_procesos_x_proyecto.proyecto_id', $proyecto->id)
    //         ->select('cambio_procesos_x_proyecto.*')
    //         ->first();

    //     $pisosRequeridos = $CambioProceso ? (int) $CambioProceso->numero : 0;

    //     $inicioProceso = DB::table('proyecto_detalle')
    //         ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
    //         ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
    //         ->where('proyecto_detalle.torre', $torre)
    //         ->where('proyecto_detalle.proyecto_id', $proyecto->id)
    //         ->where('proyecto_detalle.piso', 1)
    //         ->get();


    //     $yaIniciado = $inicioProceso->isNotEmpty() && $inicioProceso->every(fn($apt) => $apt->estado != 0);

    //     if ($yaIniciado) {

    //         $nuevoPiso = $piso - ($pisosRequeridos - 1);

    //         $verValidacion = DB::table('proyecto_detalle')
    //             ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
    //             ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
    //             ->where('proyecto_detalle.torre', $torre)
    //             ->where('proyecto_detalle.proyecto_id', $proyecto->id)
    //             ->where('proyecto_detalle.piso', $nuevoPiso)
    //             ->select('proyecto_detalle.*')
    //             ->get();


    //         $todosPendientes = $verValidacion->every(fn($apt) => $apt->validacion == 1 && $apt->estado_validacion == 0);

    //         if ($todosPendientes) {
    //             return; // espera validación externa
    //         }

    //         ProyectosDetalle::where('torre', $torre)
    //             ->where('proyecto_id', $proyecto->id)
    //             ->where('piso', $nuevoPiso)
    //             ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoNombre]))
    //             ->where('estado', 0)
    //             ->update(['estado' => 1, 'fecha_habilitado' => now()]);
    //     } elseif ($piso >= $pisosRequeridos) {

    //         // Aún no iniciado, inicia en piso 1
    //         $detalle = DB::table('proyecto_detalle')
    //             ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
    //             ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
    //             ->where('proyecto_detalle.torre', $torre)
    //             ->where('proyecto_detalle.proyecto_id', $proyecto->id)
    //             ->select('proyecto_detalle.*')
    //             ->first();

    //         if ($detalle && $detalle->validacion == 1 && $detalle->estado_validacion == 0) {
    //             return; // espera validación externa
    //         }


    //         DB::table('proyecto_detalle')
    //             ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
    //             ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
    //             ->where('proyecto_detalle.torre', $torre)
    //             ->where('proyecto_detalle.proyecto_id', $proyecto->id)
    //             ->where('proyecto_detalle.piso', 1)
    //             ->where('estado', 0)
    //             ->update(['estado' => 1, 'fecha_habilitado' => now()]);
    //     }
    // }

    // public function ExportInformeExcelProyecto($id)
    // {
    //     $proyectoId = $id;

    //     if (!$proyectoId) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'ID de proyecto no proporcionado.',
    //         ], 400);
    //     }

    //     $torresConNombre = DB::table('nombre_xtore')
    //         ->where('proyecto_id', $proyectoId)
    //         ->pluck('nombre_torre', 'torre')
    //         ->toArray();

    //     $detalles = DB::table('proyecto_detalle')
    //         ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
    //         ->select(
    //             'proyecto_detalle.torre',
    //             'proyecto_detalle.estado',
    //             'procesos_proyectos.nombre_proceso as proceso'
    //         )
    //         ->where('proyecto_detalle.proyecto_id', $proyectoId)
    //         ->get();

    //     if ($detalles->isEmpty()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'No se encontraron detalles para el proyecto.',
    //         ], 404);
    //     }

    //     $procesos = $detalles->groupBy('proceso');
    //     $torres = $detalles->pluck('torre')->unique()->sort()->values()->map(function ($codigoTorre) use ($torresConNombre) {
    //         return [
    //             'codigo' => $codigoTorre,
    //             'nombre' => $torresConNombre[$codigoTorre] ?? "Torre {$codigoTorre}"
    //         ];
    //     });

    //     $resultado = [];

    //     foreach ($procesos as $proceso => $itemsProceso) {
    //         $fila = ['Proceso' => $proceso];
    //         $totalGlobal = 0;
    //         $terminadosGlobal = 0;

    //         foreach ($torres as $torre) {
    //             $codigo = $torre['codigo'];
    //             $nombre = $torre['nombre'];

    //             $filtrados = $itemsProceso->where('torre', $codigo);
    //             $total = $filtrados->count();
    //             $terminados = $filtrados->where('estado', 2)->count();

    //             $porcentaje = $total > 0 ? round(($terminados / $total) * 100, 2) : 0;
    //             $fila[$nombre] = "{$terminados}/{$total} ({$porcentaje}%)";

    //             $totalGlobal += $total;
    //             $terminadosGlobal += $terminados;
    //         }

    //         $porcentajeGlobal = $totalGlobal > 0 ? round(($terminadosGlobal / $totalGlobal) * 100, 2) : 0;
    //         $fila["Total"] = "{$terminadosGlobal}/{$totalGlobal} ({$porcentajeGlobal}%)";

    //         $resultado[] = $fila;
    //     }

    //     return Excel::download(new InformeProyectoExport($resultado), 'informe-proyecto.xlsx');
    // }


    //----------------------------------------------------------------------- nuevo toque minimos piso

    public function confirmarAptNuevaLogica($id)
    {
        DB::beginTransaction();

        try {
            $info = ProyectosDetalle::findOrFail($id);

            $torre = $info->torre;
            $orden_proceso = (int) $info->orden_proceso;
            $piso = (int) $info->piso;

            $proyecto = Proyectos::findOrFail($info->proyecto_id);
            $TipoProceso = strtolower(ProcesosProyectos::where('id', $info->procesos_proyectos_id)->value('nombre_proceso'));

            if ($TipoProceso === "fundida" || $TipoProceso === "prolongacion" || $TipoProceso === "destapada" || $TipoProceso === "ritel" || $TipoProceso === "retie") {
                // Confirmar el apartamento sin reglas si es uno de estos procesos
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
                    $procesoError = strtolower(ProcesosProyectos::where('id', $verPisoAnteriorConfirmado->procesos_proyectos_id)->value('nombre_proceso'));

                    return response()->json([
                        'success' => false,
                        'message' => "El apartamento no puede ser confirmado por que el apartamento en proceso '{$procesoError}' no esta confirmado"
                    ], 400);
                }
            }

            $info->estado = 2;
            $info->fecha_fin = now();
            $info->user_id = Auth::id();
            $info->save();

            switch ($TipoProceso) {
                case 'fundida':
                    $this->confirmarFundida($proyecto, $torre, $orden_proceso, $piso);
                    break;
                case 'destapada':
                case 'prolongacion':
                    $this->intentarHabilitarAlambrada($info, $proyecto);
                    break;

                case 'alambrada':
                    $this->validarYHabilitarPorPiso($proyecto, $torre, $piso, 'alambrada', 'aparateada');
                    break;
                case 'aparateada':
                    $fase2 = DB::table('proyecto_detalle')
                        ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
                        ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', ['aparateada fase 2'])
                        ->where('proyecto_detalle.torre', $torre)
                        ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                        ->exists();

                    $siguienteProceso = $fase2 ? 'aparateada fase 2' : 'pruebas';

                    $this->validarYHabilitarPorPiso($proyecto, $torre, $piso, 'aparateada', $siguienteProceso);
                    break;

                case 'aparateada fase 2':
                    $this->validarYHabilitarPorPiso($proyecto, $torre, $piso, 'aparateada fase 2', 'pruebas');
                    break;
                case 'pruebas':
                    $this->confirmarPruebas($proyecto, $torre, $orden_proceso, $piso);
                    break;

                case 'retie':
                case 'ritel':
                    $this->intentarHabilitarEntrega($info); // esta función no habilita entrega directamente, solo revisa
                    break;

                case 'entrega':
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'data' => 'ERROR, PROCESO NO EXISTENTE, COMUNICATE CON TI'
                    ]);
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

    private function confirmarFundida($proyecto, $torre, $orden_proceso, $piso)
    {
        // Revisar si todo el piso de fundida esta completo
        $aptosDelPiso = ProyectosDetalle::where('torre', $torre)
            ->where('orden_proceso', $orden_proceso)
            ->where('proyecto_id', $proyecto->id)
            ->where('piso', $piso)
            ->get();

        $confirmarInicioProceso = $aptosDelPiso->isNotEmpty() && $aptosDelPiso->every(fn($apt) => $apt->estado == 2);

        if ($confirmarInicioProceso) {
            // Habilitar siguiente piso fundida
            ProyectosDetalle::where('torre', $torre)
                ->where('orden_proceso', $orden_proceso)
                ->where('piso', $piso + 1)
                ->where('proyecto_id', $proyecto->id)
                ->where('estado', 0)
                ->update(['estado' => 1, 'fecha_habilitado' => now()]);

            // Validar y habilitar procesos dependientes
            $this->validarYHabilitarProceso($proyecto, $torre, $piso, 'destapada');
            $this->validarYHabilitarProceso($proyecto, $torre, $piso, 'prolongacion');
        }
    }

    private function validarYHabilitarProceso($proyecto, $torre, $piso, $procesoNombre)
    {
        //Buscamos los pisos minimo por proceso para poder activar este proceso
        $CambioProceso = DB::table('cambio_procesos_x_proyecto')
            ->join('procesos_proyectos', 'procesos_proyectos.id', '=', 'cambio_procesos_x_proyecto.proceso')
            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
            ->where('cambio_procesos_x_proyecto.proyecto_id', $proyecto->id)
            ->select('cambio_procesos_x_proyecto.*')
            ->first();

        $pisosRequeridos = $CambioProceso ? (int) $CambioProceso->numero : 0;

        //validamos si ya el proceso esta iniciado, es decir que tenga en piso 1 un estado diferente a 0
        $inicioProceso = DB::table('proyecto_detalle')
            ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
            ->where('proyecto_detalle.torre', $torre)
            ->where('proyecto_detalle.proyecto_id', $proyecto->id)
            ->where('proyecto_detalle.piso', 1)
            ->get();

        $yaIniciado = $inicioProceso->isNotEmpty() && $inicioProceso->every(fn($apt) => $apt->estado != 0);

        //se compara, si tiene estado diferente a 0 entra en el if
        if ($yaIniciado) {

            $nuevoPiso = $piso - ($pisosRequeridos - 1);

            $verValidacion = DB::table('proyecto_detalle')
                ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
                ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
                ->where('proyecto_detalle.torre', $torre)
                ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                ->where('proyecto_detalle.piso', $nuevoPiso)
                ->select('proyecto_detalle.*')
                ->get();

            $todosPendientes = $verValidacion->every(fn($apt) => $apt->validacion == 1 && $apt->estado_validacion == 0);

            if ($todosPendientes) {
                return; // espera validación externa
            }

            ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyecto->id)
                ->where('piso', $nuevoPiso)
                ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoNombre]))
                ->where('estado', 0)
                ->update(['estado' => 1, 'fecha_habilitado' => now()]);
        } elseif ($piso >= $pisosRequeridos) {

            // Aún no iniciado, inicia en piso 1
            $detalle = DB::table('proyecto_detalle')
                ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
                ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
                ->where('proyecto_detalle.torre', $torre)
                ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                ->select('proyecto_detalle.*')
                ->first();

            if ($detalle && $detalle->validacion == 1 && $detalle->estado_validacion == 0) {
                return; // espera validación externa
            }


            DB::table('proyecto_detalle')
                ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
                ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
                ->where('proyecto_detalle.torre', $torre)
                ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                ->where('proyecto_detalle.piso', 1)
                ->where('estado', 0)
                ->update(['estado' => 1, 'fecha_habilitado' => now()]);
        }
    }

    private function intentarHabilitarAlambrada($info, $proyecto)
    {
        $torre = $info->torre;
        $proyectoId = $info->proyecto_id;
        $piso = $info->piso;
        $aptMinimos = $proyecto->minimoApt;

        $procesos = ['destapada', 'prolongacion'];
        $aptosConfirmados = [];

        // 1. Buscar los consecutivos con estado == 2 de cada proceso
        foreach ($procesos as $proceso) {
            $aptosConfirmados[$proceso] = ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyectoId)
                ->where('piso', $piso)
                ->whereHas(
                    'proceso',
                    fn($q) =>
                    $q->whereRaw('LOWER(nombre_proceso) = ?', [$proceso])
                )
                ->where('estado', 2)
                ->pluck('consecutivo') // consecutivo de apto
                ->toArray();
        }

        // 2. Intersección: solo los que están confirmados en ambos procesos
        $aptosValidos = array_intersect(
            $aptosConfirmados['destapada'] ?? [],
            $aptosConfirmados['prolongacion'] ?? []
        );

        // 3. Verificar si ya se cumplió al menos una vez el mínimo
        $alambradaHabilitados = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada'])
            )
            ->where('estado', 1)
            ->pluck('consecutivo')
            ->toArray();

        // Si aún no se ha habilitado nada en alambrada (primera vez)
        if (empty($alambradaHabilitados)) {
            // Validar mínimo
            if (count($aptosValidos) < $aptMinimos) {
                return; // No cumple mínimo, aún no habilitamos
            }

            // Habilitar todos los aptos válidos en alambrada (fase inicial)
            $this->habilitarAptosEnAlambrada($torre, $proyectoId, $piso, $aptosValidos);
        } else {
            // Fase 2: habilitar uno a uno los que no estén ya habilitados en alambrada
            $nuevosAptos = array_diff($aptosValidos, $alambradaHabilitados);

            if (!empty($nuevosAptos)) {
                $this->habilitarAptosEnAlambrada($torre, $proyectoId, $piso, $nuevosAptos);
            }
        }
    }
    //Función auxiliar para habilitar consecutivos en alambrada
    private function habilitarAptosEnAlambrada($torre, $proyectoId, $piso, $consecutivos)
    {
        // Obtener los aptos que coinciden y están en estado 0
        $validacion = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->whereIn('consecutivo', $consecutivos)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada'])
            )
            ->where('estado', 0)
            ->get();

        // Si alguno requiere validación externa, detener
        if ($validacion->contains(
            fn($item) =>
            $item->validacion == 1 && $item->estado_validacion == 0
        )) {
            return response()->json([
                'success' => true,
                'message' => 'espera validación externa',
            ], 200);
        }

        // Habilitar
        ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->whereIn('consecutivo', $consecutivos)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada'])
            )
            ->where('estado', 0)
            ->update([
                'estado' => 1,
                'fecha_habilitado' => now()
            ]);
    }

    // private function validarYHabilitarPorPiso($proyecto, $torre, $piso, $procesoOrigen, $procesoDestino)
    // {
    //     // 1. Revisar que todo el piso del proceso origen esté confirmado (estado 2)
    //     $aptos = ProyectosDetalle::where('torre', $torre)
    //         ->where('proyecto_id', $proyecto->id)
    //         ->where('piso', $piso)
    //         ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoOrigen]))
    //         ->get();

    //     if ($aptos->isEmpty() || $aptos->contains(fn($apt) => $apt->estado != 2)) {
    //         return; // Piso no está completo aún
    //     }

    //     // 2. Obtener la cantidad mínima de pisos requeridos
    //     $minimos = DB::table('cambio_procesos_x_proyecto')
    //         ->join('procesos_proyectos', 'cambio_procesos_x_proyecto.proceso', '=', 'procesos_proyectos.id')
    //         ->where('cambio_procesos_x_proyecto.proyecto_id', $proyecto->id)
    //         ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoDestino])
    //         ->value('numero');

    //     // $pisosMinimos = $minimos ? (int)$minimos : 0;
    //     // if ($piso < $pisosMinimos) return;

    //     // $PisoCambioProceso = $piso - ($minimos - 1);

    //     // 3. Validar si requiere validación
    //     $detalleDestino = ProyectosDetalle::where('torre', $torre)
    //         ->where('proyecto_id', $proyecto->id)
    //         ->where('piso', $piso)
    //         ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoDestino]))
    //         ->first();

    //     if ($detalleDestino->validacion == 1 && $detalleDestino->estado_validacion == 0) {
    //         return; // espera validación externa
    //     }

    //     // 4. Activar el proceso destino en ese piso
    //     ProyectosDetalle::where('torre', $torre)
    //         ->where('proyecto_id', $proyecto->id)
    //         ->where('piso', $piso)
    //         ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoDestino]))
    //         ->where('estado', 0)
    //         ->update([
    //             'estado' => 1,
    //             'fecha_habilitado' => now()
    //         ]);
    // }

    private function validarYHabilitarPorPiso($proyecto, $torre, $piso, $procesoOrigen, $procesoDestino)
    {
        $aptMinimos = $proyecto->minimoApt;

        // 1. Consecutivos confirmados del procesoOrigen en el piso enviado
        $aptosValidos = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyecto->id)
            ->where('piso', $piso)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoOrigen])
            )
            ->where('estado', 2)
            ->pluck('consecutivo')
            ->toArray();

        if (empty($aptosValidos)) {
            return; // Nada confirmado aún
        }

        // 2. Obtener el mínimo de pisos requeridos
        $minimosPisos = DB::table('cambio_procesos_x_proyecto')
            ->join('procesos_proyectos', 'cambio_procesos_x_proyecto.proceso', '=', 'procesos_proyectos.id')
            ->where('cambio_procesos_x_proyecto.proyecto_id', $proyecto->id)
            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoDestino])
            ->value('numero');

        $minimosPisos = $minimosPisos ? (int)$minimosPisos : 0;

        // 3. Validar si el procesoDestino ya se inició (piso 1 con estado != 0)
        $inicioProceso = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyecto->id)
            ->where('piso', 1)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoDestino])
            )
            ->get();

        $yaIniciado = $inicioProceso->isNotEmpty() && $inicioProceso->contains(fn($apt) => $apt->estado != 0);


        // ========================= FASE 2: YA INICIADO =========================
        if ($yaIniciado) {
            $nuevoPiso = $piso - ($minimosPisos - 1);
            // Pendientes en el piso del procesoDestino
            $pendientesDestino = ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyecto->id)
                ->where('piso', $nuevoPiso)
                ->whereHas(
                    'proceso',
                    fn($q) =>
                    $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoDestino])
                )
                ->where('estado', 0)
                ->pluck('consecutivo')
                ->toArray();

            // --------> Transformación de consecutivos
            $pisoActualizado = $piso - $nuevoPiso;
            $aptosTransformados = array_map(function ($apt) use ($pisoActualizado) {
                return (int)$apt - ($pisoActualizado * 100);
            }, $aptosValidos);

            // Intersección con los pendientes en destino
            $aptosHabilitar = array_intersect($aptosTransformados, $pendientesDestino);

            if (empty($aptosHabilitar)) {
                return;
            }

            // Aptos habilitados actualmente en el destino
            $habilitadosDestino = ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyecto->id)
                ->where('piso', $nuevoPiso)
                ->whereHas(
                    'proceso',
                    fn($q) =>
                    $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoDestino])
                )
                ->where('estado', 1)
                ->pluck('consecutivo')
                ->toArray();

            /**
             * Si el total habilitado (actuales + los nuevos por habilitar) aún es menor que $aptMinimos,
             * habilitamos TODOS los aptos de este bloque.
             */
            if ((count($habilitadosDestino) + count($aptosHabilitar)) < $aptMinimos) {
                /**
                 * Aún no se cumple el mínimo: NO habilitar nada.
                 */
                return;
            } elseif ((count($habilitadosDestino) + count($aptosHabilitar)) == $aptMinimos) {
                /**
                 * Exactamente se cumple el mínimo con estos aptos: habilitarlos todos.
                 */
                $this->habilitarPorConsecutivos($proyecto, $torre, $nuevoPiso, $procesoDestino, $aptosHabilitar);
            } else {
                /**
                 * Ya se cumplió el mínimo previamente. Habilitar de a uno.
                 */
                $nuevo = reset($aptosHabilitar);
                $this->habilitarPorConsecutivos($proyecto, $torre, $nuevoPiso, $procesoDestino, [$nuevo]);
            }


            // ========================= FASE 1: NO INICIADO =========================
        } elseif ($piso >= $minimosPisos) {

            // Contar cuántos pisos cumplen mínimo aptos
            $pisosCumplen = ProyectosDetalle::select('piso')
                ->where('torre', $torre)
                ->where('proyecto_id', $proyecto->id)
                ->whereHas(
                    'proceso',
                    fn($q) =>
                    $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoOrigen])
                )
                ->where('estado', 2)
                ->groupBy('piso')
                ->havingRaw('COUNT(*) >= ?', [$aptMinimos])
                ->pluck('piso')
                ->toArray();

            // Si aún no cumplen los pisos mínimos → no hacer nada
            if (count($pisosCumplen) < $minimosPisos) {
                return;
            }

            // Habilitar en piso 1 los aptos confirmados en el piso 1 del procesoOrigen
            $aptosPiso1Origen = ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyecto->id)
                ->where('piso', 1)
                ->whereHas(
                    'proceso',
                    fn($q) =>
                    $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoOrigen])
                )
                ->where('estado', 2)
                ->pluck('consecutivo')
                ->toArray();


            $this->habilitarPorConsecutivos($proyecto, $torre, 1, $procesoDestino, $aptosPiso1Origen);
        }
    }

    //Función auxiliar para habilitar consecutivos en el procesoDestino
    private function habilitarPorConsecutivos($proyecto, $torre, $piso, $procesoDestino, $consecutivos)
    {
        if (empty($consecutivos)) return;

        $verValidacion = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyecto->id)
            ->where('piso', $piso)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoDestino])
            )
            ->get();

        $todosPendientes = $verValidacion->every(fn($apt) => $apt->validacion == 1 && $apt->estado_validacion == 0);

        if ($todosPendientes) {
            return; // espera validación externa
        }



        ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyecto->id)
            ->where('piso', $piso)
            ->whereIn('consecutivo', $consecutivos)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoDestino])
            )
            ->where('estado', 0)
            ->update(['estado' => 1, 'fecha_habilitado' => now()]);
    }

    private function intentarHabilitarEntrega($info)
    {
        $torre = $info->torre;
        $proyectoId = $info->proyecto_id;
        $piso = $info->piso;
        $consecutivo = $info->consecutivo;

        $procesos = ['retie', 'ritel'];

        foreach ($procesos as $proceso) {
            // Solo buscar el apartamento 1 en cada proceso
            $apto = ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyectoId)
                ->where('piso', $piso)
                ->where('consecutivo', $consecutivo) // <-- Solo el apto 1
                ->whereHas(
                    'proceso',
                    fn($q) =>
                    $q->whereRaw('LOWER(nombre_proceso) = ?', [$proceso])
                )
                ->first();

            // Si no existe el apto 1 o no está en estado 2, no habilitar entrega
            if (!$apto || $apto->estado != 2) {
                return;
            }
        }

        // Si ambos procesos para el apto 1 están completos, habilitar entrega
        ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->where('consecutivo', $consecutivo)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', ['entrega'])
            )
            ->where('estado', 0)
            ->update([
                'estado' => 1,
                'fecha_habilitado' => now()
            ]);
    }

    private function confirmarPruebas($proyecto, $torre, $orden_proceso, $piso)
    {

        // Confirmar todo el piso pruebas
        $aptosDelPiso = ProyectosDetalle::where('torre', $torre)
            ->where('orden_proceso', $orden_proceso)
            ->where('proyecto_id', $proyecto->id)
            ->where('piso', $piso)
            ->get();

        $confirmarInicioProceso = $aptosDelPiso->isNotEmpty() && $aptosDelPiso->every(fn($apt) => $apt->estado == 2);

        if ($confirmarInicioProceso) {
            // Validar y habilitar procesos dependientes
            $this->validarYHabilitarRetieYRitel($proyecto, $torre, $piso, 'retie');
            $this->validarYHabilitarRetieYRitel($proyecto, $torre, $piso, 'ritel');
        }
    }

    private function validarYHabilitarRetieYRitel($proyecto, $torre, $piso, $procesoNombre)
    {
        //se buscan los pisos minimos para activar este proceso
        $CambioProceso = DB::table('cambio_procesos_x_proyecto')
            ->join('procesos_proyectos', 'procesos_proyectos.id', '=', 'cambio_procesos_x_proyecto.proceso')
            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
            ->where('cambio_procesos_x_proyecto.proyecto_id', $proyecto->id)
            ->select('cambio_procesos_x_proyecto.*')
            ->first();

        $pisosRequeridos = $CambioProceso ? (int) $CambioProceso->numero : 0;

        $inicioProceso = DB::table('proyecto_detalle')
            ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
            ->where('proyecto_detalle.torre', $torre)
            ->where('proyecto_detalle.proyecto_id', $proyecto->id)
            ->where('proyecto_detalle.piso', 1)
            ->get();


        $yaIniciado = $inicioProceso->isNotEmpty() && $inicioProceso->every(fn($apt) => $apt->estado != 0);

        if ($yaIniciado) {

            $nuevoPiso = $piso - ($pisosRequeridos - 1);

            $verValidacion = DB::table('proyecto_detalle')
                ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
                ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
                ->where('proyecto_detalle.torre', $torre)
                ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                ->where('proyecto_detalle.piso', $nuevoPiso)
                ->select('proyecto_detalle.*')
                ->get();


            $todosPendientes = $verValidacion->every(fn($apt) => $apt->validacion == 1 && $apt->estado_validacion == 0);

            if ($todosPendientes) {
                return; // espera validación externa
            }

            ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyecto->id)
                ->where('piso', $nuevoPiso)
                ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoNombre]))
                ->where('estado', 0)
                ->update(['estado' => 1, 'fecha_habilitado' => now()]);
        } elseif ($piso >= $pisosRequeridos) {

            // Aún no iniciado, inicia en piso 1
            $detalle = DB::table('proyecto_detalle')
                ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
                ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
                ->where('proyecto_detalle.torre', $torre)
                ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                ->select('proyecto_detalle.*')
                ->first();

            if ($detalle && $detalle->validacion == 1 && $detalle->estado_validacion == 0) {
                return; // espera validación externa
            }


            DB::table('proyecto_detalle')
                ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
                ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
                ->where('proyecto_detalle.torre', $torre)
                ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                ->where('proyecto_detalle.piso', 1)
                ->where('estado', 0)
                ->update(['estado' => 1, 'fecha_habilitado' => now()]);
        }
    }

    // private function confirmarPruebas($proyecto, $torre, $orden_proceso, $piso)
    // {
    //     $aptMinimos = $proyecto->minimoApt;

    //     // Obtener todos los aptos del piso para este proceso
    //     $aptosDelPiso = ProyectosDetalle::where('torre', $torre)
    //         ->where('orden_proceso', $orden_proceso)
    //         ->where('proyecto_id', $proyecto->id)
    //         ->where('piso', $piso)
    //         ->get();

    //     // Filtrar solo los que están confirmados (estado == 2)
    //     $confirmados = $aptosDelPiso->filter(fn($apt) => $apt->estado == 2);

    //     // Validar si al menos se cumple el mínimo requerido
    //     $confirmarInicioProceso = $confirmados->count() >= $aptMinimos;

    //     if ($confirmarInicioProceso) {
    //         // Validar y habilitar procesos dependientes
    //         $this->validarYHabilitarRetieYRitel($proyecto, $torre, $piso, 'retie');
    //         $this->validarYHabilitarRetieYRitel($proyecto, $torre, $piso, 'ritel');
    //     }
    // }


    // private function validarYHabilitarRetieYRitel($proyecto, $torre, $piso, $procesoNombre)
    // {
    //     $aptMinimos = $proyecto->minimoApt;
    //     info("#entro");

    //     // Obtener pisos requeridos
    //     $CambioProceso = DB::table('cambio_procesos_x_proyecto')
    //         ->join('procesos_proyectos', 'procesos_proyectos.id', '=', 'cambio_procesos_x_proyecto.proceso')
    //         ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
    //         ->where('cambio_procesos_x_proyecto.proyecto_id', $proyecto->id)
    //         ->select('cambio_procesos_x_proyecto.*')
    //         ->first();



    //     $pisosRequeridos = $CambioProceso ? (int) $CambioProceso->numero : 0;
    //     info("cambio nuemro " . $pisosRequeridos);

    //     // Verificar si el proceso ya inició en piso 1
    //     $inicioProceso = DB::table('proyecto_detalle')
    //         ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
    //         ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
    //         ->where('proyecto_detalle.torre', $torre)
    //         ->where('proyecto_detalle.proyecto_id', $proyecto->id)
    //         ->where('proyecto_detalle.piso', 1)
    //         ->get();

    //     $yaIniciado = $inicioProceso->isNotEmpty() && $inicioProceso->contains(fn($apt) => $apt->estado != 0);


    //     if ($yaIniciado) {
    //         info("cumple");
    //         $nuevoPiso = $piso - ($pisosRequeridos - 1);

    //         $verValidacion = DB::table('proyecto_detalle')
    //             ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
    //             ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
    //             ->where('proyecto_detalle.torre', $torre)
    //             ->where('proyecto_detalle.proyecto_id', $proyecto->id)
    //             ->where('proyecto_detalle.piso', $nuevoPiso)
    //             ->select('proyecto_detalle.*')
    //             ->get();

    //         $todosPendientes = $verValidacion->every(fn($apt) => $apt->validacion == 1 && $apt->estado_validacion == 0);

    //         if ($todosPendientes) {
    //             return;
    //         }

    //         // Aplicar lógica de mínimos para habilitar
    //         $aptosConfirmados = ProyectosDetalle::where('torre', $torre)
    //             ->where('proyecto_id', $proyecto->id)
    //             ->where('piso', $piso)
    //             ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoNombre]))
    //             ->where('estado', 2)
    //             ->pluck('consecutivo')
    //             ->toArray();

    //         $pendientesDestino = ProyectosDetalle::where('torre', $torre)
    //             ->where('proyecto_id', $proyecto->id)
    //             ->where('piso', $nuevoPiso)
    //             ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoNombre]))
    //             ->where('estado', 0)
    //             ->pluck('consecutivo')
    //             ->toArray();

    //         $pisoActualizado = $piso - $nuevoPiso;
    //         $transformados = array_map(fn($apt) => (int)$apt - ($pisoActualizado * 100), $aptosConfirmados);

    //         $aptosHabilitar = array_intersect($transformados, $pendientesDestino);

    //         $yaHabilitados = ProyectosDetalle::where('torre', $torre)
    //             ->where('proyecto_id', $proyecto->id)
    //             ->where('piso', $nuevoPiso)
    //             ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoNombre]))
    //             ->where('estado', 1)
    //             ->pluck('consecutivo')
    //             ->toArray();

    //         if ((count($yaHabilitados) + count($aptosHabilitar)) < $aptMinimos) {
    //             return;
    //         } elseif ((count($yaHabilitados) + count($aptosHabilitar)) == $aptMinimos) {
    //             $this->habilitarPorConsecutivos($proyecto, $torre, $nuevoPiso, $procesoNombre, $aptosHabilitar);
    //         } else {
    //             $uno = reset($aptosHabilitar);
    //             $this->habilitarPorConsecutivos($proyecto, $torre, $nuevoPiso, $procesoNombre, [$uno]);
    //         }
    //     } elseif ($piso >= $pisosRequeridos) {
    //         info("no cumple pero se debe activar");
    //         // Verifica que se cumplan pisos suficientes con mínimo de aptos confirmados
    //         $pisosCumplen = ProyectosDetalle::select('piso')
    //             ->where('torre', $torre)
    //             ->where('proyecto_id', $proyecto->id)
    //             ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ["pruebas"]))
    //             ->where('estado', 2)
    //             ->groupBy('piso')
    //             ->havingRaw('COUNT(*) >= ?', [$aptMinimos])
    //             ->pluck('piso')
    //             ->toArray();



    //         info("validacion exerna-1");
    //         info("apt que cumplen: " . json_encode($pisosCumplen));



    //         if (count($pisosCumplen) < $pisosRequeridos) {
    //             info("no cumplen por pisos ");

    //             return;
    //         }
    //         info("validacion exerna");

    //         // Validación externa antes de habilitar piso 1
    //         $detalle = DB::table('proyecto_detalle')
    //             ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
    //             ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
    //             ->where('proyecto_detalle.torre', $torre)
    //             ->where('proyecto_detalle.proyecto_id', $proyecto->id)
    //             ->where('proyecto_detalle.piso', 1)
    //             ->select('proyecto_detalle.*')
    //             ->first();

    //         if ($detalle && $detalle->validacion == 1 && $detalle->estado_validacion == 0) {
    //             return;
    //         }

    //         info("validacion exerna superada");


    //         // Aptos confirmados del procesoNombre en piso 1
    //         $aptosConfirmados = ProyectosDetalle::where('torre', $torre)
    //             ->where('proyecto_id', $proyecto->id)
    //             ->where('piso', 1)
    //             ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoNombre]))
    //             ->where('estado', 2)
    //             ->pluck('consecutivo')
    //             ->toArray();

    //         $this->habilitarPorConsecutivos($proyecto, $torre, 1, $procesoNombre, $aptosConfirmados);
    //     }
    // }
}
