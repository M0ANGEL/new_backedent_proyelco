<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudMaterialInge extends Model
{
    use HasFactory;

    protected $table = "solicitud_material";

    protected $fillable = [
        'user_id',
        'numero_solicitud',
        'numero_solicitud_sinco',
        'codigo_proyecto',
        'codigo_item',
        'codigo_insumo',
        'descripcion',
        'padre',
        'nivel',
        'um',
        'cant_unitaria',
        'cant_solicitada',
        'cant_total',
        'fecha_solicitud',
    ];
}
