<?php

namespace App\Mail;

use App\Models\Proyectos;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AvisoFechaFinFaltante extends Mailable
{
    use Queueable, SerializesModels;

    public $proyecto;

    public function __construct(Proyectos $proyecto)
    {
        $this->proyecto = $proyecto;
    }

    public function build()
    {
        return $this->view('emails.aviso_fecha_fin')
            ->subject("🚨 Alerta de Inactividad en Proyecto {$this->proyecto->descripcion_proyecto}")
            ->with([
                'proyecto' => $this->proyecto,
            ]);
    }
}
