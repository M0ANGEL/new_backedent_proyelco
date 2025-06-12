<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CargoUsuario extends Model
{
    protected $table = 'cargo_users';

    protected $fillable = [
        'id_user',
        'id_cargo',
        'estado'
    ];

    public function cargo()
    {
        return $this->belongsTo(Cargo::class, 'id_cargo');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }
}
