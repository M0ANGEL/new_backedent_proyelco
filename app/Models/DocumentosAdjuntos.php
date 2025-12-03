<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentosAdjuntos extends Model
{
    use HasFactory;

    protected $table = "documentos_adjuntos";

    protected $fillable = [
    'documento_id',
    'ruta_archivo',
    'nombre_original',
    'extension',
    'tamano',
];

}
    
