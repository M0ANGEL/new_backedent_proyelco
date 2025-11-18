<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PersonasSinAsistenciaExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
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
            'Nombre Completo',
            'Identificación',
            'Tipo Documento',
            'Teléfono',
            'Cargo',
            'Obra Asignada',
            'Fecha Consulta',
            'Estado'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Estilo para los encabezados
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FF6B6B'], // Rojo para indicar ausencia
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Bordes para toda la tabla
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle("A1:I{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ]);

        // Centrar contenido
        $sheet->getStyle("A1:I{$lastRow}")->getAlignment()->setVertical(
            \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
        );

        // Centrar columnas específicas
        $sheet->getStyle("A:A")->getAlignment()->setHorizontal('center');
        $sheet->getStyle("I:I")->getAlignment()->setHorizontal('center');

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,  // N°
            'B' => 30, // Nombre Completo
            'C' => 15, // Identificación
            'D' => 15, // Tipo Documento
            'E' => 15, // Teléfono
            'F' => 20, // Cargo
            'G' => 20, // Obra Asignada
            'H' => 15, // Fecha Consulta
            'I' => 15, // Estado
        ];
    }

    public function title(): string
    {
        return 'Personas Sin Asistencia';
    }
}