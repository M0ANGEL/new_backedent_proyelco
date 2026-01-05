<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SolicitudMaterialAdjunto extends Model
{
    use HasFactory;

    protected $table = 'solicitud_material_adjuntos';

    protected $fillable = [
        'solicitud_id',
        'codigo_proyecto',
        'ruta_archivo',
        'nombre_original',
        'extension',
        'tamano'
    ];

    // Relación con la solicitud
    public function solicitud()
    {
        return $this->belongsTo(SolicitudMaterialInge::class, 'solicitud_id');
    }
}