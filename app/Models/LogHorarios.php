<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogHorarios extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'user_id',
        'hora_anterior',
        'hora_nueva',
        'horario_id',
        'dia'

    ];


    protected $table = 'log_horarios';
}
