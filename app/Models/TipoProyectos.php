<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoProyectos extends Model
{
    use HasFactory;

    protected $table = "tipos_de_proyectos";

    protected $fillable = [
        'nombre_tipo',
        'user_id'
    ];
}
