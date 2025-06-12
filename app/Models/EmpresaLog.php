<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpresaLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_empresa',
        'id_operario',
        'accion',
        'data',
        'old'
    ];

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function operarios()
    {
        return $this->belongsTo(User::class, 'id_operario');
    }
}
