<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TMorganismos extends Model
{
    use HasFactory;

    protected $table = "torres_documentacion_organismos";

    protected $fillable = [
        'id',
        'codigo_proyecto',
        'codigo_documento',
        'user_id',
        'actividad_id',
        'actividad_hijos_id',
        'tm',
        'estado'
    ];
}
