<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activo extends Model
{
    use HasFactory;

    protected $table = "activo";

    protected $fillable = [
        'tipo_activo',
        'origen_activo',
        'proveedor_activo',
        'numero_activo',
        'categoria_id',
        'subcategoria_id',
        'user_id',
        'descripcion',
        'ubicacion_id',
        'usuarios_asignados',
        'valor',
        'fecha_aquiler',
        'fecha_compra',
        'condicion',
        'modelo',
        'marca',
        'serial',
        'observacion',
        'estado',
        'tipo_ubicacion',
        'ubicacion_actual_id',
    ];
}
