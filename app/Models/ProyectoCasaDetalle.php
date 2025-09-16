<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProyectoCasaDetalle extends Model
{
    use HasFactory;

    protected $table = "proyectos_casas_detalle";
    protected $fillable = [
        "user_id",
        "proyecto_casa_id",
        "manzana",
        "casa",
        "consecutivo_casa",
        "piso",
        "etapa",
        "orden_proceso",
        "procesos_proyectos_id",
        "text_validacion",
        "fecha_ini_torre",
        "estado",
        "fecha_habilitado",
        "validacion",
        "estado_validacion",
        "fecha_validacion",
        "fecha_fin",
    ];
}
