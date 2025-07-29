<?php

namespace App\Http\Controllers\Api\Proyectos;

use App\Http\Controllers\Controller;
use App\Models\CambioProcesoProyectos;
use App\Models\ProcesosProyectos;
use App\Models\Proyectos;
use App\Models\ProyectosDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VaidarProcesoController extends Controller
{

    public function validarProcesoNuevaLogica(Request $request)
    {
        DB::beginTransaction();

        try {

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
            $info =  ProyectosDetalle::where('torre', $torre)
                ->where('orden_proceso', $ordenProceso)
                ->where('proyecto_id', $proyecto)
                ->where('piso', $pisoActual)
                ->first();

            if ($info->validacion === 1 && $info->estado_validacion === 1) {
                return response()->json([
                    'success' => false,
                    'message' => "Este piso ya fue validado",
                ], 500);
            }

            $torre = $info->torre;
            $orden_proceso = (int) $info->orden_proceso;
            $piso = (int) $info->piso;

            $proyecto = Proyectos::findOrFail($info->proyecto_id);
            $TipoProceso = strtolower(ProcesosProyectos::where('id', $info->procesos_proyectos_id)->value('nombre_proceso'));

            switch ($TipoProceso) {
                case 'destapada':
                    $this->validarYHabilitarPorPiso($proyecto, $torre, $piso, 'fundida', 'destapada');
                    break;
                case 'prolongacion':
                    $this->validarYHabilitarPorPiso($proyecto, $torre, $piso, 'fundida', 'prolongacion');
                    break;
                case 'alambrada':
                    $this->intentarHabilitarAlambrada($info);
                    break;

                case 'aparateada':
                    $this->validarYHabilitarPorPiso($proyecto, $torre, $piso, 'alambrada', 'aparateada');
                    break;

                case 'aparateada fase 2':
                    $this->validarYHabilitarPorPiso($proyecto, $torre, $piso, 'aparateada', 'aparateada fase 2');
                    break;

                case 'pruebas':
                    $fase2 = ProcesosProyectos::whereRaw('LOWER(nombre_proceso) = ?', ['aparateada fase 2'])->exists();
                    $siguienteProceso = $fase2 ? 'aparateada fase 2' : 'aparateada';
                    $this->validarYHabilitarPorPiso($proyecto, $torre, $piso,$siguienteProceso, 'pruebas');
                    break;

                case 'retie':
                    $this->validarYHabilitarPorPiso($proyecto, $torre, $piso, 'pruebas', 'retie');
                    break;
                case 'ritel':
                    $this->validarYHabilitarPorPiso($proyecto, $torre, $piso, 'pruebas', 'ritel');
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
                'message' => $e->getMessage(),
                'error' => 'Error al confirmar apartamento'
            ], 500);
        }
    }

    private function intentarHabilitarAlambrada($info)
    {
        $torre = $info->torre;
        $proyectoId = $info->proyecto_id;
        $piso = $info->piso;

        $procesos = ['destapada', 'prolongacion'];

        foreach ($procesos as $proceso) {
            $aptos = ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyectoId)
                ->where('piso', $piso)
                ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$proceso]))
                ->get();

            if ($aptos->isEmpty()) {
                throw new \Exception("No hay apartamentos con el proceso '{$proceso}' en estado confirmado (2).");
            }

            $activarValidacion =  ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyectoId)
                ->where('piso', $piso)
                ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada']))
                ->where('estado', 0)
                ->get();

            $noConfirmados = $aptos->filter(fn($apt) => $apt->estado != 2);
            $noConfirmadosValidar = $activarValidacion->filter(fn($apt) => $apt->estado != 2);

            if ($noConfirmados->isNotEmpty()) {
                // Actualizamos la validación solo para los no confirmados
                foreach ($noConfirmadosValidar as $apt) {
                    $apt->estado_validacion = 1;
                    $apt->fecha_validacion = now();
                    $apt->save();
                }
                DB::commit();
                throw new \Exception("La habilitación del piso ha sido cancelada debido a que algunos apartamentos del proceso '{$proceso}' permanecen sin confirmar. Se ha procedido con la actualización del estado de validación.");
            }
        }

        // Si ambos procesos están completos, habilitar alambrada en ese piso
        ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada']))
            ->where('estado', 0)
            ->update([
                'estado' => 1,
                'fecha_habilitado' => now(),
                'estado_validacion' => 1,
                'fecha_validacion' => now(),
            ]);
    }

    private function validarYHabilitarPorPiso($proyecto, $torre, $piso, $procesoRevisarValidacion, $procesoActual)
    {
        // 1. Obtener todos los apartamentos del piso que tengan el proceso a revisar
        $aptos = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyecto->id)
            ->where('piso', $piso)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoRevisarValidacion)])
            )
            ->get();


        // 2. Validar que existan apartamentos con ese proceso
        if ($aptos->isEmpty()) {
            throw new \Exception("No hay apartamentos con el proceso '{$procesoRevisarValidacion}' en este piso.");
        }

        // 3. Verificar si hay alguno que no esté en estado 2
        $noConfirmados = $aptos->filter(fn($apt) => $apt->estado != 2);

        // 4. Si hay apartamentos no confirmados, actualizar solo estado de validación (NO habilitar proceso)
        if ($noConfirmados->isNotEmpty()) {
            // Buscar los apartamentos del proceso actual que están en estado 0
            $paraValidar = ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyecto->id)
                ->where('piso', $piso)
                ->whereHas(
                    'proceso',
                    fn($q) =>
                    $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoActual)])
                )
                ->where('estado', 0)
                ->get();

            foreach ($paraValidar as $apt) {
                $apt->estado_validacion = 1;
                $apt->fecha_validacion = now();
                $apt->save();
            }

            DB::commit();


            throw new \Exception("No se habilitó el piso porque algunos apartamentos del proceso '{$procesoRevisarValidacion}' no están confirmados. Solo se actualizó el estado de validación.");
        }

        // 5. Si todos los apartamentos están confirmados (estado 2), habilitar el proceso actual
        ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyecto->id)
            ->where('piso', $piso)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoActual)])
            )
            ->where('estado', 0)
            ->update([
                'estado' => 1,
                'fecha_habilitado' => now(),
                'estado_validacion' => 1,
                'fecha_validacion' => now(),
            ]);
    }


    private function intentarHabilitarPruebas($info)
    {
        $torre = $info->torre;
        $proyectoId = $info->proyecto_id;
        $piso = $info->piso;

        $procesos = ['destapada', 'prolongacion'];

        foreach ($procesos as $proceso) {
            $aptos = ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyectoId)
                ->where('piso', $piso)
                ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$proceso]))
                ->get();

            if ($aptos->isEmpty()) {
                throw new \Exception("No hay apartamentos con el proceso '{$proceso}' en estado confirmado (2).");
            }

            $activarValidacion =  ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyectoId)
                ->where('piso', $piso)
                ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada']))
                ->where('estado', 0)
                ->get();

            $noConfirmados = $aptos->filter(fn($apt) => $apt->estado != 2);
            $noConfirmadosValidar = $activarValidacion->filter(fn($apt) => $apt->estado != 2);

            if ($noConfirmados->isNotEmpty()) {
                // Actualizamos la validación solo para los no confirmados
                foreach ($noConfirmadosValidar as $apt) {
                    $apt->estado_validacion = 1;
                    $apt->fecha_validacion = now();
                    $apt->save();
                }
                DB::commit();
                throw new \Exception("La habilitación del piso ha sido cancelada debido a que algunos apartamentos del proceso '{$proceso}' permanecen sin confirmar. Se ha procedido con la actualización del estado de validación.");
            }
        }

        // Si ambos procesos están completos, habilitar alambrada en ese piso
        ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada']))
            ->where('estado', 0)
            ->update([
                'estado' => 1,
                'fecha_habilitado' => now()
            ]);
    }
}
