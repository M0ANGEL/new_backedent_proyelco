<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerfilesModulos extends Model
{
    protected $table = 'perfiles_modulos';

    protected $fillable = [
        'id_modulo', 'id_perfil', 'id_menu', 'id_submenu'
    ];

    public function modulo()
    {
        return $this->belongsTo(Modulo::class, 'id_modulo');
    }

    public function perfil()
    {
        return $this->belongsTo(Perfil::class, 'id_perfil');
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class, 'id_menu');
    }

    public function submenu()
    {
        return $this->belongsTo(Submenu::class, 'id_submenu');
    }
}
