<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HorarioDetalle extends Model
{
    use HasFactory;

    protected $table = "horarios_detalle"; //hora_detalle

    protected $fillable = [
        'horario_id',
        'dia',
        'hora_inicio',
        'hora_final',
        'user_id',
        'estado',
    ];


    public function horario() //horario 
    {
        return $this->belongsTo(Horario::class, 'horario_id');  //horario horario_id
    }

    // Un horario fue creado por un usuario
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id'); //queda igual
    }
}
