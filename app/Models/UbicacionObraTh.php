<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UbicacionObraTh extends Model
{
    use HasFactory;

    protected $table = "ubicacion_obras_th";

    protected $fillable =[
        'latitud',
        'longitud',
        'user_id',
        'serial',
        'tipo_obra',
        'obra_id',
        'estado',
    ];
}
