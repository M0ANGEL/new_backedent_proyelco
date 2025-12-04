<?php

namespace App\Models\Sinco;

use Illuminate\Database\Eloquent\Model;

class CodigosItemSinco extends Model
{
    protected $connection = 'sqlsrv_sinco'; 
    protected $table = 'ADP_DTM_DIM.Insumo';  
    
    // Si tu tabla tiene columnas con espacios, usa accessors
    public function getDescripcionAttribute()
    {
        return $this->attributes['Insumo Descripcion'] ?? null;
    }
    
    public function getCodigoInsumoAttribute()
    {
        return $this->attributes['Codigo Insumo'] ?? null;
    }
    
    public $timestamps = false;
}