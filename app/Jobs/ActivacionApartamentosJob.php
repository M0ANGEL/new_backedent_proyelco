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

class ActivacionApartamentosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $hoy = Carbon::now();

        // Omitir fines de semana
        if ($hoy->isWeekend()) {
            return;
        }

        // Omitir días festivos
        if (Festivos::whereDate('festivo_fecha', $hoy->toDateString())->exists()) {
            return;
        }

        // Procesar todos los proyectos
        $proyectos = Proyectos::all();

        foreach ($proyectos as $proyecto) {
            $cantidadActivar = $proyecto->activador_pordia_apt;

            $procesos = ProyectosDetalle::where('proyecto_id', $proyecto->id)
                ->select('orden_proceso')
                ->distinct()
                ->orderBy('orden_proceso')
                ->pluck('orden_proceso')
                ->values();

            $torres = ProyectosDetalle::where('proyecto_id', $proyecto->id)
                ->select('torre')
                ->distinct()
                ->pluck('torre');

            foreach ($torres as $torre) {
                for ($i = 0; $i < $procesos->count() - 1; $i++) {
                    $procesoActual = $procesos[$i];
                    $procesoSiguiente = $procesos[$i + 1];

                    $registrosActual = ProyectosDetalle::where('proyecto_id', $proyecto->id)
                        ->where('orden_proceso', $procesoActual)
                        ->where('torre', $torre)
                        ->get();

                    $completado = $registrosActual->every(fn($r) => $r->estado == 2);

                    if (!$completado) {
                        continue;
                    }

                    $pendientesSiguiente = ProyectosDetalle::where('proyecto_id', $proyecto->id)
                        ->where('orden_proceso', $procesoSiguiente)
                        ->where('estado', 0)
                        ->where('torre', $torre)
                        ->orderBy('consecutivo')
                        ->get();

                    $registroValidar = ProyectosDetalle::where('proyecto_id', $proyecto->id)
                        ->where('orden_proceso', $procesoSiguiente)
                        ->where('torre', $torre)
                        ->first();

                    if ($registroValidar && $registroValidar->validacion == 1 && $registroValidar->estado_validacion == 0) {
                        continue;
                    }

                    $ya_habilitado_hoy = ProyectosDetalle::where('proyecto_id', $proyecto->id)
                        ->where('orden_proceso', $procesoSiguiente)
                        ->where('torre', $torre)
                        ->whereIn('estado', [1, 2])
                        ->whereDate('fecha_habilitado', $hoy->toDateString())
                        ->exists();

                    if ($ya_habilitado_hoy || $pendientesSiguiente->isEmpty()) {
                        continue;
                    }

                    // Activar los N apartamentos
                    $apartamentosPorActivar = $pendientesSiguiente->take($cantidadActivar);

                    foreach ($apartamentosPorActivar as $apt) {
                        $apt->update([
                            'fecha_habilitado' => $hoy->toDateString(),
                            'estado' => 1,
                        ]);
                    }

                    break; // activar solo una tanda diaria por torre
                }
            }
        }
    }
}
