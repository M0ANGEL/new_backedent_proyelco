<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProyectoCasa extends Model
{
    use HasFactory;

    protected $table = "proyectos_casas";

    protected $fillable = [
        "tipoProyecto_id",
        "cliente_id",
        "usuario_crea_id",
        "descripcion_proyecto",
        "fecha_inicio",
        "codigo_proyecto",
        "usuarios_notificacion",
        "estado",
        "activador_pordia_fundida",
        "activador_pordia",
        "fecha_ini_proyecto",
        "encargado_id",
        "ingeniero_id",
    ];
}
