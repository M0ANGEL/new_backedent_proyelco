<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContratistasSST extends Model
{
    use HasFactory;

    protected $table = "contratistas_th";
    protected $fillable =  [
        'user_id',
        'nit',
        'contratista',
        'arl',
        'actividad',
        'contacto',
        'telefono',
        'direccion',
        'correo',
        'estado',
        'nit',
    ];
}
