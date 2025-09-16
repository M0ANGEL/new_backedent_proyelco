<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nombrexmanzana extends Model
{
    use HasFactory;
    
    protected $table = "nombrexmanzana";

    protected $fillable = [
        'nombre_manzana',
        'manzana',
        'proyectos_casas_id',
    ];
}
