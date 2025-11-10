<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteCompletoSheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
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
            'Tipo Registro',
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
        $sheet->getStyle('A1:P1')->applyFromArray([
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
        $sheet->getStyle("A1:P{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ]);

        // Colorear filas según el tipo
        foreach (range(2, $lastRow) as $row) {
            $tipo = $sheet->getCell('B' . $row)->getValue();
            if ($tipo === 'SIN ASISTENCIA') {
                $sheet->getStyle("A{$row}:P{$row}")->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFE6E6'],
                    ],
                ]);
            } elseif ($tipo === 'ASISTENCIA REGISTRADA') {
                $estado = $sheet->getCell('C' . $row)->getValue();
                if ($estado === 'EN CURSO') {
                    $sheet->getStyle("A{$row}:P{$row}")->applyFromArray([
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'E6F3FF'],
                        ],
                    ]);
                }
            }
        }

        // Centrar contenido verticalmente
        $sheet->getStyle("A1:P{$lastRow}")->getAlignment()->setVertical(
            \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
        );

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,  // N°
            'B' => 18, // Tipo Registro
            'C' => 15, // Estado
            'D' => 12, // Fecha Ingreso
            'E' => 10, // Hora Ingreso
            'F' => 12, // Fecha Salida
            'G' => 10, // Hora Salida
            'H' => 15, // Horas Laboradas
            'I' => 25, // Nombre Completo
            'J' => 15, // Identificación
            'K' => 15, // Tipo Documento
            'L' => 15, // Teléfono
            'M' => 20, // Cargo
            'N' => 20, // Ubicación
            'O' => 20, // Contratista
            'P' => 15, // Tipo Empleado
        ];
    }

    public function title(): string
    {
        return 'Reporte Completo';
    }
}