<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpxUsu extends Model
{
    use HasFactory;

    protected $table = 'emp_x_usu';

    protected $fillable = [
        'id_empresa',
        'id_user'
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }
}
