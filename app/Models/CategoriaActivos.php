<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoriaActivos extends Model
{
    use HasFactory;

    protected $table = "categoria_activos";

    protected $fillable = [
        'nombre',
        'descripcion',
        'user_id',
        'estado',
        'prefijo',
    ];

    public function setPrefijoAttribute($value)
    {
        $this->attributes['prefijo'] = strtolower($value);
    }
}
