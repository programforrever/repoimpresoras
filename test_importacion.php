<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de PhpSpreadsheet</h2>";

// Verificar que el autoload existe
$autoloadPath = __DIR__ . '/vendor/autoload.php';
echo "1. Verificando autoload en: $autoloadPath<br>";
if (file_exists($autoloadPath)) {
    echo "✅ Autoload existe<br>";
    require_once $autoloadPath;
} else {
    echo "❌ Autoload NO existe<br>";
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Verificar que podemos usar PhpSpreadsheet
echo "<br>2. Intentando crear un Spreadsheet...<br>";
try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'Test');
    
    echo "✅ Spreadsheet creado correctamente<br>";
    
    // Intentar guardar temporalmente
    echo "<br>3. Intentando guardar archivo...<br>";
    $tempFile = __DIR__ . '/uploads/temp/test.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);
    
    if (file_exists($tempFile)) {
        echo "✅ Archivo guardado correctamente en: $tempFile<br>";
        echo "Tamaño: " . filesize($tempFile) . " bytes<br>";
        unlink($tempFile);
        echo "✅ Archivo temporal eliminado<br>";
    } else {
        echo "❌ No se pudo guardar el archivo<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<br><br>4. Verificando extensiones PHP:<br>";
$extensiones = ['gd', 'zip', 'xml', 'xmlreader'];
foreach ($extensiones as $ext) {
    $loaded = extension_loaded($ext);
    echo ($loaded ? '✅' : '❌') . " $ext: " . ($loaded ? 'Cargada' : 'NO cargada') . "<br>";
}

echo "<br><br>5. Intentando descargar plantilla de marcas...<br>";
echo '<a href="controllers/importacion.php?action=descargarPlantilla&tipo=marcas" target="_blank">Descargar Plantilla Marcas</a><br>';
echo '<a href="controllers/importacion.php?action=descargarPlantilla&tipo=modelos" target="_blank">Descargar Plantilla Modelos</a><br>';
echo '<a href="controllers/importacion.php?action=descargarPlantilla&tipo=equipos" target="_blank">Descargar Plantilla Equipos</a><br>';
