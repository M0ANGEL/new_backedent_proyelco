<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentosTorres extends Model
{
    use HasFactory;

    protected $table = 'documentacion_torres';

    protected $fillable = [
        'codigo_proyecto',
        'codigo_documento',
        'nombre_torre',
        'operador',
        'actividad_id',
        'estado',
    ];
}
