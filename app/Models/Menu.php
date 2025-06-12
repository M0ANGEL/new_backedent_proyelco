<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    protected $table = 'menu';

    protected $fillable = [
        'nom_menu',
        'link_menu',
        'desc_menu',
        'id_modulo'
    ];

    public function modulo()
    {
        return $this->belongsTo(Modulo::class, 'id_modulo', 'id');
    }

    public function submenus(){
        return $this->hasMany(Submenu::class, 'id_menu');
    }
}
