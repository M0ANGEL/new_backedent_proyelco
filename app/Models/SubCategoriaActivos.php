<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubCategoriaActivos extends Model
{
    use HasFactory;

    protected $table = "subcategoria_activos";

    protected $fillable = [
        'categoria_id',
        'nombre',
        'descripcion',
        'estado',
        'user_id',
    ];
}
