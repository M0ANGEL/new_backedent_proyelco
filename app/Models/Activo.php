<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activo extends Model
{
    use HasFactory;

    protected $table = "activo";

    protected $fillable = [
        'numero_activo',
        'categoria_id',
        'subcategoria_id',
        'descripcion',
        'ubicacion_id',
        'usuarios_asignados',
        'valor',
        'fecha_fin_garantia',
        'condicion',
        'modelo',
        'marca',
        'serial',
        'observacion',
        'estado',
    ];
}
