<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MantenimientoActivos extends Model
{
    use HasFactory;

    protected $table = "mantenimiento_activos";

    protected $fillable = [
        'activo_id',
        'valor',
        'fecha_inicio',
        'fecha_fin',
        'observacion',
    ];
}
