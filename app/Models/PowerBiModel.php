<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PowerBiModel extends Model
{
    use HasFactory;

    protected $table = "ruta_power_bi_informes";

    protected $fillable = [ 
        'nombre',
        'estado',
        'link_power_bi',
        'ruta'
    ];
}
