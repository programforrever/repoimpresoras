<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración UTF-8
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$tipo = $_GET['tipo'] ?? 'marcas';

echo "<h2>Generando plantilla de: $tipo</h2>";

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    switch ($tipo) {
        case 'marcas':
            $sheet->setCellValue('A1', 'Nombre Marca *');
            $sheet->setCellValue('B1', 'Descripción');
            $sheet->setCellValue('A2', 'HP');
            $sheet->setCellValue('B2', 'Hewlett-Packard');
            $sheet->setCellValue('A3', 'Epson');
            $sheet->setCellValue('B3', 'Impresoras Epson');
            break;
            
        case 'modelos':
            $sheet->setCellValue('A1', 'Marca *');
            $sheet->setCellValue('B1', 'Modelo *');
            $sheet->setCellValue('C1', 'Descripción');
            $sheet->setCellValue('A2', 'HP');
            $sheet->setCellValue('B2', 'LaserJet Pro M404dn');
            $sheet->setCellValue('C2', 'Impresora láser monocromática');
            break;
            
        case 'equipos':
            $sheet->setCellValue('A1', 'Código Patrimonial *');
            $sheet->setCellValue('B1', 'Número de Serie');
            $sheet->setCellValue('C1', 'Marca');
            $sheet->setCellValue('D1', 'Modelo');
            $sheet->setCellValue('E1', 'Ubicación');
            $sheet->setCellValue('F1', 'Área');
            $sheet->setCellValue('G1', 'Responsable');
            $sheet->setCellValue('H1', 'Fecha Adquisición (DD/MM/YYYY)');
            $sheet->setCellValue('I1', 'Estado');
            
            $sheet->setCellValue('A2', 'IMP-001');
            $sheet->setCellValue('B2', 'SN123456789');
            $sheet->setCellValue('C2', 'HP');
            $sheet->setCellValue('D2', 'LaserJet Pro M404dn');
            $sheet->setCellValue('E2', 'Oficina Principal');
            $sheet->setCellValue('F2', 'Administración');
            $sheet->setCellValue('G2', 'Juan Pérez');
            $sheet->setCellValue('H2', '15/01/2024');
            $sheet->setCellValue('I2', 'Operativo');
            break;
    }
    
    // Estilos
    $sheet->getStyle('A1:I1')->getFont()->setBold(true);
    
    echo "✅ Spreadsheet creado<br>";
    
    // Limpiar buffer
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    // Headers para descarga
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="plantilla_' . $tipo . '.xlsx"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
