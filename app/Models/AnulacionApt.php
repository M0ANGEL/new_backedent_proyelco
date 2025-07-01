<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnulacionApt extends Model
{
    use HasFactory;

    protected $table = "cambio_estado_apt_proyecto";

    protected $fillable = [
        'user_id',
        'userConfirmo_id',
        'proyecto_id',
        'motivo',
        'piso',
        'apt',
        'fecha_confirmo',
    ];
}
