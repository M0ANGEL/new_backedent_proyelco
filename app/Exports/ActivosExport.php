<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ActivosExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithColumnFormatting
{
    protected $data;
    
    public function __construct(array $data)
    {
        $this->data = $data;
    }
    
    public function array(): array
    {
        return $this->data;
    }
    
    public function headings(): array
    {
        return [
            'N°',
            'Número de Activo',
            'Prefijo Categoría',
            'Categoría',
            'Subcategoría',
            'Descripción',
            'Valor',
            'Marca',
            'Serial',
            'Condición',
            'Estado',
            'Aceptación',
            'Tipo Ubicación',
            'Tipo Activo',
            'Origen Activo',
            'Fecha Compra',
            'Fecha Alquiler',
            'Proveedor',
            'Ubicación Actual', // Nuevo encabezado
            'Usuarios Asignados', // Nuevo encabezado
            'Creado por',
            'Fecha Creación'
        ];
    }
    
    public function styles(Worksheet $sheet)
    {
        // Aplicar estilos
        $sheet->getStyle('A1:V1')->getFont()->setBold(true); // Cambiado a V por las nuevas columnas
        $sheet->getStyle('A1:V1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFCCCCCC');
        
        // Alinear verticalmente todas las celdas
        $sheet->getStyle('A2:V' . (count($this->data) + 1))
            ->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        
        // Ajustar ancho para columnas con texto largo
        $sheet->getColumnDimension('F')->setWidth(30); // Descripción
        $sheet->getColumnDimension('T')->setWidth(30); // Ubicación Actual
        $sheet->getColumnDimension('U')->setWidth(30); // Usuarios Asignados
        
        return [
            // Estilo para el encabezado
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => 'CCCCCC']
                ]
            ],
        ];
    }
    
    public function columnFormats(): array
    {
        return [
            // Formato para la columna de valor (columna G)
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
        ];
    }
}