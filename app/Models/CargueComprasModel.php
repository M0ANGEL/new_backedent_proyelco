<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CargueComprasModel extends Model
{
    use HasFactory;

    protected $table = 'compras';

    protected $fillable = [
        'codigo_insumo',
        'insumo_descripcion',
        'unidad',
        'mat_requerido',
        'agrupacion_descripcion',
        'nombre_tercero',
        'prefijo',
    ];
}
