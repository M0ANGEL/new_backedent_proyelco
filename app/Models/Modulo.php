<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modulo extends Model
{
    use HasFactory;

    protected $table = 'modulos';

    protected $fillable = [
        'cod_modulo',
        'nom_modulo',
        'desc_modulo',
        'estado',
    ];

    public function perfiles()
    {
        return $this->belongsToMany(Perfil::class, 'perfiles_modulos', 'id_modulo', 'id_perfil');
    }

    public function menus()
    {
        return $this->hasMany(Menu::class, 'id_modulo');
    }
}
