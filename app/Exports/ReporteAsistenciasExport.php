<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteAsistenciasExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        return collect($this->data);
    }

    public function headings(): array
    {
        return [
            'N°',
            'Estado',
            'Fecha Ingreso',
            'Hora Ingreso',
            'Fecha Salida', 
            'Hora Salida',
            'Horas Laboradas',
            'Nombre Completo',
            'Identificación',
            'Tipo Documento',
            'Teléfono',
            'Cargo',
            'Ubicación',
            'Contratista',
            'Tipo Empleado'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Estilo para los encabezados
        $sheet->getStyle('A1:O1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2C3E50'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Bordes para toda la tabla
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle("A1:O{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ]);

        // Centrar contenido verticalmente
        $sheet->getStyle("A1:O{$lastRow}")->getAlignment()->setVertical(
            \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
        );

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,  // N°
            'B' => 12, // Estado
            'C' => 12, // Fecha Ingreso
            'D' => 10, // Hora Ingreso
            'E' => 12, // Fecha Salida
            'F' => 10, // Hora Salida
            'G' => 15, // Horas Laboradas
            'H' => 25, // Nombre Completo
            'I' => 15, // Identificación
            'J' => 15, // Tipo Documento
            'K' => 15, // Teléfono
            'L' => 20, // Cargo
            'M' => 20, // Ubicación
            'N' => 20, // Contratista
            'O' => 15, // Tipo Empleado
        ];
    }

    public function title(): string
    {
        return 'Reporte Asistencias';
    }
}