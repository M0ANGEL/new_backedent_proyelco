<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerfilLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_perfil',
        'id_user',
        'accion',
        'data',
        'old'
    ];

    public function perfiles()
    {
        return $this->belongsTo(Perfil::class, 'id_perfil');
    }

    public function usuarios()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
