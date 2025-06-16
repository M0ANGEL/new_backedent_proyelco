<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class DatosProveedorExport implements FromView
{
    protected $datos;

    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    public function view(): View
    {
        return view('exports.proveedor', [
            'datos' => $this->datos
        ]);
    }
}
