<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsistenciasObra extends Model
{
    use HasFactory;

    protected $table = "asistencias_obra_create";

    protected $fillable = [
        'personal_id',
        'proyecto_id',
        'usuario_asigna',
        'usuario_confirma',
        'confirmacion',
        'detalle',
        'fecha_programacion',
        'fecha_confirmacion',
    ];
}
