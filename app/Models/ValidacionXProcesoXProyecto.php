<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ValidacionXProcesoXProyecto extends Model
{
    use HasFactory;

    protected $table = "val_xpro_xpt";

    protected $fillable = [
        'tipo_validacion',
        'cant',
        'P1_id',
        'P1_depende_id',
        'proyecto_id',
    ];
}
