<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CalculoHorasSheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
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
            'Cargo',
            'Ubicación',
            'Contratista',
            'Primera Entrada',
            'Última Salida',
            'Horas Calculadas',
            'Horas Normales',
            'Horas Extras',
            'Horas Nocturnas',
            'Horas Extras Nocturnas',
            'Estado'
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
                'startColor' => ['rgb' => '1E6F1E'], // Verde para cálculo de horas
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

        // Resaltar diferentes tipos de horas
        foreach (range(2, $lastRow) as $row) {
            $horasCalculadas = $sheet->getCell('J' . $row)->getValue();
            if ($horasCalculadas !== 'Sin calcular' && $horasCalculadas !== 'N/A') {
                // Horas normales - verde claro
                $sheet->getStyle("J{$row}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '1E6F1E'],
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E8F5E8'],
                    ],
                ]);
                
                // Horas extras - naranja claro
                $horasExtras = $sheet->getCell('L' . $row)->getValue();
                if ($horasExtras !== '00:00') {
                    $sheet->getStyle("L{$row}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => 'E67E22'],
                        ],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FEF5E7'],
                        ],
                    ]);
                }
                
                // Horas nocturnas - azul claro
                $horasNocturnas = $sheet->getCell('M' . $row)->getValue();
                if ($horasNocturnas !== '00:00') {
                    $sheet->getStyle("M{$row}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => '2980B9'],
                        ],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'EBF5FB'],
                        ],
                    ]);
                }
                
                // Horas extras nocturnas - rojo claro
                $horasExtrasNocturnas = $sheet->getCell('N' . $row)->getValue();
                if ($horasExtrasNocturnas !== '00:00') {
                    $sheet->getStyle("N{$row}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => 'C0392B'],
                        ],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FDEDEC'],
                        ],
                    ]);
                }
            }
            
            // Colorear estado
            $estado = $sheet->getCell('O' . $row)->getValue();
            if ($estado === 'COMPLETADO') {
                $sheet->getStyle("O{$row}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '1E6F1E'],
                    ],
                ]);
            } elseif ($estado === 'EN CURSO') {
                $sheet->getStyle("O{$row}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'E67E22'],
                    ],
                ]);
            }
        }

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
            'B' => 25, // Nombre Completo
            'C' => 15, // Identificación
            'D' => 15, // Tipo Documento
            'E' => 20, // Cargo
            'F' => 20, // Ubicación
            'G' => 20, // Contratista
            'H' => 18, // Primera Entrada
            'I' => 18, // Última Salida
            'J' => 15, // Horas Calculadas
            'K' => 15, // Horas Normales
            'L' => 15, // Horas Extras
            'M' => 15, // Horas Nocturnas
            'N' => 18, // Horas Extras Nocturnas
            'O' => 15, // Estado
        ];
    }

    public function title(): string
    {
        return 'Cálculo de Horas';
    }
}