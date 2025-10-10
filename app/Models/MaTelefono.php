<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaTelefono extends Model
{
    use HasFactory;

    protected $table = 'ma_telefono_th';

    protected $fillable = [
        'marca',
        'serial_email',
        'estado',
        'activo',
        'editserial'
    ];
}
