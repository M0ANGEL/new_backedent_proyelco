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

            // if ($info->validacion === 1 && $info->estado_validacion === 1) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => "Este piso ya fue validado",
            //     ], 500);
            // }

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
                    $fase2 = DB::table('proyecto_detalle')
                        ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
                        ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', ['aparateada fase 2'])
                        ->where('proyecto_detalle.torre', $torre)
                        ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                        ->exists();
                    $siguienteProceso = $fase2 ? 'aparateada fase 2' : 'aparateada';
                    $this->validarYHabilitarPorPiso($proyecto, $torre, $piso, $siguienteProceso, 'pruebas');
                    break;

                case 'retie':
                    $this->validarYHabilitarPorPiso($proyecto, $torre, $piso, 'pruebas', 'retie');
                    break;
                case 'ritel':
                    $this->validarYHabilitarPorPiso($proyecto, $torre, $piso, 'pruebas', 'ritel');
                    break;

                case 'entrega':
                    $this->intentarHabilitarEntrega($info);

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

        // Obtener los consecutivos confirmados (estado = 2) en cada proceso
        $destapada = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['destapada']))
            ->where('estado', 2)
            ->pluck('consecutivo')
            ->toArray();

        $prolongacion = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['prolongacion']))
            ->where('estado', 2)
            ->pluck('consecutivo')
            ->toArray();

        // Buscar consecutivos en común
        $comunes = array_intersect($destapada, $prolongacion);

        // Obtener todos los apartamentos en alambrada con estado = 0
        $alambrada = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada']))
            ->where('estado', 0)
            ->get();

        // Si hay comunes, habilitar solo esos
        if (!empty($comunes)) {
            foreach ($alambrada as $apt) {
                if (in_array($apt->consecutivo, $comunes)) {
                    $apt->estado = 1;
                    $apt->fecha_habilitado = now();
                }
                $apt->estado_validacion = 1;
                $apt->fecha_validacion = now();
                $apt->save();
            }
        } else {
            // No hay comunes: solo actualizar validación
            foreach ($alambrada as $apt) {
                $apt->estado_validacion = 1;
                $apt->fecha_validacion = now();
                $apt->save();
            }
            DB::commit();
            throw new \Exception("No hay apartamentos confirmados en común entre destapada y prolongación. Se actualizó solo la validación del proceso alambrada.");
        }
    }

    private function validarYHabilitarPorPiso($proyecto, $torre, $piso, $procesoRevisarValidacion, $procesoActual)
    {
        // 1. Obtener consecutivos confirmados del proceso a revisar
        $confirmados = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyecto->id)
            ->where('piso', $piso)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoRevisarValidacion)])
            )
            ->where('estado', 2)
            ->pluck('consecutivo') // o 'id' si usas otro identificador
            ->toArray();

        // 2. Validar que existan confirmados
        if (empty($confirmados)) {
            // Si no hay confirmados, validar todos los del proceso actual
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
            throw new \Exception("No hay apartamentos confirmados del proceso '{$procesoRevisarValidacion}'. Solo se actualizó el estado de validación en '{$procesoActual}'.");
        }

        // 3. Obtener todos los aptos del proceso actual en estado 0
        $aptosActual = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyecto->id)
            ->where('piso', $piso)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoActual)])
            )
            ->where('estado', 0)
            ->get();

        // 4. Recorrer y habilitar los que están en común
        foreach ($aptosActual as $apt) {
            if (in_array($apt->consecutivo, $confirmados)) {
                $apt->estado = 1;
                $apt->fecha_habilitado = now();
            }
            $apt->estado_validacion = 1;
            $apt->fecha_validacion = now();
            $apt->save();
        }
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

    private function intentarHabilitarEntrega($info)
    {
        $torre = $info->torre;
        $proyectoId = $info->proyecto_id;
        $piso = $info->piso;

        // Buscar aptos confirmados por proceso
        $retieConfirmados = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->where('estado', 2)
            ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['retie']))
            ->pluck('apartamento') // Usamos 'apartamento' como identificador, cámbialo si tu campo es diferente
            ->toArray();

        $ritelConfirmados = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->where('estado', 2)
            ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['ritel']))
            ->pluck('apartamento')
            ->toArray();

        // Obtener intersección de aptos confirmados en ambos procesos
        $aptosComunes = array_intersect($retieConfirmados, $ritelConfirmados);

        // Obtener aptos con proceso 'entrega' en estado 0
        $aptosAlambrada = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['entrega']))
            ->get();

        if (empty($aptosComunes)) {
            // Si no hay ningún apto que tenga retie y ritel confirmados
            foreach ($aptosAlambrada as $apt) {
                if ($apt->estado == 0) {
                    $apt->estado_validacion = 1;
                    $apt->fecha_validacion = now();
                    $apt->save();
                }
            }

            DB::commit();
            throw new \Exception("No hay apartamentos con ambos procesos (retie y ritel) confirmados. Solo se actualizó la validación.");
        }

        // Habilitar solo los aptos entrega que estén en la lista común
        ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['entrega']))
            ->whereIn('apartamento', $aptosComunes)
            ->where('estado', 0)
            ->update([
                'estado' => 1,
                'fecha_habilitado' => now()
            ]);

        // A los demás aptos alambrada, solo se les marca la validación
        foreach ($aptosAlambrada as $apt) {
            if (!in_array($apt->apartamento, $aptosComunes) && $apt->estado == 0) {
                $apt->estado_validacion = 1;
                $apt->fecha_validacion = now();
                $apt->save();
            }
        }
    }
}
