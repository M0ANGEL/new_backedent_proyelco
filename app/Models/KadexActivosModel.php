<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KadexActivosModel extends Model
{
    use HasFactory;

    protected $table = "kadex_activos";

    protected $fillable = [
        'codigo_traslado',
        'activo_id',
        'user_id',
        'usuarios_asignados',
        'usuarios_confirmaron',
        'aceptacion',
        'ubicacion_id',
        'fecha_Aceptacion',
        'observacion',
    ];
}
