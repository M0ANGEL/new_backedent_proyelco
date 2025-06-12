<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'emp_nombre',
        'estado',
        'nit',
        'direccion',
        'telefono',
        'servidor_smtp',
        'protocolo_smtp',
        'cuenta_de_correo',
        'contrasena_correo'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'emp_x_usu', 'id_empresa', 'id_user');
    }
}
