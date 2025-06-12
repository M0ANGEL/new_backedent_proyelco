<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cargo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
        'id_empresa'
    ];

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }
}
