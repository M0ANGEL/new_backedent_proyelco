<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clientes extends Model
{
    use HasFactory;

    //direccion de la tablas cleintes
    protected $table = "clientes";

    protected $fillable = [
        'emp_nombre',
        'estado',
        'nit',
        'direccion',
        'telefono',
        'cuenta_de_correo',
        'id_user',
    ];
}
