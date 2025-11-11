<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentosOrganismos extends Model
{
    use HasFactory;

    protected $table = 'documentos_organismos';

    protected $fillable = [
        'nombre_etapa',
        'codigo_proyecto',
        'etapa',
        'actividad_id',
        'actividad_depende_id',
        'tipo',
        'orden',
        'fecha_confirmacion',
        'usuario_id',
        'estado',
        'operador',
        'observacion'
    ];


    public function actividad()
    {
        return $this->belongsTo(ActividadesOrganismos::class, 'actividad_id');
    }

    
}
