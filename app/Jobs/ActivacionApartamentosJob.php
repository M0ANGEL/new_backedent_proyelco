<?php

namespace App\Jobs;

use App\Models\Proyectos;
use App\Models\ProyectosDetalle;
use App\Models\Festivos;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ActivacionApartamentosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $hoy = Carbon::now();

        // Omitir fines de semana y festivos
        if ($hoy->isWeekend() || Festivos::whereDate('festivo_fecha', $hoy->toDateString())->exists()) {
            Log::info("📅 Job detenido: Hoy es fin de semana o festivo ({$hoy->toDateString()})");
            return;
        }

        // Solo proyectos activos
        $proyectos = Proyectos::where('estado', 1)->get();
        Log::info("🔍 Iniciando activación. Total proyectos activos: " . $proyectos->count());

        foreach ($proyectos as $proyecto) {
            $this->procesarProyecto($proyecto, $hoy);
        }
    }

    private function procesarProyecto(Proyectos $proyecto, Carbon $hoy): void
    {
        $torres = ProyectosDetalle::where('proyecto_id', $proyecto->id)
            ->pluck('torre')
            ->unique()
            ->values();

        Log::info("🏢 Proyecto {$proyecto->id} ({$proyecto->nombre}) - Torres encontradas: " . $torres->implode(', '));

        foreach ($torres as $torre) {
            $this->procesarTorre($proyecto, $torre, $hoy);
        }
    }

    private function procesarTorre(Proyectos $proyecto, string $torre, Carbon $hoy): void
    {
        if ($this->procesoCompleto($proyecto->id, $torre, 'fundida')) {
            Log::info("✅ Fundida completa en Proyecto {$proyecto->id}, Torre {$torre}");

            $this->activarProcesosSimultaneos($proyecto, $torre, ['destapada', 'prolongacion'], $hoy);

            if ($this->procesosCompletos($proyecto->id, $torre, ['destapada', 'prolongacion'])) {
                Log::info("✅ Destapada y prolongacion completas → Activando alambrada");
                $this->activarApartamentos($proyecto, $torre, 'alambrada', $hoy);
            }

            if ($this->procesoCompleto($proyecto->id, $torre, 'alambrada')) {
                Log::info("✅ Alambrada completa → Activando aparateada");
                $this->activarApartamentos($proyecto, $torre, 'aparateada', $hoy);
            }

            if ($this->procesoCompleto($proyecto->id, $torre, 'aparateada')) {
                if ($this->tieneFase2($proyecto->id, $torre)) {
                    Log::info("🔄 Aparateada completa con fase 2 → Activando aparateada fase 2");
                    $this->activarApartamentos($proyecto, $torre, 'aparateada fase 2', $hoy);

                    if ($this->procesoCompleto($proyecto->id, $torre, 'aparateada fase 2')) {
                        Log::info("✅ Aparateada fase 2 completa → Activando pruebas");
                        $this->activarApartamentos($proyecto, $torre, 'pruebas', $hoy);
                    }
                } else {
                    Log::info("⚡ Aparateada completa sin fase 2 → Activando pruebas");
                    $this->activarApartamentos($proyecto, $torre, 'pruebas', $hoy);
                }
            }

            if ($this->procesoCompleto($proyecto->id, $torre, 'pruebas')) {
                Log::info("✅ Pruebas completas → Activando retie y ritel");
                $this->activarApartamentos($proyecto, $torre, 'retie', $hoy);
                $this->activarApartamentos($proyecto, $torre, 'ritel', $hoy);
            }
        } else {
            Log::info("⏳ Fundida NO completa en Proyecto {$proyecto->id}, Torre {$torre}");
        }
    }

    private function procesoCompleto(int $proyectoId, string $torre, string $proceso): bool
    {
        $proceso = strtolower(trim($proceso));

        $pisos = ProyectosDetalle::where('proyecto_detalle.proyecto_id', $proyectoId)
            ->where('proyecto_detalle.torre', $torre)
            ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$proceso])
            ->select('proyecto_detalle.piso')
            ->distinct()
            ->pluck('piso');

        foreach ($pisos as $piso) {
            $incompletosEnPiso = ProyectosDetalle::where('proyecto_detalle.proyecto_id', $proyectoId)
                ->where('proyecto_detalle.torre', $torre)
                ->where('proyecto_detalle.piso', $piso)
                ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
                ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$proceso])
                ->where('proyecto_detalle.estado', '!=', 2)
                ->count();

            if ($incompletosEnPiso > 0) {
                Log::warning("📋 Proceso '{$proceso}' Torre {$torre}, Piso {$piso} → Incompletos: {$incompletosEnPiso}");
                return false;
            }
        }

        Log::info("📋 Proceso '{$proceso}' en Torre {$torre} → TODOS los pisos completos");
        return true;
    }

    private function procesosCompletos(int $proyectoId, string $torre, array $procesos): bool
    {
        foreach ($procesos as $proceso) {
            if (!$this->procesoCompleto($proyectoId, $torre, $proceso)) {
                return false;
            }
        }
        return true;
    }

    private function activarProcesosSimultaneos(Proyectos $proyecto, string $torre, array $procesos, Carbon $hoy): void
    {
        foreach ($procesos as $proceso) {
            $this->activarApartamentos($proyecto, $torre, $proceso, $hoy);
        }
    }

    // private function activarApartamentos(Proyectos $proyecto, string $torre, string $proceso, Carbon $hoy): void
    // {
    //     $proceso = strtolower(trim($proceso));

    //     $aptosParaActivar = ProyectosDetalle::select('proyecto_detalle.*')
    //         ->where('proyecto_detalle.proyecto_id', $proyecto->id)
    //         ->where('proyecto_detalle.torre', $torre)
    //         ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
    //         ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$proceso])
    //         ->where('proyecto_detalle.estado', 0)
    //         ->orderBy('proyecto_detalle.piso')
    //         ->orderBy('proyecto_detalle.consecutivo')
    //         ->take($proyecto->activador_pordia_apt)
    //         ->get();

    //     Log::info("🚀 Intentando activar '{$proceso}' en Proyecto {$proyecto->id}, Torre {$torre} → Encontrados: " . $aptosParaActivar->count());

    //     foreach ($aptosParaActivar as $apto) {
    //         Log::info("    ➡ Apto {$apto->apartamento} (Piso {$apto->piso}) ID {$apto->id} → Estado actual: {$apto->estado}");
    //         $apto->update([
    //             'fecha_habilitado' => now(),
    //             'estado' => 1,
    //         ]);
    //         Log::info("    ✅ Activado Apto {$apto->apartamento} en '{$proceso}'");
    //     }
    // }

    private function activarApartamentos(Proyectos $proyecto, string $torre, string $proceso, Carbon $hoy): void
{
    $proceso = strtolower(trim($proceso));

    // 🔹 Verificar si ya se habilitó hoy este proceso en esta torre
    $yaActivadoHoy = ProyectosDetalle::join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
        ->where('proyecto_detalle.proyecto_id', $proyecto->id)
        ->where('proyecto_detalle.torre', $torre)
        ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$proceso])
        ->whereDate('proyecto_detalle.fecha_habilitado', $hoy->toDateString())
        ->exists();

    if ($yaActivadoHoy) {
        Log::info("⏭ Ya se activó hoy el proceso '{$proceso}' en Proyecto {$proyecto->id}, Torre {$torre}. No se activa más.");
        return;
    }

    // 🔹 Buscar apartamentos pendientes de activar (estado = 0)
    $aptosParaActivar = ProyectosDetalle::select('proyecto_detalle.*')
        ->where('proyecto_detalle.proyecto_id', $proyecto->id)
        ->where('proyecto_detalle.torre', $torre)
        ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
        ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$proceso])
        ->where('proyecto_detalle.estado', 0)
        ->orderBy('proyecto_detalle.piso')
        ->orderBy('proyecto_detalle.consecutivo')
        ->take($proyecto->activador_pordia_apt)
        ->get();

    Log::info("🚀 Intentando activar '{$proceso}' en Proyecto {$proyecto->id}, Torre {$torre} → Encontrados: " . $aptosParaActivar->count());

    foreach ($aptosParaActivar as $apto) {
        Log::info("    ➡ Apto {$apto->apartamento} (Piso {$apto->piso}) ID {$apto->id} → Estado actual: {$apto->estado}");
        $apto->update([
            'fecha_habilitado' => now(),
            'estado' => 1,
        ]);
        Log::info("    ✅ Activado Apto {$apto->apartamento} en '{$proceso}'");
    }
}


    private function tieneFase2(int $proyectoId, string $torre): bool
    {
        return ProyectosDetalle::where('proyecto_id', $proyectoId)
            ->where('torre', $torre)
            ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', ['aparateada fase 2'])
            ->exists();
    }
}
