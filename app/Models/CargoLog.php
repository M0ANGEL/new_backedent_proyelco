<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CargoLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_cargo',
        'id_user',
        'accion',
        'data', 
        'old'
    ];

    public function usuarios()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function cargos()
    {
        return $this->belongsTo(Cargo::class, 'id_cargo');
    }
}
