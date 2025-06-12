<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CargoUsuarioLog extends Model
{
    use HasFactory;

    protected $table = 'cargo_user_logs';

    protected $fillable = [
        'id_cargo_user',
        'id_user',
        'accion',
        'data',
        'old'
    ];

    public function usuarios()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function cargosUsuarios()
    {
        return $this->belongsTo(Cargo::class, 'id_cargo_user');
    }
}
