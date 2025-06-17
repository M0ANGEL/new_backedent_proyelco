<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proyectos extends Model
{
    use HasFactory;

    protected $table = "proyecto";

    protected $fillable = [
        'tipoProyecto_id',
        'cliente_id',
        'usuario_crea_id',
        'encargado_id',
        'descripcion_proyecto',
        'fecha_inicio',
        'codigo_proyecto',
        'torres',
        'cant_pisos',
        'apt',
        'pisosCambiarProcesos',
        'usuarios_notificacion',
        'estado',
        'fecha_ini_proyecto',
    ];
}
