<?php

namespace App\Console;

use App\Jobs\EnviarCorreoJob;
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
        $schedule->job(new \App\Jobs\EnviarCorreoJob)
            ->dailyAt('10:24'); // Hora deseada para ejecutar el Job
        // everyMinute cada minuto para pruebas

    }



    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
