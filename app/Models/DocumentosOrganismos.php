<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentosOrganismos extends Model
{
    use HasFactory;

    protected $table = 'documentos_organismos';


    public function actividad()
    {
        return $this->belongsTo(ActividadesOrganismos::class, 'actividad_id');
    }

    
}
