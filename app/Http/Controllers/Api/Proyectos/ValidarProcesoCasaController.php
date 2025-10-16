<?php

namespace App\Http\Controllers\Api\Proyectos;

use App\Http\Controllers\Controller;
use App\Models\ProcesosProyectos;
use App\Models\ProyectoCasa;
use App\Models\ProyectoCasaDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ValidarProcesoCasaController extends Controller
{
    public function validarProcesoCasas(Request $request)
    {
        DB::beginTransaction();

        try {

            $request->validate([
                'manzana' => 'required|string',
                'proyecto' => 'required',
                'casa_id' => 'required|integer|min:1',
            ]);

            //detalle del proceso a validar
            $info = ProyectoCasaDetalle::where('id', $request->casa_id)->first();
            //detalle del proyecto
            $proyecto = ProyectoCasa::findOrFail($info->proyecto_casa_id);


            $proyecto =  $request->proyecto;
            $manzana = $info->manzana;

            $TipoProceso = strtolower(ProcesosProyectos::where('id', $info->procesos_proyectos_id)->value('nombre_proceso'));

            switch ($TipoProceso) {
                case 'destapada':
                    $this->intentarHabilitarDestapaYProlongacion($info);
                    break;
                case 'prolongacion':
                    $this->intentarHabilitarDestapaYProlongacion($info);
                    break;
                case 'alambrada':
                    $this->intentarHabilitarAlambrada($info);
                    break;

                case 'aparateada':
                    $this->validarYHabilitarPorPiso($info, 'alambrada', 'aparateada');
                    break;

                case 'aparateada fase 2':
                    $this->validarYHabilitarPorPiso($info, 'aparateada', 'aparateada fase 2');
                    break;

                case 'pruebas':
                    $fase2 = DB::table('proyectos_casas_detalle')
                        ->join('procesos_proyectos', 'proyectos_casas_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
                        ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', ['aparateada fase 2'])
                        ->where('proyectos_casas_detalle.manzana', $manzana)
                        ->where('proyectos_casas_detalle.etapa', 2)
                        ->where('proyectos_casas_detalle.proyecto_casa_id', $proyecto)
                        ->exists();
                    $siguienteProceso = $fase2 ? 'aparateada fase 2' : 'aparateada';
                    $this->validarYHabilitarPorPiso($info, $siguienteProceso, 'pruebas');
                    break;

                case 'retie':
                    $this->validarYHabilitarPorPiso($info, 'pruebas', 'retie');
                    break;
                case 'ritel':
                    $this->validarYHabilitarPorPiso($info, 'pruebas', 'ritel');
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
        $manzana = $info->manzana;
        $proyectoId = $info->proyecto_casa_id;
        $piso = $info->piso;
        $casa = $info->casa;
        $etapa = $info->etapa;

        // Buscar el registro del proceso destapada en este piso/casa
        $destapada = ProyectoCasaDetalle::where('manzana', $manzana)
            ->where('proyecto_casa_id', $proyectoId)
            ->where('piso', $piso)
            ->where('casa', $casa)
            ->where('etapa', $etapa)
            ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['destapada']))
            ->first();

        // Buscar el registro del proceso prolongacion en este piso/casa
        $prolongacion = ProyectoCasaDetalle::where('manzana', $manzana)
            ->where('proyecto_casa_id', $proyectoId)
            ->where('piso', $piso)
            ->where('casa', $casa)
            ->where('etapa', $etapa)
            ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['prolongacion']))
            ->first();

        // Si ambos existen y están confirmados (estado = 2)
        if ($destapada && $destapada->estado == 2 && $prolongacion && $prolongacion->estado == 2) {
            $alambrada = ProyectoCasaDetalle::where('manzana', $manzana)
                ->where('proyecto_casa_id', $proyectoId)
                ->where('piso', $piso)
                ->where('casa', $casa)
                ->where('etapa', $etapa)
                ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada']))
                ->where('estado', 0)
                ->first();


            if ($alambrada) {
                // ✅ Habilitar y marcar validación
                $alambrada->estado = 1;
                $alambrada->fecha_habilitado = now();
                $alambrada->estado_validacion = 1;
                $alambrada->fecha_validacion = now();
                $alambrada->save();
            }
        } else {
            // ⚠️ No existe un alambrada en estado 0 → actualizar solo validación en todos los registros de alambrada
            ProyectoCasaDetalle::where('manzana', $manzana)
                ->where('proyecto_casa_id', $proyectoId)
                ->where('piso', $piso)
                ->where('casa', $casa)
                ->where('etapa', $etapa)
                ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada']))
                ->update([
                    'estado_validacion' => 1,
                    'fecha_validacion' => now()
                ]);

            DB::commit();
            throw new \Exception("No se puede habilitar el proceos, valida que este confirmado los proceos [destapda, prolongacion]. Solo se actualizó la validación.");
        }
    }

    private function validarYHabilitarPorPiso($info, $procesoRevisarValidacion, $procesoActual)
    {
        $manzana = $info->manzana;
        $proyectoId = $info->proyecto_casa_id;
        $piso = $info->piso;
        $casa = $info->casa;
        $etapa = $info->etapa;

        // 1. Obtener el proceso a revisar
        $procesoRevisar = ProyectoCasaDetalle::where('manzana', $manzana)
            ->where('proyecto_casa_id', $proyectoId)
            ->where('piso', $piso)
            ->where('casa', $casa)
            ->where('etapa', $etapa)
            ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoRevisarValidacion)]))
            ->first();


        // 2. Validar el estado del proceso a revisar
        if ($procesoRevisar->estado !== "2") {
            // Si no existe o no está confirmado (estado 2), solo validar el proceso actual
            $paraValidar = ProyectoCasaDetalle::where('manzana', $manzana)
                ->where('proyecto_casa_id', $proyectoId)
                ->where('piso', $piso)
                ->where('casa', $casa)
                ->where('etapa', $etapa)
                ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoActual)]))
                ->where('estado', 0)
                ->get();

            // Actualizar cada registro individualmente
            foreach ($paraValidar as $registro) {
                $registro->estado_validacion = 1;
                $registro->fecha_validacion = now();
                $registro->save();
            }

            DB::commit();
            throw new \Exception("No esta confirmado el proceso '{$procesoRevisarValidacion}'. Solo se actualizó el estado de validación en '{$procesoActual}'.");
        }

        // 3. Si el proceso a revisar está confirmado (estado 2), habilitar y validar el proceso actual
        $aptosActual = ProyectoCasaDetalle::where('manzana', $manzana)
            ->where('proyecto_casa_id', $proyectoId)
            ->where('piso', $piso)
            ->where('casa', $casa)
            ->where('etapa', $etapa)
            ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoActual)]))
            ->where('estado', 0)
            ->get();


        // Actualizar cada registro individualmente
        foreach ($aptosActual as $registro) {
            $registro->estado = 1;
            $registro->fecha_habilitado = now();
            $registro->estado_validacion = 1;
            $registro->fecha_validacion = now();
            $registro->save();
        }

        return true;
    }

    private function intentarHabilitarEntrega($info)
    {
        $torre = $info->torre;
        $proyectoId = $info->proyecto_id;
        $piso = $info->piso;

        // Buscar aptos confirmados por proceso
        $retieConfirmados = ProyectoCasaDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->where('estado', 2)
            ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['retie']))
            ->pluck('apartamento') // Usamos 'apartamento' como identificador, cámbialo si tu campo es diferente
            ->toArray();

        $ritelConfirmados = ProyectoCasaDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->where('estado', 2)
            ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ['ritel']))
            ->pluck('apartamento')
            ->toArray();

        // Obtener intersección de aptos confirmados en ambos procesos
        $aptosComunes = array_intersect($retieConfirmados, $ritelConfirmados);

        // Obtener aptos con proceso 'entrega' en estado 0
        $aptosAlambrada = ProyectoCasaDetalle::where('torre', $torre)
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
        ProyectoCasaDetalle::where('torre', $torre)
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

    private function intentarHabilitarDestapaYProlongacion($info)
    {

        $manzana = $info->manzana;
        $proyectoId = $info->proyecto_casa_id;
        $piso = $info->piso;
        $casa = $info->casa;
        $etapa = 1;
        $procesoActual = strtolower(ProcesosProyectos::where('id', $info->procesos_proyectos_id)->value('nombre_proceso'));

        // Determinar qué proceso de cimentación validar según el piso
        if ($piso == 1) {
            // Para piso 1: validar proceso "losa entre pisos etapa 1"
            $procesoCimentacion = 'losa entre pisos'; //13
        } else if ($piso == 2) {
            // Para piso 2: validar proceso "muros segundo piso"
            $procesoCimentacion = 'muros segundo piso';
        } else {
            throw new \Exception("Solo se permiten pisos 1 y 2 para validación de procesos de cimentación.");
        }


        // Buscar el registro del proceso de cimentación correspondiente
        $procesoCimentacionRegistro = ProyectoCasaDetalle::where('manzana', $manzana)
            ->where('proyecto_casa_id', $proyectoId)
            ->where('piso', $piso)
            ->where('casa', $casa)
            ->where('etapa', $etapa)
            ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [strtolower($procesoCimentacion)]))
            ->first();

        // Validar si el proceso de cimentación existe y está confirmado (estado = 2)
        if ($procesoCimentacionRegistro && $procesoCimentacionRegistro->estado == 2) {
            // ✅ Proceso de cimentación confirmado, habilitar destapada/prolongación
            $procesoActualRegistro = ProyectoCasaDetalle::where('manzana', $manzana)
                ->where('proyecto_casa_id', $proyectoId)
                ->where('piso', $piso)
                ->where('casa', $casa)
                ->where('etapa', $info->etapa)
                ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoActual]))
                ->where('estado', 0)
                ->first();

            if ($procesoActualRegistro) {
                // Habilitar y marcar validación
                $procesoActualRegistro->estado = 1;
                $procesoActualRegistro->fecha_habilitado = now();
                $procesoActualRegistro->estado_validacion = 1;
                $procesoActualRegistro->fecha_validacion = now();
                $procesoActualRegistro->save();

                return true;
            }
        } else {
            // ⚠️ Proceso de cimentación no confirmado, solo actualizar validación
            $procesosParaValidar = ProyectoCasaDetalle::where('manzana', $manzana)
                ->where('proyecto_casa_id', $proyectoId)
                ->where('piso', $piso)
                ->where('casa', $casa)
                ->where('etapa', $info->etapa)
                ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoActual]))
                ->where('estado', 0)
                ->get();


            // Actualizar cada registro individualmente
            foreach ($procesosParaValidar as $registro) {
                $registro->estado_validacion = 1;
                $registro->fecha_validacion = now();
                $registro->save();
            }

            DB::commit();
            throw new \Exception("No se puede habilitar el proceso '{$procesoActual}', el proceso de cimentación '{$procesoCimentacion}' no está confirmado. Solo se actualizó la validación.");
        }

        return false;
    }
}
