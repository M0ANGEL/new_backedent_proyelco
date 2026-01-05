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
use Illuminate\Support\Facades\Log;

class VerificarAptsSinFechaFinJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $festivos = Festivos::pluck('festivo_fecha')->toArray();
        $hoy = Carbon::now();

        // Obtener todos los proyectos los que sean estado 1, signifa proyectos que estan en proceso
        $proyectos = Proyectos::where("estado",1)->get();

        foreach ($proyectos as $proyecto) {
            $ultimoDetalle = $proyecto->detalles()
                ->whereNotNull('fecha_fin')
                ->orderByDesc('fecha_fin')
                ->first();

            if (!$ultimoDetalle) {
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
                // Obtener IDs de ingenieros desde el JSON
                $ingenierosIds = json_decode($proyecto->ingeniero_id, true);

                if (is_array($ingenierosIds) && count($ingenierosIds) > 0) {
                    $usuarios = User::whereIn('id', $ingenierosIds)
                        ->whereNotNull('correo')
                        ->get();

                    foreach ($usuarios as $usuario) {
                        try {
                            Mail::to($usuario->correo)->send(new AvisoFechaFinFaltante($proyecto));
                            Log::info("Correo enviado a {$usuario->correo} para proyecto {$proyecto->id}");
                        } catch (\Exception $e) {
                            Log::error("Error al enviar correo a {$usuario->correo}: " . $e->getMessage());
                        }
                    }
                }
            }
        }
    }
}
