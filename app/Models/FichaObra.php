<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FichaObra extends Model
{
    use HasFactory;

    protected $table = "ficha_th";
    protected $fillable = [
        'tipo_empleado',
        'estado',
        'identificacion',
        'empleado_id',
        'rh',
        'hijos',
        'eps',
        'afp',
        'contratista_id'
    ];
}
