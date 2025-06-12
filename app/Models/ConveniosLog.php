<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConveniosLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'convenio_id',
        'usuario_id',
        'accion',
        'data',
        'old',
    ];

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}
