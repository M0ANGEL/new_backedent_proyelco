<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpxUsuLog extends Model
{
    use HasFactory;

    protected $table = "emp_x_usu_logs";

    protected $fillable = [
        'id_emp_x_usu',
        'id_user',
        'accion',
        'data',
        'old'
    ];

    public function usuarios()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function empresasxusuarios()
    {
        return $this->belongsTo(EmpxUsu::class, 'id_emp_x_usu');
    }
}
