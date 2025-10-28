<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Documentos extends Model
{
    use HasFactory;

    protected $table = 'documentacion_operadores';
    protected $fillable = [
        'codigo_proyecto',
        'codigo_documento',
        'etapa',
        'actividad_id',
        'actividad_depende_id',
        'tipo',
        'orden',
        'fecha_proyeccion',
        'fecha_actual',
        'fecha_confirmacion',
        'usuario_id',
        'estado',
        'operador',
        'observacion',
    ];

    // En el modelo Documentos
    public function actividad()
    {
        return $this->belongsTo(ActividadesDocumentos::class, 'actividad_id');
    }
}
