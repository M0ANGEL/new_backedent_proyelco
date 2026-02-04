<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReporteMateriaNc extends Model
{
    use HasFactory;

    protected $table = 'reporte_material_nc';
    protected $fillable = [
        'codigo_proyecto',
        'tipo_reporte',
        'insumo',
        'codigo_insumo',
        'factura',
        'cantidad_reportada',
        'proveedor_id',
        'descripcion_nc',
        'estado',
        'id_user',
        'respuesta',
        'respuesta_proveedor',
        'cantidad_aceptada',
    ];
}
