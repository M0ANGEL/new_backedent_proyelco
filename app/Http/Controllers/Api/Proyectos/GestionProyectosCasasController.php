<?php

namespace App\Http\Controllers\Api\Proyectos;

use App\Http\Controllers\Controller;
use App\Models\AnulacionApt;
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
    public function infoCasa(Request $request)
    {
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

    /* =================================================LOGICA DE MANEJO DE CONFIRMACION DE CASAS====================== */
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

        /* nuevo: calcular si la casa es de 1 o 2 pisos */
        $totalProcesosEtapa1 = ProyectoCasaDetalle::where('proyecto_casa_id', $proyecto->id)
            ->where('manzana', $manzana)
            ->where('casa', $casa)
            ->where('etapa', 1)
            ->count();

        $pisos = $totalProcesosEtapa1 > 4 ? 2 : 1;
        // === NUEVAS REGLAS SEGÚN PISOS ===

        // Si la casa es de 2 pisos
        if ($pisos == 2) {

            // Piso 1 -> habilitar destapada y prolongación cuando losa entre pisos esté confirmada
            if (
                strtolower($info->proceso->nombre_proceso) == 'losa entre pisos'
                && $info->estado == ProcesoEstados::CONFIRMADO
            ) {
                $procesosEtapa2Piso1 = ProyectoCasaDetalle::where('proyecto_casa_id', $proyecto->id)
                    ->where('manzana', $manzana)
                    ->where('casa', $casa)
                    ->where('etapa', 2)
                    ->where('piso', 1)
                    ->whereHas('proceso', function ($q) {
                        $q->whereIn(DB::raw('LOWER(nombre_proceso)'), ['destapada', 'prolongacion']);
                    })
                    ->where('estado', ProcesoEstados::PENDIENTE)
                    ->get();

                foreach ($procesosEtapa2Piso1 as $proceso) {
                    $proceso->estado = ProcesoEstados::HABILITADO;
                    $proceso->fecha_habilitado = now();
                    $proceso->save();
                    $this->logProceso('Habilitado destapada/prolongación en etapa 2 piso 1', [
                        'proceso_id' => $proceso->id
                    ]);
                }
            }

            // Piso 2 -> habilitar destapada y prolongación cuando muros segundo piso esté confirmado
            if (
                strtolower($info->proceso->nombre_proceso) == 'muros segundo piso'
                && $info->estado == ProcesoEstados::CONFIRMADO
            ) {
                $procesosEtapa2Piso2 = ProyectoCasaDetalle::where('proyecto_casa_id', $proyecto->id)
                    ->where('manzana', $manzana)
                    ->where('casa', $casa)
                    ->where('etapa', 2)
                    ->where('piso', 2)
                    ->whereHas('proceso', function ($q) {
                        $q->whereIn(DB::raw('LOWER(nombre_proceso)'), ['destapada', 'prolongacion']);
                    })
                    ->where('estado', ProcesoEstados::PENDIENTE)
                    ->get();

                foreach ($procesosEtapa2Piso2 as $proceso) {
                    $proceso->estado = ProcesoEstados::HABILITADO;
                    $proceso->fecha_habilitado = now();
                    $proceso->save();
                    $this->logProceso('Habilitado destapada/prolongación en etapa 2 piso 2', [
                        'proceso_id' => $proceso->id
                    ]);
                }
            }
        }

        // Si la casa es de 1 piso
        if ($pisos == 1) {
            // Cuando se cumpla el último proceso de etapa 1
            if (!$siguiente && $info->estado == ProcesoEstados::CONFIRMADO) {
                $procesosEtapa2Piso1 = ProyectoCasaDetalle::where('proyecto_casa_id', $proyecto->id)
                    ->where('manzana', $manzana)
                    ->where('casa', $casa)
                    ->where('etapa', 2)
                    ->where('piso', 1)
                    ->whereHas('proceso', function ($q) {
                        $q->whereIn(DB::raw('LOWER(nombre_proceso)'), ['destapada', 'prolongacion']);
                    })
                    ->where('estado', ProcesoEstados::PENDIENTE)
                    ->get();

                foreach ($procesosEtapa2Piso1 as $proceso) {
                    $proceso->estado = ProcesoEstados::HABILITADO;
                    $proceso->fecha_habilitado = now();
                    $proceso->save();
                    $this->logProceso('Habilitado destapada/prolongación en etapa 2 piso 1 (casa 1 piso)', [
                        'proceso_id' => $proceso->id
                    ]);
                }
            }
        }

        // Lógica original de habilitar el siguiente proceso en etapa 1
        if ($siguiente) {
            if ($siguiente->estado == ProcesoEstados::PENDIENTE) {
                $siguiente->estado = ProcesoEstados::HABILITADO;
                $siguiente->fecha_habilitado = now();
                $siguiente->save();

                $this->logProceso('Proceso habilitado en etapa 1', [
                    'proceso_id' => $siguiente->id,
                    'orden_proceso' => $siguiente->orden_proceso
                ]);
            }
        }/*  else {
            $this->habilitarInicioEtapa2($proyecto, $manzana, $casa);
        } */
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
                info("entro a pruebas");
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
            ->whereHas('proceso', function ($q) use ($procesoOrigen) {
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
                ->whereHas('proceso', function ($q) use ($proceso) {
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
            ->whereHas('proceso', function ($q) use ($procesoDestino) {
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
        info("proyecto");
        info($proyecto);
        info("info");
        info($info);
        $aptosDelPiso = ProyectoCasaDetalle::where('manzana', $info->manzana)
            ->where('orden_proceso', $info->orden_proceso)
            ->where('proyecto_casa_id', $proyecto->id)
            ->where('piso', $info->piso)
            ->where('casa', $info->casa)
            ->where('etapa', $info->etapa)
            ->first();

        if ($aptosDelPiso->estado == ProcesoEstados::CONFIRMADO) {
            info("se trata de habilitar retie y ritel");

            $this->validarYHabilitarRetieYRitel($proyecto, $info, TiposProceso::RETIE);
            $this->validarYHabilitarRetieYRitel($proyecto, $info, TiposProceso::RITEL);
        }
    }

    private function validarYHabilitarRetieYRitel($proyecto, $info, $procesoNombre)
    {
        info($proyecto);
        info($info);
        info($procesoNombre);
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
            ->whereHas('proceso', function ($q) use ($procesoNombre) {
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

    /* ============================================================================================================ */

    public function InformeDetalladoProyectosCasas($id)
    {
        $proyectoId = $id;

        if (!$proyectoId) {
            return response()->json([
                'success' => false,
                'message' => 'ID de proyecto no proporcionado.',
            ], 400);
        }

        // Obtener listado de nombres de manzana
        $torresConNombre = DB::table('nombrexmanzana')
            ->where('proyectos_casas_id', $proyectoId)
            ->pluck('nombre_manzana', 'manzana')
            ->toArray();

        // Traer detalles con piso real
        $detalles = DB::table('proyectos_casas_detalle')
            ->join('procesos_proyectos', 'proyectos_casas_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
            ->select(
                'proyectos_casas_detalle.manzana',
                'proyectos_casas_detalle.estado',
                'proyectos_casas_detalle.etapa',
                'proyectos_casas_detalle.piso',
                'procesos_proyectos.nombre_proceso as proceso',
                'proyectos_casas_detalle.orden_proceso as orden'
            )
            ->where('proyectos_casas_detalle.proyecto_casa_id', $proyectoId)
            ->get();

        if ($detalles->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron detalles para el proyecto.',
            ], 404);
        }

        // Agrupar por etapa + proceso + orden + piso
        $procesos = $detalles->groupBy(function ($item) {
            return $item->etapa . '||' . $item->proceso . '||' . $item->orden . '||' . $item->piso;
        });

        // Lista de manzanas
        $manzanas = $detalles->pluck('manzana')->unique()->sort()->values()->map(function ($codigoManzana) use ($torresConNombre) {
            return [
                'codigo' => $codigoManzana,
                'nombre' => $torresConNombre[$codigoManzana] ?? "manzana {$codigoManzana}"
            ];
        });

        $resultado = [];

        foreach ($procesos as $key => $itemsProceso) {
            [$etapa, $proceso, $orden, $piso] = explode('||', $key);

            $fila = [
                'proceso' => $proceso,
                'etapa'   => (int)$etapa,
                'piso'    => (int)$piso,
                'orden'   => (int)$orden,
            ];

            $totalGlobal = 0;
            $terminadosGlobal = 0;

            foreach ($manzanas as $manzana) {
                $codigo = $manzana['codigo'];
                $nombre = $manzana['nombre'];

                $filtrados = $itemsProceso->where('manzana', $codigo);
                $total = $filtrados->count();
                $terminados = $filtrados->where('estado', 2)->count();

                $porcentaje = $total > 0 ? round(($terminados / $total) * 100, 1) : 0;

                $fila[$nombre] = "{$terminados}/{$total} ({$porcentaje}%)";

                $totalGlobal += $total;
                $terminadosGlobal += $terminados;
            }

            $porcentajeGlobal = $totalGlobal > 0 ? round(($terminadosGlobal / $totalGlobal) * 100, 1) : 0;
            $fila["total"] = "{$terminadosGlobal}/{$totalGlobal} ({$porcentajeGlobal}%)";

            $resultado[] = $fila;
        }

        // Ordenar: primero etapa, luego orden, luego piso
        $resultado = collect($resultado)->sortBy([
            ['etapa', 'asc'],
            ['orden', 'asc'],
            ['piso', 'asc'],
        ])->values()->all();

        return response()->json([
            'success' => true,
            'data' => [
                'manzanas' => $manzanas->pluck('nombre'),
                'reporte' => $resultado,
                'proyecto_id' => $proyectoId
            ]
        ]);
    }


    public function CambioEstadosCasas(Request $request)
    {
        DB::beginTransaction();

        try {
            $info = ProyectoCasaDetalle::findOrFail($request->casa);

            $idsAfectados = [];
            $procesosAfectados = [];

            if ($info->etapa == 1) {
                // 🔹 Caso 1: si es etapa 1 → anular todos los procesos en etapa 1 (todos los pisos)
                $etapa1 = ProyectoCasaDetalle::where('proyecto_casa_id', $info->proyecto_casa_id)
                    ->where('manzana', $info->manzana)
                    ->where('casa', $info->casa)
                    ->where('etapa', 1)
                    ->where('estado', 2)
                    ->get();

                foreach ($etapa1 as $apt) {
                    $apt->estado = 1;
                    $apt->fecha_habilitado = now();
                    $apt->fecha_fin = null;
                    $apt->user_id = null;
                    $apt->update();

                    $idsAfectados[] = $apt->id;
                    $procesosAfectados[] = $apt;
                }

                // 🔹 Después, anular también todos los procesos en etapa 2 (todos los pisos)
                $etapa2 = ProyectoCasaDetalle::where('proyecto_casa_id', $info->proyecto_casa_id)
                    ->where('manzana', $info->manzana)
                    ->where('casa', $info->casa)
                    ->where('etapa', 2)
                    ->where('estado', 2)
                    ->get();

                foreach ($etapa2 as $apt) {
                    $apt->estado = 1;
                    $apt->fecha_habilitado = now();
                    $apt->fecha_fin = null;
                    $apt->user_id = null;
                    $apt->update();

                    $idsAfectados[] = $apt->id;
                    $procesosAfectados[] = $apt;
                }
            } elseif ($info->etapa == 2) {
                // 🔹 Caso 2: si es etapa 2 → anular todos los procesos de ese piso desde el actual hasta los siguientes
                $procesosEtapa2 = ProyectoCasaDetalle::where('proyecto_casa_id', $info->proyecto_casa_id)
                    ->where('manzana', $info->manzana)
                    ->where('casa', $info->casa)
                    ->where('piso', $info->piso) // se respeta el piso
                    ->where('etapa', 2)
                    ->where('estado', 2)
                    ->where('orden_proceso', '>=', $info->orden_proceso)
                    ->get();

                foreach ($procesosEtapa2 as $apt) {
                    $apt->estado = 1;
                    $apt->fecha_habilitado = now();
                    $apt->fecha_fin = null;
                    $apt->user_id = null;
                    $apt->update();

                    $idsAfectados[] = $apt->id;
                    $procesosAfectados[] = $apt;
                }
            }

            // 🔹 Guardar log de anulación
            if (!empty($idsAfectados)) {
                $LogCambioEstadoApt = new AnulacionApt();
                $LogCambioEstadoApt->motivo = "Casas: " . $request->detalle;
                $LogCambioEstadoApt->piso = (int) $info->piso;
                $LogCambioEstadoApt->apt = $request->casa;
                $LogCambioEstadoApt->fecha_confirmo = $info->fecha_fin;
                $LogCambioEstadoApt->userConfirmo_id = $info->user_id;
                $LogCambioEstadoApt->user_id = Auth::id();
                $LogCambioEstadoApt->proyecto_id = $info->proyecto_casa_id;
                $LogCambioEstadoApt->apt_afectados = json_encode($idsAfectados);
                $LogCambioEstadoApt->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $procesosAfectados // devolvemos procesos completos
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
