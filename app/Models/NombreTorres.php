<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NombreTorres extends Model
{
    use HasFactory;

    protected $table = "nombre_xtore";

    protected $fillable = [
        'nombre_torre',
        'torre',
        'proyecto_id',
    ];
}
