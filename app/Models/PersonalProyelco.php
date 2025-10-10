<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalProyelco extends Model
{
    use HasFactory;

    protected $table = "empleados_proyelco_th";

    protected $fillable = [
        'estado',
        'identificacion',
        'tipo_documento',
        'nombre_completo',
        'fecha_expedicion',
        'estado_civil',
        'ciuda_expedicion_id',
        'fecha_nacimiento',
        'pais_residencia_id',
        'ciudad_resudencia_id',
        'genero',
        'telefono_fijo',
        'telefono_celular',
        'direccion',
        'correo',
        'cargo_id',
        'fecha_ingreso',
        'fecha_terminacion',
        'motivo_retiro',
        'salario',
        'uuario_retira',
        'user_id',
        'valor_hora'
    ];
}
