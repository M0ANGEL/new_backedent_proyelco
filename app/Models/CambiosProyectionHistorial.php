<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CambiosProyectionHistorial extends Model
{
    use HasFactory;

    protected $table = "cambios_proyeccion_historial";

    protected $fillable = [ 
        'user_id',
        'version_edicion',
        'codigo_proyecto',
        'codigo_item',
        'codigo_insumo',
        'descripcion',
        'padre',
        'nivel',
        'um',
        'cant_old',
        'cant_modificada',
        'cant_final',
        'cant_apu_old',
        'cant_apu_modificada',
        'cant_apu_final',
        'fecha_modificacion',
    ];
}
