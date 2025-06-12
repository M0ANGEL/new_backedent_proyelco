<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proyectos extends Model
{
    use HasFactory;

    protected $table = "proyecto";

    protected $fillable = [
        'estado',
        'bloques',
        'codigo_contrato',
        'fecha_inicio',
        'tipoProyecto_id',
        'torres',
        'nit',
        'PsiguentePro',
        'tipo_obra',
        'cant_pisos',
        'apt',
    ];
}
