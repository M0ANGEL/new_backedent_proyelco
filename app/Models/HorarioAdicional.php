<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HorarioAdicional extends Model
{
    use HasFactory;

    protected $table = 'horarios_adicional';

    protected $fillable = [
        'id',
        'observacion',
        'fecha_inicio',
        'fecha_final',
        'usuarios_autorizados',
        'estado',
        'proceso_autoriza_id',
        'user_id'
    ];
}
