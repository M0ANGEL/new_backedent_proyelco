<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcesosProyectos extends Model
{
    use HasFactory;

    protected $table = "procesos_proyectos";

    protected $fillable = [
        'tipoPoryecto_id',
        'nombre_proceso',
        'user_id',
        'estado'
    ];

    public function setNombreProcesoAttribute($value)
    {
        $this->attributes['nombre_proceso'] = strtolower($value);
    }
}
