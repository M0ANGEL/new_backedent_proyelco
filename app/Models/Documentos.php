<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Documentos extends Model
{
    use HasFactory;

    protected $table = 'documentacion_operadores';
    protected $fillable = [
        'nombre_etapa',
        'codigo_proyecto',
        'codigo_documento',
        'etapa',
        'tipo',
        'orden',
        'actividad_id',
        'actividad_depende_id',
        'fecha_proyeccion',
        'fecha_actual',
        'fecha_confirmacion',
        'usuario_id',
        'estado',
        'operador',
        'observacion',
        'diferenciaDias',
    ];

    // En el modelo Documentos
    public function actividad()
    {
        return $this->belongsTo(ActividadesDocumentos::class, 'actividad_id');
    }
}
