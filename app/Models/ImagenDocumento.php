<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagenDocumento extends Model
{
    use HasFactory;

    protected $table = "imagen_documentos";

    public $timestamps = false;

    protected $fillable = [
        'consecutivo',
        'image',
        'tipo_documento_id',
        'fecha_cargue',
    ];
}
