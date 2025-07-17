<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProyectosDetalle extends Model
{
    use HasFactory;

    protected $table = "proyecto_detalle";

    protected $fillable = [
        'user_id',
        'proyecto_id',
        'torre',
        'piso',
        'apartamento',
        'orden_proceso',
        'procesos_proyectos_id',
        'fecha_ini_torre',
        'estado',
        'fecha_habilitado',
        'validacion',
        'estado_validacion',
        'fecha_validacion',
        'fecha_fin',
        'text_validacion',
        'consecutivo',
    ];

    public function proceso()
    {
        return $this->belongsTo(ProcesosProyectos::class, 'procesos_proyectos_id', 'id');
    }
}
