<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Perfil extends Model
{
    use HasFactory;

    protected $table = 'perfiles';

    protected $fillable = [
        'id',
        'cod_perfil',
        'nom_perfil',
        'desc_perfil',
        'estado',
        'id_empresa',
    ];

    public function modulos()
    {
        return $this->hasMany(PerfilesModulos::class, 'id_perfil', 'id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa', 'id');
    }
}
