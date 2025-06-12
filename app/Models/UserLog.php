<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_user',
        'id_operario',
        'accion',
        'data',
        'old'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function operario()
    {
        return $this->belongsTo(User::class, 'id_operario');
    }
}
