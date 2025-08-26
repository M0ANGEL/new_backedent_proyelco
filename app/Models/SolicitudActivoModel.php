<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudActivoModel extends Model
{
    use HasFactory;

    protected $table = "solicitudes_activos";

    protected $fillable = [
        'activo_id',
        'user_id',
        'bodega_solicita',
        'motivo',
        'estado',
        'tipo_ubicacion',
    ];
}
