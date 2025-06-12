<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Horario extends Model
{
    use HasFactory;

    protected $table = "horarios"; //horario

    protected $fillable = [
        'nombre_perfil',
        'user_id',
        'estado',
        'dia'
    ];

    public $timestamps = false;

    // Relación con Horarios
    // Un perfil de horario tiene varios horarios
    public function detalles() //horario_detalle
    {
        return $this->hasMany(HorarioDetalle::class, 'horario_id'); //horario_id
    }

    // Un perfil de horario puede estar asignado a varios usuarios
    public function usuarios()
    {
        return $this->hasMany(User::class, 'horario_id'); //queda igual
    }
}
