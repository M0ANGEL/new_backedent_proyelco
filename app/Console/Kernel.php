<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */

    protected function schedule(Schedule $schedule)
    {
        // job de enviar correos de info de los proyectos
        $schedule->job(new \App\Jobs\EnviarCorreoJob)
            ->dailyAt('00:00')
            // ->everyMinute('10:24')
            ->timezone('America/Bogota');
        // everyMinute cada minuto para pruebas

        // job de activar pisos diarios si el procesos esta completo, solo dias habilis 
        $schedule->job(new \App\Jobs\ActivacionApartamentosJob)
            ->dailyAt('00:00')
            ->timezone('America/Bogota');
    }



    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
