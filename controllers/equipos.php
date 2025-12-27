<?php
/**
 * Controlador de Equipos
 * RF-03: Gestión completa de equipos con carga de imágenes
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Equipo.php';
require_once __DIR__ . '/../includes/functions.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isLoggedIn()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

// Configurar header JSON para respuestas AJAX
if (isset($_POST['action']) || isset($_GET['action'])) {
    header('Content-Type: application/json; charset=UTF-8');
}

$database = new Database();
$db = $database->getConnection();
$equipoModel = new Equipo($db);

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'create':
        crearEquipo();
        break;
    
    case 'update':
        actualizarEquipo();
        break;
    
    case 'get':
        obtenerEquipo();
        break;
    
    case 'delete':
        eliminarEquipo();
        break;
    
    case 'getModelos':
        getModelos();
        break;
    
    case 'getAuditoria':
        getAuditoria();
        break;
    
    case 'cambiarEstado':
        cambiarEstadoEquipo();
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Acción no válida']);
}

/**
 * Crear nuevo equipo
 */
function crearEquipo() {
    global $equipoModel, $db;
    
    try {
        // Validar datos requeridos
        $required = ['codigo_patrimonial', 'clasificacion', 'id_marca', 'id_modelo', 'id_estado'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("El campo $field es requerido");
            }
        }
        
        // Obtener nombres de marca y modelo
        $id_marca = intval($_POST['id_marca']);
        $id_modelo = intval($_POST['id_modelo']);
        
        $stmt = $db->prepare("SELECT nombre FROM marcas WHERE id = :id");
        $stmt->execute([':id' => $id_marca]);
        $marca = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT nombre FROM modelos WHERE id = :id");
        $stmt->execute([':id' => $id_modelo]);
        $modelo = $stmt->fetchColumn();
        
        // Preparar datos
        $data = [
            'codigo_patrimonial' => sanitize($_POST['codigo_patrimonial']),
            'clasificacion' => sanitize($_POST['clasificacion']),
            'marca' => $marca,
            'modelo' => $modelo,
            'id_marca' => $id_marca,
            'id_modelo' => $id_modelo,
            'numero_serie' => sanitize($_POST['numero_serie'] ?? ''),
            'garantia' => sanitize($_POST['garantia'] ?? ''),
            'id_estado' => intval($_POST['id_estado']),
            'tiene_estabilizador' => isset($_POST['tiene_estabilizador']) ? 1 : 0,
            'anio_adquisicion' => !empty($_POST['anio_adquisicion']) ? intval($_POST['anio_adquisicion']) : null,
            
            'id_distrito' => !empty($_POST['id_distrito']) ? intval($_POST['id_distrito']) : null,
            'id_sede' => !empty($_POST['id_sede']) ? intval($_POST['id_sede']) : null,
            'id_macro_proceso' => !empty($_POST['id_macro_proceso']) ? intval($_POST['id_macro_proceso']) : null,
            'ubicacion_fisica' => sanitize($_POST['ubicacion_fisica'] ?? ''),
            'id_despacho' => !empty($_POST['id_despacho']) ? intval($_POST['id_despacho']) : null,
            'id_usuario_final' => !empty($_POST['id_usuario_final']) ? intval($_POST['id_usuario_final']) : null,
            
            'observaciones' => sanitize($_POST['observaciones'] ?? ''),
            'id_usuario_creacion' => $_SESSION['user_id']
        ];
        
        // Manejar imagen si se subió
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $imagePath = uploadImage($_FILES['imagen']);
            if ($imagePath) {
                $data['imagen'] = $imagePath;
            }
        }
        
        // Crear equipo
        $result = $equipoModel->create($data);
        
        if ($result) {
            // Preparar datos legibles para auditoría
            $datosLegibles = prepararDatosEquipoParaAuditoria($db, $data);
            
            logAudit($db, 'equipos', $result, 'INSERT', null, $datosLegibles);
            setFlashMessage('success', 'Equipo creado exitosamente');
            redirect('views/equipos/index.php');
        } else {
            throw new Exception("Error al crear el equipo");
        }
        
    } catch (Exception $e) {
        setFlashMessage('danger', $e->getMessage());
        redirect('views/equipos/index.php');
    }
}

function actualizarEquipo() {
    global $equipoModel, $db;
    
    try {
        $id = intval($_POST['id']);
        
        if (empty($id)) {
            throw new Exception("ID de equipo no válido");
        }
        
        $datosAnteriores = $equipoModel->getById($id);
        
        // Obtener nombres de marca y modelo si se proporcionan IDs
        $marca = null;
        $modelo = null;
        $id_marca = null;
        $id_modelo = null;
        
        if (!empty($_POST['id_marca'])) {
            $id_marca = intval($_POST['id_marca']);
            $stmt = $db->prepare("SELECT nombre FROM marcas WHERE id = :id");
            $stmt->execute([':id' => $id_marca]);
            $marca = $stmt->fetchColumn();
        }
        
        if (!empty($_POST['id_modelo'])) {
            $id_modelo = intval($_POST['id_modelo']);
            $stmt = $db->prepare("SELECT nombre FROM modelos WHERE id = :id");
            $stmt->execute([':id' => $id_modelo]);
            $modelo = $stmt->fetchColumn();
        }
        
        $data = [
            'id' => $id,
            'codigo_patrimonial' => sanitize($_POST['codigo_patrimonial']),
            'clasificacion' => sanitize($_POST['clasificacion']),
            'marca' => $marca,
            'modelo' => $modelo,
            'id_marca' => $id_marca,
            'id_modelo' => $id_modelo,
            'numero_serie' => sanitize($_POST['numero_serie'] ?? ''),
            'garantia' => sanitize($_POST['garantia'] ?? ''),
            'id_estado' => intval($_POST['id_estado']),
            'tiene_estabilizador' => isset($_POST['tiene_estabilizador']) ? 1 : 0,
            'anio_adquisicion' => !empty($_POST['anio_adquisicion']) ? intval($_POST['anio_adquisicion']) : null,
            
            'id_distrito' => !empty($_POST['id_distrito']) ? intval($_POST['id_distrito']) : null,
            'id_sede' => !empty($_POST['id_sede']) ? intval($_POST['id_sede']) : null,
            'id_macro_proceso' => !empty($_POST['id_macro_proceso']) ? intval($_POST['id_macro_proceso']) : null,
            'ubicacion_fisica' => sanitize($_POST['ubicacion_fisica'] ?? ''),
            'id_despacho' => !empty($_POST['id_despacho']) ? intval($_POST['id_despacho']) : null,
            'id_usuario_final' => !empty($_POST['id_usuario_final']) ? intval($_POST['id_usuario_final']) : null,
            
            'observaciones' => sanitize($_POST['observaciones'] ?? ''),
            'id_usuario_actualizacion' => $_SESSION['user_id']
        ];
        
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $imagePath = uploadImage($_FILES['imagen']);
            if ($imagePath) {
                if (!empty($datosAnteriores['imagen'])) {
                    deleteImage($datosAnteriores['imagen']);
                }
                $data['imagen'] = $imagePath;
            }
        }
        
        $result = $equipoModel->update($id, $data);
        
        if ($result) {
            // Preparar datos legibles para auditoría
            $datosAnterioresLegibles = prepararDatosEquipoParaAuditoria($db, $datosAnteriores);
            $datosNuevosLegibles = prepararDatosEquipoParaAuditoria($db, $data);
            
            logAudit($db, 'equipos', $id, 'UPDATE', $datosAnterioresLegibles, $datosNuevosLegibles);
            setFlashMessage('success', 'Equipo actualizado exitosamente');
            redirect('views/equipos/index.php');
        } else {
            throw new Exception("Error al actualizar el equipo");
        }
        
    } catch (Exception $e) {
        setFlashMessage('danger', $e->getMessage());
        redirect('views/equipos/index.php');
    }
}

function obtenerEquipo() {
    global $equipoModel;
    
    try {
        $id = intval($_GET['id'] ?? 0);
        
        if (empty($id)) {
            throw new Exception("ID no válido");
        }
        
        $equipo = $equipoModel->getById($id);
        
        if (!$equipo) {
            throw new Exception("Equipo no encontrado");
        }
        
        echo json_encode($equipo);
        
    } catch (Exception $e) {
        http_response_code(404);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function eliminarEquipo() {
    global $equipoModel, $db;
    
    try {
        $id = intval($_POST['id'] ?? 0);
        
        if (empty($id)) {
            throw new Exception("ID no válido");
        }
        
        $datosAnteriores = $equipoModel->getById($id);
        
        if (!$datosAnteriores) {
            throw new Exception("Equipo no encontrado");
        }
        
        $result = $equipoModel->delete($id);
        
        if ($result) {
            logAudit($db, 'equipos', $id, 'DELETE', $datosAnteriores, null);
            echo json_encode(['success' => true, 'message' => 'Equipo eliminado exitosamente']);
        } else {
            throw new Exception("Error al eliminar el equipo");
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function uploadImage($file) {
    $targetDir = __DIR__ . '/../uploads/equipos/';
    
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    // Verificar por extensión (más confiable)
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($extension, $allowedExtensions)) {
        throw new Exception("Tipo de archivo no permitido. Use: JPG, PNG, GIF o WEBP");
    }
    
    // Verificar también por MIME type si está disponible
    $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!empty($file['type']) && !in_array($file['type'], $allowedMimes)) {
        // Log para debugging
        error_log("MIME type recibido: " . $file['type']);
        // Pero no fallar, porque algunos navegadores no lo envían correctamente
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("El archivo es demasiado grande. Máximo 5MB");
    }
    
    $fileName = uniqid('equipo_') . '.' . $extension;
    $targetPath = $targetDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'uploads/equipos/' . $fileName;
    }
    
    throw new Exception("Error al subir el archivo");
}

function deleteImage($imagePath) {
    $fullPath = __DIR__ . '/../' . $imagePath;
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }
}

/**
 * Obtener modelos por marca
 */
function getModelos() {
    global $equipoModel;
    
    $id_marca = $_GET['id_marca'] ?? null;
    
    error_log("getModelos llamado con id_marca: " . $id_marca);
    
    if ($id_marca) {
        $modelos = $equipoModel->getModelos($id_marca);
        error_log("Modelos encontrados: " . count($modelos));
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($modelos);
        exit;
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'ID de marca no proporcionado']);
        exit;
    }
}

/**
 * Obtener historial de auditoría de un equipo
 */
function getAuditoria() {
    global $equipoModel;
    
    $id_equipo = $_GET['id'] ?? null;
    
    if (!$id_equipo) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de equipo no proporcionado']);
        exit;
    }
    
    try {
        $auditoria = $equipoModel->getAuditoria($id_equipo);
        
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($auditoria);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

/**
 * Cambiar estado de equipo
 */
function cambiarEstadoEquipo() {
    global $db;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $id = intval($_POST['id']);
        $id_estado = intval($_POST['id_estado']);
        $observacion = $_POST['observacion'] ?? '';
        
        if (!$id || !$id_estado) {
            throw new Exception("Datos incompletos");
        }
        
        // Obtener estado anterior con nombre
        $sqlEstado = "SELECT e.id_estado, est.nombre as estado_nombre 
                     FROM equipos e 
                     LEFT JOIN estados_equipo est ON e.id_estado = est.id 
                     WHERE e.id = :id";
        $stmtEstado = $db->prepare($sqlEstado);
        $stmtEstado->execute([':id' => $id]);
        $estadoAnteriorData = $stmtEstado->fetch(PDO::FETCH_ASSOC);
        
        // Obtener nombre del nuevo estado
        $sqlNuevoEstado = "SELECT nombre FROM estados_equipo WHERE id = :id";
        $stmtNuevoEstado = $db->prepare($sqlNuevoEstado);
        $stmtNuevoEstado->execute([':id' => $id_estado]);
        $estadoNuevoNombre = $stmtNuevoEstado->fetchColumn();
        
        // Actualizar estado del equipo
        $sql = "UPDATE equipos SET id_estado = :id_estado, id_usuario_actualizacion = :id_usuario WHERE id = :id";
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            ':id_estado' => $id_estado,
            ':id_usuario' => $_SESSION['user_id'],
            ':id' => $id
        ]);
        
        if ($result) {
            // Registrar auditoría con cambio de estado (guardando nombres, no IDs)
            logAudit($db, 'equipos', $id, 'UPDATE', 
                ['estado' => $estadoAnteriorData['estado_nombre'] ?? 'Sin estado'], 
                ['estado' => $estadoNuevoNombre, 'observacion' => $observacion]
            );
            
            echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
        } else {
            throw new Exception("Error al actualizar el estado");
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Preparar datos de equipo para auditoría (convertir IDs a nombres)
 */
function prepararDatosEquipoParaAuditoria($db, $data) {
    if (!$data) return null;
    
    $datosLegibles = [];
    
    // Copiar campos simples
    $camposSimples = ['codigo_patrimonial', 'clasificacion', 'numero_serie', 'garantia', 
                     'tiene_estabilizador', 'anio_adquisicion', 'ubicacion_fisica', 
                     'observaciones', 'imagen', 'marca', 'modelo'];
    
    foreach ($camposSimples as $campo) {
        if (isset($data[$campo])) {
            $datosLegibles[$campo] = $data[$campo];
        }
    }
    
    // Convertir IDs a nombres
    if (isset($data['id_estado'])) {
        $stmt = $db->prepare("SELECT nombre FROM estados_equipo WHERE id = :id");
        $stmt->execute([':id' => $data['id_estado']]);
        $datosLegibles['estado'] = $stmt->fetchColumn() ?: 'ID: ' . $data['id_estado'];
    }
    
    if (isset($data['id_marca'])) {
        $stmt = $db->prepare("SELECT nombre FROM marcas WHERE id = :id");
        $stmt->execute([':id' => $data['id_marca']]);
        $datosLegibles['marca'] = $stmt->fetchColumn() ?: 'ID: ' . $data['id_marca'];
    }
    
    if (isset($data['id_modelo'])) {
        $stmt = $db->prepare("SELECT nombre FROM modelos WHERE id = :id");
        $stmt->execute([':id' => $data['id_modelo']]);
        $datosLegibles['modelo'] = $stmt->fetchColumn() ?: 'ID: ' . $data['id_modelo'];
    }
    
    if (isset($data['id_distrito'])) {
        $stmt = $db->prepare("SELECT nombre FROM distritos_fiscales WHERE id = :id");
        $stmt->execute([':id' => $data['id_distrito']]);
        $datosLegibles['distrito'] = $stmt->fetchColumn() ?: 'ID: ' . $data['id_distrito'];
    }
    
    if (isset($data['id_sede'])) {
        $stmt = $db->prepare("SELECT nombre FROM sedes WHERE id = :id");
        $stmt->execute([':id' => $data['id_sede']]);
        $datosLegibles['sede'] = $stmt->fetchColumn() ?: 'ID: ' . $data['id_sede'];
    }
    
    if (isset($data['id_macro_proceso'])) {
        $stmt = $db->prepare("SELECT nombre FROM macro_procesos WHERE id = :id");
        $stmt->execute([':id' => $data['id_macro_proceso']]);
        $datosLegibles['macro_proceso'] = $stmt->fetchColumn() ?: 'ID: ' . $data['id_macro_proceso'];
    }
    
    if (isset($data['id_despacho'])) {
        $stmt = $db->prepare("SELECT nombre FROM despachos WHERE id = :id");
        $stmt->execute([':id' => $data['id_despacho']]);
        $datosLegibles['despacho'] = $stmt->fetchColumn() ?: 'ID: ' . $data['id_despacho'];
    }
    
    if (isset($data['id_usuario_final'])) {
        $stmt = $db->prepare("SELECT nombre_completo FROM usuarios_finales WHERE id = :id");
        $stmt->execute([':id' => $data['id_usuario_final']]);
        $datosLegibles['usuario_final'] = $stmt->fetchColumn() ?: 'ID: ' . $data['id_usuario_final'];
    }
    
    return $datosLegibles;
}
