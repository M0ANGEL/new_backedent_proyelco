<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Exportable;

class ReporteCompletoConCalculoHorasExport implements WithMultipleSheets
{
    use Exportable;

    protected $reporteCompleto;
    protected $calculosHoras;

    public function __construct($reporteCompleto, $calculosHoras)
    {
        $this->reporteCompleto = $reporteCompleto;
        $this->calculosHoras = $calculosHoras;
    }

    public function sheets(): array
    {
        $sheets = [];

        // Hoja 1: Reporte Completo (original)
        $sheets[] = new ReporteCompletoSheet($this->reporteCompleto);
        
        // Hoja 2: Cálculo de Horas
        $sheets[] = new CalculoHorasSheet($this->calculosHoras);

        return $sheets;
    }
}