<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialSolicitud extends Model
{
    use HasFactory;

    protected $table = 'materiales';
    
    // Añade esto para permitir valores null en codigo_insumo
    protected $fillable = [
        'user_id',
        'codigo_proyecto',
        'codigo',
        'codigo_insumo', // ← Asegúrate que está aquí
        'descripcion',
        'padre',
        'um',
        'cantidad',
        'subcapitulo',
        'cant_apu',
        'cant_restante',
        'rend',
        'valor_sin_iva',
        'tipo_insumo',
        'agrupacion',
        'nivel',
        'iva',
    ];
    
    // OPCIONAL: Si quieres establecer un valor por defecto
    protected $attributes = [
        'codigo_insumo' => "Err-COD",
    ];
}