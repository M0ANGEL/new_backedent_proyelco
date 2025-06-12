<?php

namespace App\Jobs;

use App\Mail\NotificacionCorreo;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EnviarCorreoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // Obtener todos los proyectos
        $proyectos = DB::connection('mysql')
            ->table('proyecto')
            ->join('clientes', 'proyecto.cliente_id', '=', 'clientes.id')
            ->join('users', 'proyecto.encargado_id', '=', 'users.id')
            ->join('users as ing', 'proyecto.ingeniero_id', '=', 'ing.id')
            ->select(
                'proyecto.*',
                'users.nombre as nombreEncargado',
                'ing.nombre as nombreIngeniero',
                'clientes.emp_nombre'
            )
            ->get();

        foreach ($proyectos as $proyecto) {

            $detalles = DB::connection('mysql')
                ->table('proyecto_detalle')
                ->where('proyecto_id', $proyecto->id)
                ->get();

            // Calcular % atraso
            $totalEjecutando = $detalles->where('estado', 1)->count();
            $totalTerminado = $detalles->where('estado', 2)->count();
            $total = $totalEjecutando + $totalTerminado;

            $porcentajeAtraso = $total > 0 ? ($totalEjecutando / $total) * 100 : 0;

            // Calcular % avance
            $totalApartamentos = $detalles->count();
            $apartamentosRealizados = $totalTerminado;

            $porcentajeAvance = $totalApartamentos > 0 ? ($apartamentosRealizados / $totalApartamentos) * 100 : 0;

            // Obtener los usuarios de notificación
            $usuariosIds = json_decode($proyecto->usuarios_notificacion);

            if ($usuariosIds && count($usuariosIds) > 0) {
                $usuarios = User::whereIn('id', $usuariosIds)
                    ->whereNotNull('correo')
                    ->select('id', 'correo','nombre')
                    ->get();

                foreach ($usuarios as $usuario) {

                    $detallesCorreo = [
                        'titulo' => 'Actualización del Proyecto: ' . $proyecto->descripcion_proyecto,
                        'mensaje' => "El proyecto tiene un avance del {$porcentajeAvance}% y un atraso del {$porcentajeAtraso}%.",
                        'proyecto' => $proyecto
                    ];

                    try {
                        Mail::to($usuario->correo)->send(new NotificacionCorreo($detallesCorreo));
                        Log::info('Correo enviado a: ' . $usuario->correo);
                    } catch (\Exception $e) {
                        Log::error('Error al enviar correo a ' . $usuario->correo . ': ' . $e->getMessage());
                    }
                }
            }
        }
    }
}
