<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración UTF-8
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/ImportacionMasiva.php';

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Verificar autenticación EXCEPTO para descargar plantillas (son vacías, no hay riesgo)
if ($action !== 'descargarPlantilla' && !isset($_SESSION['user_id'])) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$importModel = new ImportacionMasiva();
$db = getDB();

// Solo configurar header JSON si NO es descarga de plantilla
if ($action !== 'descargarPlantilla') {
    header('Content-Type: application/json; charset=UTF-8');
}

try {
    switch ($action) {
        case 'descargarPlantilla':
            descargarPlantilla($_GET['tipo'] ?? '');
            break;
            
        case 'subirArchivo':
            subirArchivo();
            break;
            
        case 'procesarImportacion':
            procesarImportacion();
            break;
            
        case 'previsualizar':
            previsualizar();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    // Si es descarga de plantilla, mostrar error en texto plano
    if ($action === 'descargarPlantilla') {
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Error al generar plantilla: ' . $e->getMessage();
        echo "\n\nStack trace:\n" . $e->getTraceAsString();
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Descarga plantilla Excel según el tipo
 */
function descargarPlantilla($tipo) {
    global $importModel;
    
    // Limpiar cualquier salida previa
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    $spreadsheet = null;
    
    switch ($tipo) {
        case 'marcas':
            $spreadsheet = $importModel->generarPlantillaMarcas();
            break;
        case 'modelos':
            $spreadsheet = $importModel->generarPlantillaModelos();
            break;
        case 'equipos':
            $spreadsheet = $importModel->generarPlantillaEquipos();
            break;
        default:
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => 'Tipo de plantilla no válido']);
            exit;
    }
    
    // Configurar headers para descarga
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="plantilla_' . $tipo . '.xlsx"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Sube y valida archivo Excel
 */
function subirArchivo() {
    if (!isset($_FILES['archivo'])) {
        echo json_encode(['success' => false, 'message' => 'No se recibió ningún archivo']);
        return;
    }
    
    $file = $_FILES['archivo'];
    $tipo = $_POST['tipo'] ?? '';
    
    // Validar tipo
    if (!in_array($tipo, ['marcas', 'modelos', 'equipos'])) {
        echo json_encode(['success' => false, 'message' => 'Tipo de importación no válido']);
        return;
    }
    
    // Validar extensión
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['xlsx', 'xls'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Formato no válido. Solo se aceptan archivos .xlsx o .xls'
        ]);
        return;
    }
    
    // Validar tamaño (máximo 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode([
            'success' => false,
            'message' => 'El archivo es demasiado grande. Máximo 5MB permitido'
        ]);
        return;
    }
    
    // Crear directorio temporal si no existe
    $uploadDir = __DIR__ . '/../uploads/temp/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Generar nombre único
    $nombreArchivo = uniqid('import_') . '.' . $extension;
    $rutaDestino = $uploadDir . $nombreArchivo;
    
    // Mover archivo
    if (move_uploaded_file($file['tmp_name'], $rutaDestino)) {
        // Guardar información en sesión
        $_SESSION['import_file'] = $rutaDestino;
        $_SESSION['import_tipo'] = $tipo;
        
        echo json_encode([
            'success' => true,
            'message' => 'Archivo subido correctamente',
            'archivo' => $nombreArchivo
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al subir el archivo'
        ]);
    }
}

/**
 * Previsualiza datos del Excel antes de importar
 */
function previsualizar() {
    global $importModel;
    
    if (!isset($_SESSION['import_file'])) {
        echo json_encode([
            'success' => false,
            'message' => 'No hay archivo cargado para previsualizar'
        ]);
        return;
    }
    
    $filePath = $_SESSION['import_file'];
    
    if (!file_exists($filePath)) {
        echo json_encode([
            'success' => false,
            'message' => 'El archivo temporal no existe'
        ]);
        return;
    }
    
    $resultado = $importModel->leerExcel($filePath);
    
    if ($resultado['success']) {
        // Limitar preview a primeras 10 filas
        $preview = array_slice($resultado['data'], 0, 10);
        
        echo json_encode([
            'success' => true,
            'headers' => $resultado['headers'],
            'preview' => $preview,
            'total' => $resultado['total']
        ]);
    } else {
        echo json_encode($resultado);
    }
}

/**
 * Procesa la importación según el tipo
 */
function procesarImportacion() {
    global $importModel, $db;
    
    if (!isset($_SESSION['import_file']) || !isset($_SESSION['import_tipo'])) {
        echo json_encode([
            'success' => false,
            'message' => 'No hay archivo o tipo de importación configurado'
        ]);
        return;
    }
    
    $filePath = $_SESSION['import_file'];
    $tipo = $_SESSION['import_tipo'];
    
    if (!file_exists($filePath)) {
        echo json_encode([
            'success' => false,
            'message' => 'El archivo temporal no existe'
        ]);
        return;
    }
    
    // Leer datos del Excel
    $lectura = $importModel->leerExcel($filePath);
    
    if (!$lectura['success']) {
        echo json_encode($lectura);
        return;
    }
    
    // Procesar según tipo
    $resultado = null;
    
    switch ($tipo) {
        case 'marcas':
            $resultado = $importModel->importarMarcas($lectura['data']);
            break;
        case 'modelos':
            $resultado = $importModel->importarModelos($lectura['data']);
            break;
        case 'equipos':
            $resultado = $importModel->importarEquipos($lectura['data']);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Tipo no válido']);
            return;
    }
    
    // Registrar auditoría
    if ($resultado['success']) {
        $usuario = $_SESSION['nombre_completo'] ?? 'Sistema';
        $detalles = json_encode([
            'tipo' => $tipo,
            'insertados' => $resultado['insertados'],
            'actualizados' => $resultado['actualizados'],
            'errores_count' => count($resultado['errores'])
        ]);
        
        registrarAuditoria(
            $db,
            $_SESSION['user_id'],
            "Importación masiva de $tipo",
            $detalles,
            $_SERVER['REMOTE_ADDR']
        );
    }
    
    // Limpiar archivos temporales
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    unset($_SESSION['import_file']);
    unset($_SESSION['import_tipo']);
    
    echo json_encode($resultado);
}

/**
 * Registra auditoría de importaciones
 */
function registrarAuditoria($db, $id_usuario, $accion, $detalles, $ip_address) {
    try {
        $stmt = $db->prepare("
            INSERT INTO auditoria (id_usuario, accion, detalles, ip_address, fecha) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$id_usuario, $accion, $detalles, $ip_address]);
    } catch (Exception $e) {
        error_log("Error al registrar auditoría: " . $e->getMessage());
    }
}
