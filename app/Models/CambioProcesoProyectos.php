<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CambioProcesoProyectos extends Model
{
    use HasFactory;

    protected $table = 'cambio_procesos_x_proyecto';

    protected $fillable = [
        'proyecto_id',
        'numero',
        'proceso'
    ];
}
