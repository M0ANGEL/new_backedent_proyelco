<?php

namespace App\Http\Controllers\Api\Proyectos;

use App\Http\Controllers\Controller;
use App\Models\ProcesosProyectos;
use App\Models\ProyectoCasa;
use App\Models\ProyectoCasaDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcesoEstados 
{
    const PENDIENTE = 0;
    const HABILITADO = 1;
    const CONFIRMADO = 2;
}

class TiposProceso
{
    const DESTAPADA = 'destapada';
    const PROLONGACION = 'prolongacion';
    const ALAMBRADA = 'alambrada';
    const APARATEADA = 'aparateada';
    const APARATEADA_FASE2 = 'aparateada fase 2';
    const PRUEBAS = 'pruebas';
    const RETIE = 'retie';
    const RITEL = 'ritel';
    const ENTREGA = 'entrega';
}

class GestionProyectosCasasController extends Controller
{
    public function infoProyectoCasa($id)
    {
        $info = ProyectoCasa::find($id);

        return response()->json([
            'success' => true,
            'data' => $info
        ]);
    }

    public function indexProgresoCasa(Request $request)
    {
        // 1. CONFIGURACIÓN DE PROCESOS
        $procesosConfig = DB::table('proyectos_casas_detalle')
            ->join('cambio_procesos_x_proyecto', function ($join) {
                $join->on('cambio_procesos_x_proyecto.proyecto_id', '=', 'proyectos_casas_detalle.proyecto_casa_id')
                    ->on('cambio_procesos_x_proyecto.proceso', '=', 'proyectos_casas_detalle.procesos_proyectos_id');
            })
            ->where('proyectos_casas_detalle.proyecto_casa_id', $request->id)
            ->select(
                'proyectos_casas_detalle.orden_proceso',
                'proyectos_casas_detalle.procesos_proyectos_id',
                'proyectos_casas_detalle.proyecto_casa_id',
                'cambio_procesos_x_proyecto.numero as pisos_requeridos'
            )
            ->get()
            ->keyBy('orden_proceso');

        // 2. NOMBRES DE MANZANAS
        $manzanaConNombre = DB::table('nombrexmanzana')
            ->where('proyectos_casas_id', $request->id)
            ->pluck('nombre_manzana', 'manzana')
            ->toArray();

        // 3. DETALLE DEL PROYECTO
        $proyecProyectoCasaDetalle = DB::connection('mysql')
            ->table('proyectos_casas_detalle')
            ->leftJoin('users', 'proyectos_casas_detalle.user_id', '=', 'users.id')
            ->leftJoin('procesos_proyectos', 'proyectos_casas_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
            ->where('proyectos_casas_detalle.proyecto_casa_id', $request->id)
            ->select(
                'proyectos_casas_detalle.manzana',
                'proyectos_casas_detalle.casa',
                'proyectos_casas_detalle.id',
                'proyectos_casas_detalle.validacion',
                'proyectos_casas_detalle.estado_validacion',
                'proyectos_casas_detalle.consecutivo_casa',
                'proyectos_casas_detalle.orden_proceso',
                'proyectos_casas_detalle.piso',
                'proyectos_casas_detalle.etapa', // NUEVO CAMPO
                'proyectos_casas_detalle.text_validacion',
                'proyectos_casas_detalle.estado',
                'procesos_proyectos.nombre_proceso',
                'users.nombre as nombre'
            )
            ->orderBy('proyectos_casas_detalle.manzana')
            ->orderBy('proyectos_casas_detalle.casa')
            ->orderBy('proyectos_casas_detalle.piso')
            ->orderBy('proyectos_casas_detalle.orden_proceso')
            ->get();

        $resultado = [];
        $manzanaResumen = [];

        foreach ($proyecProyectoCasaDetalle as $item) {
            $manzana = $item->manzana;
            $casa = $item->casa;
            $piso = $item->piso;
            $orden_proceso = $item->orden_proceso;
            $etapa = $item->etapa ?? 'Sin Etapa'; // valor por defecto si no tiene etapa

            // Inicializar manzana
            if (!isset($resultado[$manzana])) {
                $resultado[$manzana] = [];
            }

            // Resumen por manzana
            if (!isset($manzanaResumen[$manzana])) {
                $manzanaResumen[$manzana] = [
                    'nombre_manzana' => $manzanaConNombre[$manzana] ?? $manzana,
                    'total_atraso' => 0,
                    'total_realizados' => 0,
                    'porcentaje_atraso' => 0,
                    'porcentaje_avance' => 0,
                    'serial_avance' => '0/0',
                ];
            }

            // Inicializar casa
            if (!isset($resultado[$manzana][$casa])) {
                $resultado[$manzana][$casa] = [
                    'consecutivo' => $item->consecutivo_casa,
                    'pisos' => []
                ];
            }

            // Inicializar piso
            if (!isset($resultado[$manzana][$casa]['pisos'][$piso])) {
                $resultado[$manzana][$casa]['pisos'][$piso] = [];
            }

            // Inicializar etapa
            if (!isset($resultado[$manzana][$casa]['pisos'][$piso][$etapa])) {
                $resultado[$manzana][$casa]['pisos'][$piso][$etapa] = [];
            }

            // Inicializar proceso dentro de la etapa
            $resultado[$manzana][$casa]['pisos'][$piso][$etapa][$orden_proceso] = [
                'id' => $item->id,
                'nombre_proceso' => $item->nombre_proceso,
                'estado' => $item->estado,
                'validacion' => $item->validacion,
                'estado_validacion' => $item->estado_validacion,
                'text_validacion' => $item->text_validacion,
                'usuario' => $item->nombre,
            ];

            // Contadores para resumen
            if ($item->estado == 2) {
                $manzanaResumen[$manzana]['total_realizados']++;
            } elseif ($item->estado == 0) {
                $manzanaResumen[$manzana]['total_atraso']++;
            }
        }

        // Calcular % por manzana
        foreach ($manzanaResumen as $manzana => &$resumen) {
            $totalCasas = $resumen['total_atraso'] + $resumen['total_realizados'];
            if ($totalCasas > 0) {
                $resumen['porcentaje_avance'] =
                    round(($resumen['total_realizados'] / $totalCasas) * 100, 2);
                $resumen['porcentaje_atraso'] =
                    round(($resumen['total_atraso'] / $totalCasas) * 100, 2);
                $resumen['serial_avance'] =
                    "{$resumen['total_realizados']}/{$totalCasas}";
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $resultado,       // manzana → casas → pisos → etapas → procesos
            'manzanaResumen' => $manzanaResumen
        ]);
    }

    public function destroy($id)
    {
        $iniciarProyecto = ProyectoCasa::find($id);

        $iniciarProyecto->fecha_ini_proyecto = now();
        $iniciarProyecto->update();
    }

    //iniciar manzana
    public function IniciarManzana(Request $request)
    {

        // Validar los datos de entrada
        $validated = $request->validate([
            'proyecto' => 'required|exists:proyecto,id',
            'manzana' => 'required'
        ]);


        $proyectoId = $validated['proyecto'];
        $manzana = $validated['manzana'];

        DB::beginTransaction();
        try {
            // 1. Actualizar todos los apartamentos del piso 1, proceso 1 para esta torre
            $updated = DB::table('proyectos_casas_detalle')
                ->where('proyecto_casa_id', $proyectoId)
                ->where('manzana', $manzana)
                ->where('etapa', '1')
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
                    'manzana' => $manzana,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar la manzana: ' . $e->getMessage(),
                'error_details' => $e->getTraceAsString()
            ], 500);
        }
    }

    //detalle de casas
     public function infoCasa(Request $request){
        // buscamos la info del apt
        $info = DB::connection('mysql')
            ->table('proyectos_casas_detalle')
            ->leftJoin('users', 'proyectos_casas_detalle.user_id', '=', 'users.id')
            ->leftJoin('procesos_proyectos', 'proyectos_casas_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
            ->where('proyectos_casas_detalle.id', $request->id)
            ->select(
                'proyectos_casas_detalle.*',
                'users.nombre as nombre'
            )
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => $info,
        ]);
    }


    /* ******************************************************************************************************************************* */
    //logica de gestion de manzanas encargado de obra

    // public function confirmarCasas($id)
    // {
    //     DB::beginTransaction();

    //     try {
    //         $info = ProyectoCasaDetalle::findOrFail($id);

    //         $manzana = $info->manzana;
    //         $orden_proceso =  $info->orden_proceso;
    //         $piso =  $info->piso;
    //         $etapa =  $info->etapa;
    //         $casa =  $info->casa;

    //         $proyecto = ProyectoCasa::findOrFail($info->proyecto_casa_id);
    //         $TipoProceso = strtolower(ProcesosProyectos::where('id', $info->procesos_proyectos_id)->value('nombre_proceso'));


    //         //etapa 1, todo lo que tiene que ver con fundicion
    //         if ($etapa == 1) {

    //             // Confirmar proceso actual
    //             $info->estado = 2; // confirmado
    //             $info->fecha_fin = now();
    //             $info->user_id = Auth::id();
    //             $info->save();

    //             // Buscar el siguiente proceso en la misma etapa
    //             $siguiente = ProyectoCasaDetalle::where('proyecto_casa_id', $proyecto->id)
    //                 ->where('manzana', $manzana)
    //                 ->where('etapa', $etapa)
    //                 ->where('casa', $casa)
    //                 ->where('orden_proceso', $orden_proceso + 1) // el inmediato siguiente
    //                 ->first();

    //             if ($siguiente) {
    //                 // habilitar el siguiente proceso
    //                 if ($siguiente->estado == 0) {
    //                     $siguiente->estado = 1;
    //                     $info->fecha_habilitado = now();
    //                     $siguiente->save();
    //                 }
    //             } else {
    //                 //cuando ya sea el ultimo proceso ed etapa 1, se habilita el primero de etapa 2
    //                 $siguientesEtapa = ProyectoCasaDetalle::where('proyecto_casa_id', $proyecto->id)
    //                     ->where('manzana', $manzana)
    //                     ->where('etapa', 2)
    //                     ->where('casa', $casa)
    //                     ->where('piso', 1)
    //                     ->whereIn('orden_proceso', [1, 2]) // procesos 1 y 2
    //                     ->get();

    //                 foreach ($siguientesEtapa as $proceso) {
    //                     if ($proceso->estado == 0) {
    //                         $proceso->estado = 1; // habilitado
    //                         $proceso->fecha_habilitado = now();
    //                         $proceso->save();
    //                     }
    //                 }
    //             }
    //         } else if ($etapa == "2") {

    //             $info->estado = 2;
    //             $info->fecha_fin = now();
    //             $info->user_id = Auth::id();
    //             $info->save();

    //             switch ($TipoProceso) {
    //                 case 'destapada':
    //                 case 'prolongacion':
    //                     $this->intentarHabilitarAlambrada($info);
    //                     break;

    //                 case 'alambrada':
    //                     $this->validarYHabilitarPorPiso($proyecto, $manzana, $piso, $info, 'alambrada', 'aparateada');
    //                     break;
    //                 case 'aparateada':
    //                     $fase2 = DB::table('proyectos_casas_detalle')
    //                         ->join('procesos_proyectos', 'proyectos_casas_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
    //                         ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', ['aparateada fase 2'])
    //                         ->where('proyectos_casas_detalle.manzana', $manzana)
    //                         ->where('proyectos_casas_detalle.proyecto_casa_id', $proyecto->id)
    //                         ->exists();

    //                     $siguienteProceso = $fase2 ? 'aparateada fase 2' : 'pruebas';

    //                     $this->validarYHabilitarPorPiso($proyecto, $manzana, $piso, $info, 'aparateada', $siguienteProceso);
    //                     break;

    //                 case 'aparateada fase 2':
    //                     $this->validarYHabilitarPorPiso($proyecto, $manzana, $info, $piso, 'aparateada fase 2', 'pruebas');
    //                     break;
    //                 case 'pruebas':
    //                     $this->confirmarPruebas($proyecto, $info, $manzana, $orden_proceso, $piso, $info->etapa);
    //                     break;

    //                 case 'retie':
    //                 case 'ritel':
    //                     $this->intentarHabilitarEntrega($info); // esta función no habilita entrega directamente, solo revisa
    //                     break;

    //                 case 'entrega':
    //                     break;

    //                 default:
    //                     return response()->json([
    //                         'success' => false,
    //                         'data' => 'ERROR, PROCESO NO EXISTENTE, COMUNICATE CON TI'
    //                     ]);
    //             }
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

    // //habilitar proceso alambrada
    // private function intentarHabilitarAlambrada($info)
    // {
    //     $manzana = $info->manzana;
    //     $proyectoId = $info->proyecto_casa_id;
    //     $piso = $info->piso;
    //     $casa = $info->casa;
    //     $etapa = $info->etapa;
    //     $procesos = ['destapada', 'prolongacion'];
    //     $aptosConfirmados = [];

    //     // 1. Buscar consecutivos confirmados (estado=2) por cada proceso
    //     foreach ($procesos as $proceso) {
    //         $aptosConfirmados[$proceso] = ProyectoCasaDetalle::where('manzana', $manzana)
    //             ->where('proyecto_casa_id', $proyectoId)
    //             ->where('piso', $piso)
    //             ->where('casa', $casa)
    //             ->where('etapa', $etapa)
    //             ->whereHas(
    //                 'proceso',
    //                 fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$proceso])
    //             )
    //             ->where('estado', 2)
    //             ->pluck('consecutivo_casa')
    //             ->toArray();


    //             info($aptosConfirmados);
    //     }

    //     // 2. Aptos confirmados en ambos procesos
    //     $aptosValidos = array_intersect(
    //         $aptosConfirmados['destapada'] ?? [],
    //         $aptosConfirmados['prolongacion'] ?? []
    //     );

    //     // 3. Habilitar alambrada SOLO para esos aptos válidos
    //     if (!empty($aptosValidos)) {
    //         $this->habilitarAptosEnAlambrada($manzana, $proyectoId, $piso, $aptosValidos, $etapa);
    //     }
    // }
    // //Función auxiliar para habilitar consecutivos en alambrada
    // private function habilitarAptosEnAlambrada($manzana, $proyectoId, $piso, $consecutivos, $etapa)
    // {
    //     // Obtener los aptos de alambrada pendientes (estado = 0)
    //     $validacion = ProyectoCasaDetalle::where('manzana', $manzana)
    //         ->where('proyecto_casa_id', $proyectoId)
    //         ->where('piso', $piso)
    //         ->where('etapa', $etapa)
    //         ->whereIn('consecutivo_casa', $consecutivos)
    //         ->whereHas(
    //             'proceso',
    //             fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada'])
    //         )
    //         ->where('estado', 0)
    //         ->get();

    //     // Si alguno requiere validación externa, detener
    //     if ($validacion->contains(fn($item) => $item->validacion == 1 && $item->estado_validacion == 0)) {
    //         return response()->json([
    //             'success' => true,
    //             'message' => 'espera validación externa',
    //         ], 200);
    //     }

    //     // Habilitar alambrada
    //     ProyectoCasaDetalle::where('manzana', $manzana)
    //         ->where('proyecto_casa_id', $proyectoId)
    //         ->where('piso', $piso)
    //         ->where('etapa', $etapa)
    //         ->where('consecutivo_casa', $consecutivos)
    //         ->whereHas(
    //             'proceso',
    //             fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada'])
    //         )
    //         ->where('estado', 0)
    //         ->update([
    //             'estado' => 1,
    //             'fecha_habilitado' => now()
    //         ]);
    // }

    // private function validarYHabilitarPorPiso($proyecto, $manzana, $piso, $info, $procesoOrigen, $procesoDestino)
    // {
    //     $etapa = $info->etapa;

    //     // 1. Consecutivos confirmados del procesoOrigen en el piso actual
    //     $aptosValidos = ProyectoCasaDetalle::where('manzana', $manzana)
    //         ->where('proyecto_casa_id', $proyecto->id) // 🔹 corregido
    //         ->where('piso', $piso)
    //         ->where('etapa', $etapa)
    //         ->whereHas(
    //             'proceso',
    //             fn($q) =>
    //             $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoOrigen)])
    //         )
    //         ->where('estado', 2) // confirmado
    //         ->pluck('consecutivo_casa')
    //         ->toArray();

    //     // 2. Piso siguiente
    //     $pisoSiguiente = $piso;

    //     // 3. Llamar la función pasando SOLO esos consecutivos, pero para el piso siguiente
    //     $this->habilitarPorConsecutivos($proyecto, $manzana, $pisoSiguiente, $info, $procesoDestino, $aptosValidos);
    // }

    // //Función auxiliar para habilitar consecutivos en el procesoDestino
    // private function habilitarPorConsecutivos($proyecto, $manzana, $piso, $info, $procesoDestino, $consecutivos)
    // {
    //     if (empty($consecutivos)) return;

    //     $verValidacion = ProyectoCasaDetalle::where('manzana', $manzana)
    //         ->where('proyecto_casa_id', $proyecto->id)
    //         ->where('piso', $piso)
    //         ->where('etapa', $info->etapa)
    //         ->whereHas(
    //             'proceso',
    //             fn($q) =>
    //             $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoDestino)])
    //         )
    //         ->get();

    //     // Verificar si todos los aptos de ese proceso están pendientes de validación
    //     $todosPendientes = $verValidacion->every(
    //         fn($apt) => $apt->validacion == 1 && $apt->estado_validacion == 0
    //     );

    //     if ($todosPendientes) {
    //         return; // espera validación externa
    //     }

    //     // 3. Actualizar SOLO los consecutivos válidos en el piso destino
    //     ProyectoCasaDetalle::where('manzana', $manzana)
    //         ->where('proyecto_casa_id', $proyecto->id) // 🔹 corregido
    //         ->where('piso', $piso)
    //         ->where('etapa', $info->etapa)
    //         ->whereIn('consecutivo_casa', $consecutivos)
    //         ->whereHas(
    //             'proceso',
    //             fn($q) =>
    //             $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoDestino)])
    //         )
    //         ->where('estado', 0) // pendiente
    //         ->update([
    //             'estado' => 1,
    //             'fecha_habilitado' => now()
    //         ]);
    // }

    // private function confirmarPruebas($proyecto, $info, $manzana, $orden_proceso, $piso, $etapa)
    // {

    //     // Confirmar todo el piso pruebas
    //     $aptosDelPiso = ProyectoCasaDetalle::where('manzana', $manzana)
    //         ->where('orden_proceso', $orden_proceso)
    //         ->where('proyecto_casa_id', $proyecto->id)
    //         ->where('piso', $piso)
    //         ->where('etapa', $etapa)
    //         ->first();

    //     if ($aptosDelPiso->estado == 2) {
    //         // Validar y habilitar procesos dependientes
    //         $this->validarYHabilitarRetieYRitel($proyecto, $info, $manzana, $piso, 'retie');
    //         $this->validarYHabilitarRetieYRitel($proyecto, $info, $manzana, $piso, 'ritel');
    //     }
    // }

    // private function validarYHabilitarRetieYRitel($proyecto, $info, $manzana, $piso, $procesoNombre)
    // {
    //     $etapa = $info->etapa;
    //     $casa = $info->casa;

    //     $verValidacion = DB::table('proyectos_casas_detalle')
    //         ->join('procesos_proyectos', 'proyectos_casas_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
    //         ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [strtolower($procesoNombre)])
    //         ->where('proyectos_casas_detalle.manzana', $manzana)
    //         ->where('proyectos_casas_detalle.proyecto_casa_id', $proyecto->id)
    //         ->where('proyectos_casas_detalle.piso', $piso)
    //         ->where('proyectos_casas_detalle.casa', $casa)
    //         ->where('proyectos_casas_detalle.etapa', $etapa)
    //         ->first();

    //     // ✅ VERIFICAR SI EXISTE ANTES DE ACCEDER A PROPIEDADES
    //     if (!$verValidacion) {
    //         info("Proceso {$procesoNombre} no encontrado para casa {$casa}, piso {$piso}");
    //         return;
    //     }

    //     // ✅ Lógica: Si necesita validación (validacion = 1) y NO está validado (estado_validacion = 0)
    //     if ($verValidacion->validacion == 1 && $verValidacion->estado_validacion == 0) {
    //         info("Proceso {$procesoNombre} espera validación externa - NO se habilita");
    //         return; // espera validación externa, no hacer nada
    //     }

    //     // ✅ Si NO necesita validación O YA está validado, habilitar el proceso
    //     info("Habilitando proceso {$procesoNombre}");
    //     ProyectoCasaDetalle::where('manzana', $manzana)
    //         ->where('proyecto_casa_id', $proyecto->id)
    //         ->where('piso', $piso)
    //         ->where('etapa', $etapa)
    //         ->where('casa', $casa)
    //         ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoNombre)]))
    //         ->where('estado', 0) // Solo habilitar si está en estado 0
    //         ->update([
    //             'estado' => 1,
    //             'fecha_habilitado' => now(),
    //             'estado_validacion' => 1, // Marcar como validado
    //             'fecha_validacion' => now()
    //         ]);
    // }

    //     private function intentarHabilitarEntrega($info)
    // {
    //     $manzana = $info->manzana;
    //     $proyectoId = $info->proyecto_casa_id;
    //     $piso = $info->piso;
    //     $casa = $info->casa;
    //     $etapa = $info->etapa;
    //     $procesos =  ['retie', 'ritel'];
    //     $aptosConfirmados = [];

    //     // 1. Buscar consecutivos confirmados (estado=2) por cada proceso
    //     foreach ($procesos as $proceso) {
    //         $aptosConfirmados[$proceso] = ProyectoCasaDetalle::where('manzana', $manzana)
    //             ->where('proyecto_casa_id', $proyectoId)
    //             ->where('piso', $piso)
    //             ->where('casa', $casa)
    //             ->where('etapa', $etapa)
    //             ->whereHas(
    //                 'proceso',
    //                 fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$proceso])
    //             )
    //             ->where('estado', 2)
    //             ->pluck('consecutivo_casa')
    //             ->toArray();

    //             info($aptosConfirmados);
    //     }

    //     // 2. Aptos confirmados en ambos procesos
    //     $aptosValidos = array_intersect(
    //         $aptosConfirmados['retie'] ?? [],
    //         $aptosConfirmados['ritel'] ?? []
    //     );

    //     // 3. Habilitar alambrada SOLO para esos aptos válidos
    //     if (!empty($aptosValidos)) {
    //         $this->intentarHabilitarEntregaAUX($manzana, $proyectoId, $piso, $aptosValidos, $etapa);
    //     }
    // }


    //   private function intentarHabilitarEntregaAUX($manzana, $proyectoId, $piso, $consecutivos, $etapa)
    // {
    //     // Obtener los aptos de alambrada pendientes (estado = 0)
    //     $validacion = ProyectoCasaDetalle::where('manzana', $manzana)
    //         ->where('proyecto_casa_id', $proyectoId)
    //         ->where('piso', $piso)
    //         ->where('etapa', $etapa)
    //         ->whereIn('consecutivo_casa', $consecutivos)
    //         ->whereHas(
    //             'proceso',
    //             fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['entrega'])
    //         )
    //         ->where('estado', 0)
    //         ->get();

    //     // Si alguno requiere validación externa, detener
    //     if ($validacion->contains(fn($item) => $item->validacion == 1 && $item->estado_validacion == 0)) {
    //         return response()->json([
    //             'success' => true,
    //             'message' => 'espera validación externa',
    //         ], 200);
    //     }

    //     // Habilitar alambrada
    //     ProyectoCasaDetalle::where('manzana', $manzana)
    //         ->where('proyecto_casa_id', $proyectoId)
    //         ->where('piso', $piso)
    //         ->where('etapa', $etapa)
    //         ->where('consecutivo_casa', $consecutivos)
    //         ->whereHas(
    //             'proceso',
    //             fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['entrega'])
    //         )
    //         ->where('estado', 0)
    //         ->update([
    //             'estado' => 1,
    //             'fecha_habilitado' => now()
    //         ]);
    // }

    /* ********************************************************************************************************************************** */





    public function confirmarCasas($id)
    {
        DB::beginTransaction();

        try {
            $info = ProyectoCasaDetalle::findOrFail($id);
            $proyecto = ProyectoCasa::findOrFail($info->proyecto_casa_id);
            
            $this->procesarConfirmacion($info, $proyecto);

            DB::commit();
            return $this->responder(true, $info);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responder(false, null, 'Error al confirmar casa: ' . $e->getMessage(), 500);
        }
    }

    private function procesarConfirmacion($info, $proyecto)
    {
        $info->estado = ProcesoEstados::CONFIRMADO;
        $info->fecha_fin = now();
        $info->user_id = Auth::id();
        $info->save();

        if ($info->etapa == 1) {
            $this->procesarEtapa1($info, $proyecto);
        } else if ($info->etapa == 2) {
            $this->procesarEtapa2($info, $proyecto);
        }
    }

    private function procesarEtapa1($info, $proyecto)
    {
        $manzana = $info->manzana;
        $orden_proceso = $info->orden_proceso;
        $piso = $info->piso;
        $etapa = $info->etapa;
        $casa = $info->casa;

        // Buscar el siguiente proceso en la misma etapa
        $siguiente = ProyectoCasaDetalle::where('proyecto_casa_id', $proyecto->id)
            ->where('manzana', $manzana)
            ->where('etapa', $etapa)
            ->where('casa', $casa)
            ->where('orden_proceso', $orden_proceso + 1)
            ->first();

        if ($siguiente) {
            // Habilitar el siguiente proceso
            if ($siguiente->estado == ProcesoEstados::PENDIENTE) {
                $siguiente->estado = ProcesoEstados::HABILITADO;
                $siguiente->fecha_habilitado = now();
                $siguiente->save();
                
                $this->logProceso('Proceso habilitado en etapa 1', [
                    'proceso_id' => $siguiente->id,
                    'orden_proceso' => $siguiente->orden_proceso
                ]);
            }
        } else {
            // Último proceso de etapa 1, habilitar primeros procesos de etapa 2
            $this->habilitarInicioEtapa2($proyecto, $manzana, $casa);
        }
    }

    private function habilitarInicioEtapa2($proyecto, $manzana, $casa)
    {
        $siguientesEtapa = ProyectoCasaDetalle::where('proyecto_casa_id', $proyecto->id)
            ->where('manzana', $manzana)
            ->where('etapa', 2)
            ->where('casa', $casa)
            ->where('piso', 1)
            ->whereIn('orden_proceso', [1, 2])
            ->get();

        foreach ($siguientesEtapa as $proceso) {
            if ($proceso->estado == ProcesoEstados::PENDIENTE) {
                $proceso->estado = ProcesoEstados::HABILITADO;
                $proceso->fecha_habilitado = now();
                $proceso->save();
                
                $this->logProceso('Proceso habilitado al iniciar etapa 2', [
                    'proceso_id' => $proceso->id,
                    'orden_proceso' => $proceso->orden_proceso
                ]);
            }
        }
    }

    private function procesarEtapa2($info, $proyecto)
    {
        $tipoProceso = strtolower($info->proceso->nombre_proceso);
        
        switch ($tipoProceso) {
            case TiposProceso::DESTAPADA:
            case TiposProceso::PROLONGACION:
                $this->intentarHabilitarAlambrada($info);
                break;
                
            case TiposProceso::ALAMBRADA:
                $this->validarYHabilitarPorPiso($proyecto, $info, TiposProceso::ALAMBRADA, TiposProceso::APARATEADA);
                break;
                
            case TiposProceso::APARATEADA:
                $this->procesarAparateada($info, $proyecto);
                break;
                
            case TiposProceso::APARATEADA_FASE2:
                $this->validarYHabilitarPorPiso($proyecto, $info, TiposProceso::APARATEADA_FASE2, TiposProceso::PRUEBAS);
                break;
                
            case TiposProceso::PRUEBAS:
                $this->confirmarPruebas($proyecto, $info);
                break;
                
            case TiposProceso::RETIE:
            case TiposProceso::RITEL:
                $this->intentarHabilitarEntrega($info);
                break;
                
            case TiposProceso::ENTREGA:
                // No action needed for entrega
                break;
                
            default:
                throw new \Exception('ERROR, PROCESO NO EXISTENTE, COMUNICATE CON TI');
        }
    }

    private function procesarAparateada($info, $proyecto)
    {
        $fase2 = DB::table('proyectos_casas_detalle')
            ->join('procesos_proyectos', 'proyectos_casas_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [TiposProceso::APARATEADA_FASE2])
            ->where('proyectos_casas_detalle.manzana', $info->manzana)
            ->where('proyectos_casas_detalle.proyecto_casa_id', $proyecto->id)
            ->exists();

        $siguienteProceso = $fase2 ? TiposProceso::APARATEADA_FASE2 : TiposProceso::PRUEBAS;
        $this->validarYHabilitarPorPiso($proyecto, $info, TiposProceso::APARATEADA, $siguienteProceso);
    }

    private function validarYHabilitarPorPiso($proyecto, $info, $procesoOrigen, $procesoDestino)
    {
        $aptosValidos = ProyectoCasaDetalle::where('manzana', $info->manzana)
            ->where('proyecto_casa_id', $proyecto->id)
            ->where('piso', $info->piso)
            ->where('etapa', $info->etapa)
            ->whereHas('proceso', function($q) use ($procesoOrigen) {
                $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoOrigen)]);
            })
            ->where('estado', ProcesoEstados::CONFIRMADO)
            ->pluck('consecutivo_casa')
            ->toArray();

        $this->habilitarPorConsecutivos(
            $proyecto->id,
            $info->manzana,
            $info->piso,
            $info->etapa,
            $procesoDestino,
            $aptosValidos
        );
    }

    private function intentarHabilitarAlambrada($info)
    {
        $procesos = [TiposProceso::DESTAPADA, TiposProceso::PROLONGACION];
        $aptosConfirmados = $this->obtenerProcesosConfirmados(
            $info->manzana,
            $info->proyecto_casa_id,
            $info->piso,
            $info->casa,
            $info->etapa,
            $procesos
        );

        $aptosValidos = array_intersect(
            $aptosConfirmados[TiposProceso::DESTAPADA] ?? [],
            $aptosConfirmados[TiposProceso::PROLONGACION] ?? []
        );

        if (!empty($aptosValidos)) {
            $this->habilitarProcesosPorConsecutivos(
                $info->manzana,
                $info->proyecto_casa_id,
                $info->piso,
                $info->etapa,
                TiposProceso::ALAMBRADA,
                $aptosValidos
            );
        }
    }

    private function obtenerProcesosConfirmados($manzana, $proyectoId, $piso, $casa, $etapa, array $procesos)
    {
        $resultados = [];
        
        foreach ($procesos as $proceso) {
            $resultados[$proceso] = ProyectoCasaDetalle::where('manzana', $manzana)
                ->where('proyecto_casa_id', $proyectoId)
                ->where('piso', $piso)
                ->where('casa', $casa)
                ->where('etapa', $etapa)
                ->whereHas('proceso', function($q) use ($proceso) {
                    $q->whereRaw('LOWER(nombre_proceso) = ?', [$proceso]);
                })
                ->where('estado', ProcesoEstados::CONFIRMADO)
                ->pluck('consecutivo_casa')
                ->toArray();
        }
        
        return $resultados;
    }

    private function habilitarPorConsecutivos($proyectoId, $manzana, $piso, $etapa, $procesoDestino, array $consecutivos)
    {
        if (empty($consecutivos)) return false;

        return $this->habilitarProcesosPorConsecutivos(
            $manzana,
            $proyectoId,
            $piso,
            $etapa,
            $procesoDestino,
            $consecutivos
        );
    }

    private function habilitarProcesosPorConsecutivos($manzana, $proyectoId, $piso, $etapa, $procesoDestino, array $consecutivos)
    {
        if (empty($consecutivos)) return false;

        $query = ProyectoCasaDetalle::where('manzana', $manzana)
            ->where('proyecto_casa_id', $proyectoId)
            ->where('piso', $piso)
            ->where('etapa', $etapa)
            ->whereIn('consecutivo_casa', $consecutivos)
            ->whereHas('proceso', function($q) use ($procesoDestino) {
                $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoDestino)]);
            })
            ->where('estado', ProcesoEstados::PENDIENTE);

        // Verificar validaciones pendientes
        $pendientesValidacion = $query->clone()
            ->where('validacion', 1)
            ->where('estado_validacion', 0)
            ->exists();

        if ($pendientesValidacion) {
            $this->logProceso('Validación externa pendiente', [
                'proceso_destino' => $procesoDestino,
                'consecutivos' => $consecutivos
            ]);
            return false;
        }

        // Habilitar procesos
        $result = $query->update([
            'estado' => ProcesoEstados::HABILITADO,
            'fecha_habilitado' => now()
        ]);

        if ($result) {
            $this->logProceso('Procesos habilitados', [
                'proceso_destino' => $procesoDestino,
                'consecutivos' => $consecutivos,
                'afectados' => $result
            ]);
        }

        return $result;
    }

    private function confirmarPruebas($proyecto, $info)
    {
        $aptosDelPiso = ProyectoCasaDetalle::where('manzana', $info->manzana)
            ->where('orden_proceso', $info->orden_proceso)
            ->where('proyecto_casa_id', $proyecto->id)
            ->where('piso', $info->piso)
            ->where('etapa', $info->etapa)
            ->first();

        if ($aptosDelPiso->estado == ProcesoEstados::CONFIRMADO) {
            $this->validarYHabilitarRetieYRitel($proyecto, $info, TiposProceso::RETIE);
            $this->validarYHabilitarRetieYRitel($proyecto, $info, TiposProceso::RITEL);
        }
    }

    private function validarYHabilitarRetieYRitel($proyecto, $info, $procesoNombre)
    {
        $verValidacion = DB::table('proyectos_casas_detalle')
            ->join('procesos_proyectos', 'proyectos_casas_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [strtolower($procesoNombre)])
            ->where('proyectos_casas_detalle.manzana', $info->manzana)
            ->where('proyectos_casas_detalle.proyecto_casa_id', $proyecto->id)
            ->where('proyectos_casas_detalle.piso', $info->piso)
            ->where('proyectos_casas_detalle.casa', $info->casa)
            ->where('proyectos_casas_detalle.etapa', $info->etapa)
            ->first();

        if (!$verValidacion) {
            $this->logProceso("Proceso {$procesoNombre} no encontrado", [
                'casa' => $info->casa,
                'piso' => $info->piso
            ]);
            return;
        }

        if ($verValidacion->validacion == 1 && $verValidacion->estado_validacion == 0) {
            $this->logProceso("Proceso {$procesoNombre} espera validación externa");
            return;
        }

        $actualizados = ProyectoCasaDetalle::where('manzana', $info->manzana)
            ->where('proyecto_casa_id', $proyecto->id)
            ->where('piso', $info->piso)
            ->where('etapa', $info->etapa)
            ->where('casa', $info->casa)
            ->whereHas('proceso', function($q) use ($procesoNombre) {
                $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoNombre)]);
            })
            ->where('estado', ProcesoEstados::PENDIENTE)
            ->update([
                'estado' => ProcesoEstados::HABILITADO,
                'fecha_habilitado' => now(),
                'estado_validacion' => 1,
                'fecha_validacion' => now()
            ]);

        if ($actualizados > 0) {
            $this->logProceso("Proceso {$procesoNombre} habilitado", [
                'afectados' => $actualizados
            ]);
        }
    }

    private function intentarHabilitarEntrega($info)
    {
        $procesos = [TiposProceso::RETIE, TiposProceso::RITEL];
        $aptosConfirmados = $this->obtenerProcesosConfirmados(
            $info->manzana,
            $info->proyecto_casa_id,
            $info->piso,
            $info->casa,
            $info->etapa,
            $procesos
        );

        $aptosValidos = array_intersect(
            $aptosConfirmados[TiposProceso::RETIE] ?? [],
            $aptosConfirmados[TiposProceso::RITEL] ?? []
        );

        if (!empty($aptosValidos)) {
            $this->habilitarProcesosPorConsecutivos(
                $info->manzana,
                $info->proyecto_casa_id,
                $info->piso,
                $info->etapa,
                TiposProceso::ENTREGA,
                $aptosValidos
            );
        }
    }

    private function logProceso($mensaje, $contexto = [], $nivel = 'info')
    {
        $contextoBase = [
            'user_id' => Auth::id(),
            'timestamp' => now()->toISOString()
        ];
        
        Log::$nivel($mensaje, array_merge($contextoBase, $contexto));
    }

    private function responder($success, $data = null, $message = null, $code = 200)
    {
        $response = ['success' => $success];
        
        if ($data) $response['data'] = $data;
        if ($message) $response['message'] = $message;
        if (!$success && !$message) $response['message'] = 'Operación fallida';
        
        return response()->json($response, $code);
    }

}

