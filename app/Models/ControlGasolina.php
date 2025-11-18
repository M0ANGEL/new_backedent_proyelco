<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ControlGasolina extends Model
{
    use HasFactory;

    protected $table = 'control_gasolina';
    protected $fillable = [
        'empleado_id',
        'user_id',
        'no_factura_venta',
        'fecha_factura',
        'corte',
        'placa',
        'comprobante',
        'combustible',
        'ppu',
        'volumen',
        'km',
        'dinero',
    ];

    // En el modelo ControlGasolina
public function empleado()
{
    return $this->belongsTo(EmpleadoProyelco::class, 'empleado_id');
}

public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}
}
