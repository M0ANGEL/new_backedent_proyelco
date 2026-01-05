<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentosOrganismosAdjuntos extends Model
{
    use HasFactory;

    protected $table = 'documentos_organismos_adjuntos';

    protected $fillable = [
        'documento_id',
        'ruta_archivo',
        'nombre_original',
        'extension',
        'tamano',
    ];

    public function documento()
    {
        return $this->belongsTo(DocumentosOrganismos::class, 'documento_id');
    }
}
