<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CerrarAsistenciasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        $hoy = now()->format('Y-m-d'); // Ej: 2025-11-24

        DB::table('asistencias_th')
            ->whereDate('fecha_ingreso', $hoy)  // registros solo del día actual
            ->whereNull('fecha_salida')        // que NO tengan salida
            ->update([
                'fecha_salida' => now()->format('Y-m-d'),   // fecha actual
                'hora_salida' => '23:59:59',                 // hora fija
                'horas_laborales' => 0                       // 0 horas
            ]);
    }
}
