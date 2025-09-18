<?php

namespace App\Http\Controllers\Api\Proyectos;

use App\Http\Controllers\Controller;
use App\Models\ProcesosProyectos;
use App\Models\ProyectoCasa;
use App\Models\ProyectoCasaDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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


    /* ******************************************************************************************************************************* */
    //logica de gestion de manzanas encargado de obra

    public function confirmarCasas($id)
    {
        DB::beginTransaction();

        try {
            $info = ProyectoCasaDetalle::findOrFail($id);

            $manzana = $info->manzana;
            $orden_proceso =  $info->orden_proceso;
            $piso =  $info->piso;
            $etapa =  $info->etapa;
            $casa =  $info->casa;

            $proyecto = ProyectoCasa::findOrFail($info->proyecto_casa_id);
            $TipoProceso = strtolower(ProcesosProyectos::where('id', $info->procesos_proyectos_id)->value('nombre_proceso'));


            //etapa 1, todo lo que tiene que ver con fundicion
            if ($etapa == 1) {

                // Confirmar proceso actual
                $info->estado = 2; // confirmado
                $info->fecha_fin = now();
                $info->user_id = Auth::id();
                $info->save();

                // Buscar el siguiente proceso en la misma etapa
                $siguiente = ProyectoCasaDetalle::where('proyecto_casa_id', $proyecto->id)
                    ->where('manzana', $manzana)
                    ->where('etapa', $etapa)
                    ->where('casa', $casa)
                    ->where('orden_proceso', $orden_proceso + 1) // el inmediato siguiente
                    ->first();

                if ($siguiente) {
                    // habilitar el siguiente proceso
                    if ($siguiente->estado == 0) {
                        $siguiente->estado = 1;
                        $info->fecha_habilitado = now();
                        $siguiente->save();
                    }
                } else {
                    //cuando ya sea el ultimo proceso ed etapa 1, se habilita el primero de etapa 2
                    $siguientesEtapa = ProyectoCasaDetalle::where('proyecto_casa_id', $proyecto->id)
                        ->where('manzana', $manzana)
                        ->where('etapa', 2)
                        ->where('casa', $casa)
                        ->where('piso', 1)
                        ->whereIn('orden_proceso', [1, 2]) // procesos 1 y 2
                        ->get();

                    foreach ($siguientesEtapa as $proceso) {
                        if ($proceso->estado == 0) {
                            $proceso->estado = 1; // habilitado
                            $proceso->fecha_habilitado = now();
                            $proceso->save();
                        }
                    }
                }
            } else if ($etapa == "2") {

                $info->estado = 2;
                $info->fecha_fin = now();
                $info->user_id = Auth::id();
                $info->save();

                switch ($TipoProceso) {
                    case 'destapada':
                    case 'prolongacion':
                        $this->intentarHabilitarAlambrada($info);
                        break;

                    case 'alambrada':
                        $this->validarYHabilitarPorPiso($proyecto, $manzana, $piso, $info, 'alambrada', 'aparateada');
                        break;
                    case 'aparateada':
                        $fase2 = DB::table('proyectos_casas_detalle')
                            ->join('procesos_proyectos', 'proyectos_casas_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
                            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', ['aparateada fase 2'])
                            ->where('proyectos_casas_detalle.manzana', $manzana)
                            ->where('proyectos_casas_detalle.proyecto_casa_id', $proyecto->id)
                            ->exists();

                        $siguienteProceso = $fase2 ? 'aparateada fase 2' : 'pruebas';

                        $this->validarYHabilitarPorPiso($proyecto, $manzana, $piso, $info, 'aparateada', $siguienteProceso);
                        break;

                    case 'aparateada fase 2':
                        $this->validarYHabilitarPorPiso($proyecto, $manzana, $info, $piso, 'aparateada fase 2', 'pruebas');
                        break;
                    case 'pruebas':
                        $this->confirmarPruebas($proyecto, $info, $manzana, $orden_proceso, $piso, $info->etapa);
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
        $aptosDelPiso = ProyectoCasaDetalle::where('torre', $torre)
            ->where('orden_proceso', $orden_proceso)
            ->where('proyecto_id', $proyecto->id)
            ->where('piso', $piso)
            ->get();

        $confirmarInicioProceso = $aptosDelPiso->isNotEmpty() && $aptosDelPiso->every(fn($apt) => $apt->estado == 2);

        if ($confirmarInicioProceso) {
            // Habilitar siguiente piso fundida
            ProyectoCasaDetalle::where('torre', $torre)
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

            ProyectoCasaDetalle::where('torre', $torre)
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

    private function intentarHabilitarAlambrada($info)
    {
        $manzana = $info->manzana;
        $proyectoId = $info->proyecto_casa_id;
        $piso = $info->piso;
        $casa = $info->casa;
        $etapa = $info->etapa;
        $procesos = ['destapada', 'prolongacion'];
        $aptosConfirmados = [];

        // 1. Buscar consecutivos confirmados (estado=2) por cada proceso
        foreach ($procesos as $proceso) {
            $aptosConfirmados[$proceso] = ProyectoCasaDetalle::where('manzana', $manzana)
                ->where('proyecto_casa_id', $proyectoId)
                ->where('piso', $piso)
                ->where('casa', $casa)
                ->where('etapa', $etapa)
                ->whereHas(
                    'proceso',
                    fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$proceso])
                )
                ->where('estado', 2)
                ->pluck('consecutivo_casa')
                ->toArray();
        }

        // 2. Aptos confirmados en ambos procesos
        $aptosValidos = array_intersect(
            $aptosConfirmados['destapada'] ?? [],
            $aptosConfirmados['prolongacion'] ?? []
        );

        // 3. Habilitar alambrada SOLO para esos aptos válidos
        if (!empty($aptosValidos)) {
            $this->habilitarAptosEnAlambrada($manzana, $proyectoId, $piso, $aptosValidos, $etapa);
        }
    }
    //Función auxiliar para habilitar consecutivos en alambrada
    private function habilitarAptosEnAlambrada($manzana, $proyectoId, $piso, $consecutivos, $etapa)
    {
        // Obtener los aptos de alambrada pendientes (estado = 0)
        $validacion = ProyectoCasaDetalle::where('manzana', $manzana)
            ->where('proyecto_casa_id', $proyectoId)
            ->where('piso', $piso)
            ->where('etapa', $etapa)
            ->whereIn('consecutivo_casa', $consecutivos)
            ->whereHas(
                'proceso',
                fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada'])
            )
            ->where('estado', 0)
            ->get();

        // Si alguno requiere validación externa, detener
        if ($validacion->contains(fn($item) => $item->validacion == 1 && $item->estado_validacion == 0)) {
            return response()->json([
                'success' => true,
                'message' => 'espera validación externa',
            ], 200);
        }

        // Habilitar alambrada
        ProyectoCasaDetalle::where('manzana', $manzana)
            ->where('proyecto_casa_id', $proyectoId)
            ->where('piso', $piso)
            ->where('etapa', $etapa)
            ->where('consecutivo_casa', $consecutivos)
            ->whereHas(
                'proceso',
                fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada'])
            )
            ->where('estado', 0)
            ->update([
                'estado' => 1,
                'fecha_habilitado' => now()
            ]);
    }

    private function validarYHabilitarPorPiso($proyecto, $manzana, $piso, $info, $procesoOrigen, $procesoDestino)
    {
        $etapa = $info->etapa;

        // 1. Consecutivos confirmados del procesoOrigen en el piso actual
        $aptosValidos = ProyectoCasaDetalle::where('manzana', $manzana)
            ->where('proyecto_casa_id', $proyecto->id) // 🔹 corregido
            ->where('piso', $piso)
            ->where('etapa', $etapa)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoOrigen)])
            )
            ->where('estado', 2) // confirmado
            ->pluck('consecutivo_casa')
            ->toArray();

        // 2. Piso siguiente
        $pisoSiguiente = $piso;

        // 3. Llamar la función pasando SOLO esos consecutivos, pero para el piso siguiente
        $this->habilitarPorConsecutivos($proyecto, $manzana, $pisoSiguiente, $info, $procesoDestino, $aptosValidos);
    }

    //Función auxiliar para habilitar consecutivos en el procesoDestino
    private function habilitarPorConsecutivos($proyecto, $manzana, $piso, $info, $procesoDestino, $consecutivos)
    {
        if (empty($consecutivos)) return;

        $verValidacion = ProyectoCasaDetalle::where('manzana', $manzana)
            ->where('proyecto_casa_id', $proyecto->id)
            ->where('piso', $piso)
            ->where('etapa', $info->etapa)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoDestino)])
            )
            ->get();

        // Verificar si todos los aptos de ese proceso están pendientes de validación
        $todosPendientes = $verValidacion->every(
            fn($apt) => $apt->validacion == 1 && $apt->estado_validacion == 0
        );

        if ($todosPendientes) {
            return; // espera validación externa
        }

        // 3. Actualizar SOLO los consecutivos válidos en el piso destino
        ProyectoCasaDetalle::where('manzana', $manzana)
            ->where('proyecto_casa_id', $proyecto->id) // 🔹 corregido
            ->where('piso', $piso)
            ->where('etapa', $info->etapa)
            ->whereIn('consecutivo_casa', $consecutivos)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoDestino)])
            )
            ->where('estado', 0) // pendiente
            ->update([
                'estado' => 1,
                'fecha_habilitado' => now()
            ]);
    }

    private function intentarHabilitarEntrega($info)
    {
        $manzana = $info->manzana;
        $proyectoId = $info->proyecto_casa_id;
        $piso = $info->piso;
        $etapa = $info->etapa;
        $consecutivo = $info->consecutivo_casa;

        $procesos = ['retie', 'ritel'];

        foreach ($procesos as $proceso) {
            // Solo buscar el apartamento 1 en cada proceso
            $apto = ProyectoCasaDetalle::where('manzana', $manzana)
                ->where('proyecto_id', $proyectoId)
                ->where('piso', $piso)
                ->where('etapa', $etapa)
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
        ProyectoCasaDetalle::where('manzana', $manzana)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->where('etapa', $etapa)
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

    private function confirmarPruebas($proyecto, $info, $manzana, $orden_proceso, $piso, $etapa)
    {

        // Confirmar todo el piso pruebas
        $aptosDelPiso = ProyectoCasaDetalle::where('manzana', $manzana)
            ->where('orden_proceso', $orden_proceso)
            ->where('proyecto_casa_id', $proyecto->id)
            ->where('piso', $piso)
            ->where('etapa', $etapa)
            ->first();

        if ($aptosDelPiso->estado == 2) {
            // Validar y habilitar procesos dependientes
            $this->validarYHabilitarRetieYRitel($proyecto, $info, $manzana, $piso, 'retie');
            $this->validarYHabilitarRetieYRitel($proyecto, $info, $manzana, $piso, 'ritel');
        }
    }

    private function validarYHabilitarRetieYRitel($proyecto, $info, $manzana, $piso, $procesoNombre)
    {
        $etapa = $info->etapa;
        $casa = $info->casa;

        $verValidacion = DB::table('proyectos_casas_detalle')
            ->join('procesos_proyectos', 'proyectos_casas_detalle.orden_proceso', '=', 'procesos_proyectos.id')
            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [strtolower($procesoNombre)])
            ->where('proyectos_casas_detalle.manzana', $manzana)
            ->where('proyectos_casas_detalle.proyecto_casa_id', $proyecto->id)
            ->where('proyectos_casas_detalle.piso', $piso)
            ->where('proyectos_casas_detalle.casa', $casa)
            ->where('proyectos_casas_detalle.etapa', $etapa)
            ->first();

        // ✅ VERIFICAR SI EXISTE ANTES DE ACCEDER A PROPIEDADES
        if (!$verValidacion) {
            info("Proceso {$procesoNombre} no encontrado para casa {$casa}, piso {$piso}");
            return;
        }

        // ✅ Lógica: Si necesita validación (validacion = 1) y NO está validado (estado_validacion = 0)
        if ($verValidacion->validacion == 1 && $verValidacion->estado_validacion == 0) {
            info("Proceso {$procesoNombre} espera validación externa - NO se habilita");
            return; // espera validación externa, no hacer nada
        }

        // ✅ Si NO necesita validación O YA está validado, habilitar el proceso
        info("Habilitando proceso {$procesoNombre}");
        ProyectoCasaDetalle::where('manzana', $manzana)
            ->where('proyecto_casa_id', $proyecto->id)
            ->where('piso', $piso)
            ->where('etapa', $etapa)
            ->where('casa', $casa)
            ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoNombre)]))
            ->where('estado', 0) // Solo habilitar si está en estado 0
            ->update([
                'estado' => 1,
                'fecha_habilitado' => now(),
                'estado_validacion' => 1, // Marcar como validado
                'fecha_validacion' => now()
            ]);
    }

    /* ********************************************************************************************************************************** */
}
