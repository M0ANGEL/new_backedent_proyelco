<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

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
            'Ubicación Actual',
            'Usuarios Asignados',
            'Creado por',
            'Fecha Creación'
        ];
    }
    
    public function styles(Worksheet $sheet)
    {
        // Aplicar estilos al encabezado
        $sheet->getStyle('A1:V1')->getFont()->setBold(true);
        $sheet->getStyle('A1:V1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFCCCCCC');
        
        // Centrar vertical y horizontalmente todo el contenido
        $sheet->getStyle('A1:V' . (count($this->data) + 1))
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setWrapText(true);
        
        // Ajustar ancho para columnas con texto largo
        $sheet->getColumnDimension('F')->setWidth(30); // Descripción
        $sheet->getColumnDimension('G')->setWidth(15); // Valor
        $sheet->getColumnDimension('T')->setWidth(25); // Ubicación Actual
        $sheet->getColumnDimension('U')->setWidth(30); // Usuarios Asignados
        
        // Aplicar bordes a todas las celdas
        $lastRow = count($this->data) + 1;
        $sheet->getStyle("A1:V{$lastRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        return [];
    }
    
    public function columnFormats(): array
    {
        return [
            'G' => '#,##0.00', // Formato para la columna de valor
        ];
    }
}