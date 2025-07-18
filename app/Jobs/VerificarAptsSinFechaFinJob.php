<?php

namespace App\Jobs;

use App\Models\Proyectos;
use App\Models\User;
use App\Models\Festivos;
use Illuminate\Support\Facades\Mail;
use App\Mail\AvisoFechaFinFaltante;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class VerificarAptsSinFechaFinJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $festivos = Festivos::pluck('festivo_fecha')->toArray();
        $hoy = Carbon::now();

        // Obtener todos los proyectos
        $proyectos = Proyectos::all();

        foreach ($proyectos as $proyecto) {
            // Buscar el último registro con fecha_fin no nula
            $ultimoDetalle = $proyecto->detalles()
                ->whereNotNull('fecha_fin')
                ->orderByDesc('fecha_fin')
                ->first();

            if (!$ultimoDetalle) {
                // Si nunca ha tenido una fecha_fin, se puede omitir o enviar alerta según tu necesidad
                continue;
            }

            $ultimaFechaFin = Carbon::parse($ultimoDetalle->fecha_fin);
            $diasHabiles = 0;
            $fechaTemp = $ultimaFechaFin->copy();

            // Contar días hábiles desde la última fecha_fin hasta hoy
            while ($fechaTemp->lt($hoy)) {
                $fechaTemp->addDay();
                if (
                    !$fechaTemp->isSunday() &&
                    !in_array($fechaTemp->format('Y-m-d'), $festivos)
                ) {
                    $diasHabiles++;
                }
            }

            if ($diasHabiles >= 3) {
                $correo = $proyecto->ingeniero?->correo;

                if ($correo) {
                    Mail::to($correo)->send(new AvisoFechaFinFaltante($proyecto));
                }
            }
        }
    }
}
