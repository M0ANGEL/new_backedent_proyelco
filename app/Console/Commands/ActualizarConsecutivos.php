<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class ActualizarConsecutivos extends Command
{
    protected $signature = 'consecutivos:actualizar 
                            {desde : Consecutivo inicial a reemplazar} 
                            {hasta : Consecutivo final a reemplazar} 
                            {nuevoInicio : Nuevo valor inicial para reemplazar} 
                            {proyecto : ID del proyecto} 
                            {torre : Torre}
                            {piso : Piso}';

    protected $description = 'Actualiza consecutivos por rangos para un proyecto, torre y piso específicos';

    public function handle()
    {
        $desde = (int) $this->argument('desde');
        $hasta = (int) $this->argument('hasta');
        $nuevoInicio = (int) $this->argument('nuevoInicio');
        $proyecto = $this->argument('proyecto');
        $torre = $this->argument('torre');
        $piso = $this->argument('piso');

        $this->info("Actualizando consecutivos de $desde a $hasta → empezando en $nuevoInicio");

        $consecutivos = DB::table('proyecto_detalle')
            ->select('consecutivo')
            ->whereBetween('consecutivo', [$desde, $hasta])
            ->where('proyecto_id', $proyecto)
            ->where('torre', $torre)
            ->where('piso', $piso)
            ->distinct()
            ->orderBy('consecutivo')
            ->pluck('consecutivo');

        foreach ($consecutivos as $index => $original) {
            $nuevo = $nuevoInicio + $index;

            DB::table('proyecto_detalle')
                ->where('consecutivo', $original)
                ->where('proyecto_id', $proyecto)
                ->where('torre', $torre)
                ->where('piso', $piso)
                ->update(['consecutivo' => $nuevo]);

            $this->line("✔️  $original → $nuevo");
            Log::channel('consecutivos')->info("Consecutivo {$original} actualizado a {$nuevo}");

        }

        $this->info('¡Consecutivos actualizados correctamente!');
    }
}
