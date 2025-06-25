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
        // Paso 1: Consultar todos los proyectos
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

        // Paso 2: Agrupar proyectos por usuario
        $usuariosProyectos = [];

        foreach ($proyectos as $proyecto) {

            $detalles = DB::connection('mysql')
                ->table('proyecto_detalle')
                ->where('proyecto_id', $proyecto->id)
                ->get();

            // Calcular % de atraso
            $totalEjecutando = $detalles->where('estado', 1)->count();
            $totalTerminado = $detalles->where('estado', 2)->count();
            $total = $totalEjecutando + $totalTerminado;

            $porcentajeAtraso = $total > 0 ? ($totalEjecutando / $total) * 100 : 0;

            // Calcular % de avance
            $totalApartamentos = $detalles->count();
            $apartamentosRealizados = $totalTerminado;

            $porcentajeAvance = $totalApartamentos > 0 ? ($apartamentosRealizados / $totalApartamentos) * 100 : 0;

            // Obtener los usuarios de notificación
            $usuariosIds = json_decode($proyecto->usuarios_notificacion);

            if ($usuariosIds && count($usuariosIds) > 0) {
                $usuarios = User::whereIn('id', $usuariosIds)
                    ->whereNotNull('correo')
                    ->select('id', 'correo', 'nombre')
                    ->get();

                foreach ($usuarios as $usuario) {
                    if (!isset($usuariosProyectos[$usuario->correo])) {
                        $usuariosProyectos[$usuario->correo] = [
                            'nombre' => $usuario->nombre,
                            'proyectos' => []
                        ];
                    }

                    $usuariosProyectos[$usuario->correo]['proyectos'][] = [
                        'descripcion' => $proyecto->descripcion_proyecto,
                        'cliente' => $proyecto->emp_nombre,
                        'encargado' => $proyecto->nombreEncargado,
                        'ingeniero' => $proyecto->nombreIngeniero,
                        'porcentajeAvance' => number_format($porcentajeAvance, 2),
                        'porcentajeAtraso' => number_format($porcentajeAtraso, 2)
                    ];
                }
            }
        }

        // Paso 3: Enviar un solo correo por usuario consolidado
        foreach ($usuariosProyectos as $correo => $data) {
            Log::info('enviando');
            $detallesCorreo = [
                'titulo' => 'Resumen de tus proyectos asignados - ' . now()->format('d/m/Y'),
                'proyectos' => $data['proyectos'],
                'nombreUsuario' => $data['nombre']
            ];

            try {
                Mail::to($correo)->send(new NotificacionCorreo($detallesCorreo));
                Log::info('Correo enviado a: ' . $correo);
            } catch (\Exception $e) {
                Log::error('Error al enviar correo a ' . $correo . ': ' . $e->getMessage());
            }
        }
    }
}
