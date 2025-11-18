<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialSolicitud extends Model
{
    use HasFactory;

    protected $table = 'materiales';

    protected $fillable = [
        'user_id',
        'codigo_proyecto',
        'codigo',
        'descripcion',
        'padre',
        'um',
        'cantidad',
        'subcapitulo',
        'cant_apu',
        'rend',
        'valor_sin_iva',
        'tipo_insumo',
        'agrupacion',
    ];
}
