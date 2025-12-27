<?php
/**
 * Controlador de Mantenimientos
 */

// Configurar codificación UTF-8
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Mantenimiento.php';
require_once __DIR__ . '/../includes/functions.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isLoggedIn()) {
    http_response_code(403);
    die('Acceso denegado');
}

$database = new Database();
$db = $database->getConnection();
$mantenimientoModel = new Mantenimiento($db);

/**
 * Verificar si existe la columna activo en la tabla mantenimientos
 */
function hasActivoColumn($db) {
    try {
        $sql = "SHOW COLUMNS FROM mantenimientos LIKE 'activo'";
        $stmt = $db->query($sql);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'create':
        crearMantenimiento();
        break;
    
    case 'update':
        actualizarMantenimiento();
        break;
    
    case 'get':
        obtenerMantenimiento();
        break;
    
    case 'getAll':
        obtenerTodosMantenimientos();
        break;
    
    case 'getByEquipo':
        obtenerMantenimientosPorEquipo();
        break;
    
    case 'delete':
        eliminarMantenimiento();
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Acción no válida']);
        break;
}

/**
 * Crear mantenimiento
 */
function crearMantenimiento() {
    global $mantenimientoModel, $db;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $data = [
            'id_equipo' => intval($_POST['id_equipo']),
            'id_tipo_demanda' => intval($_POST['id_tipo_demanda']),
            'fecha_mantenimiento' => $_POST['fecha_mantenimiento'],
            'descripcion' => $_POST['descripcion'] ?? '',
            'tecnico_responsable' => $_POST['tecnico_responsable'] ?? '',
            'observaciones' => $_POST['observaciones'] ?? '',
            'id_estado_anterior' => !empty($_POST['id_estado_anterior']) ? intval($_POST['id_estado_anterior']) : null,
            'id_estado_nuevo' => !empty($_POST['id_estado_nuevo']) ? intval($_POST['id_estado_nuevo']) : null,
            'id_usuario_registro' => $_SESSION['user_id']
        ];
        
        // Si hay cambio de estado, actualizar el equipo
        if ($data['id_estado_nuevo'] && $data['id_estado_nuevo'] != $data['id_estado_anterior']) {
            $sqlUpdate = "UPDATE equipos SET id_estado = :id_estado, id_usuario_actualizacion = :id_usuario WHERE id = :id_equipo";
            $stmtUpdate = $db->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':id_estado' => $data['id_estado_nuevo'],
                ':id_usuario' => $_SESSION['user_id'],
                ':id_equipo' => $data['id_equipo']
            ]);
        }
        
        $result = $mantenimientoModel->create($data);
        
        if ($result) {
            // Preparar datos legibles para auditoría
            $datosLegibles = prepararDatosMantenimientoParaAuditoria($db, $data);
            
            // Registrar auditoría
            logAudit($db, 'mantenimientos', $result, 'INSERT', null, $datosLegibles);
            
            echo json_encode(['success' => true, 'message' => 'Mantenimiento registrado exitosamente']);
        } else {
            throw new Exception("Error al registrar el mantenimiento");
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Actualizar mantenimiento
 */
function actualizarMantenimiento() {
    global $mantenimientoModel, $db;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $id = intval($_POST['id']);
        
        // Obtener datos anteriores
        $datosAnteriores = $mantenimientoModel->getById($id);
        
        $data = [
            'id_equipo' => intval($_POST['id_equipo']),
            'id_tipo_demanda' => intval($_POST['id_tipo_demanda']),
            'fecha_mantenimiento' => $_POST['fecha_mantenimiento'],
            'descripcion' => $_POST['descripcion'] ?? '',
            'tecnico_responsable' => $_POST['tecnico_responsable'] ?? '',
            'observaciones' => $_POST['observaciones'] ?? '',
            'id_estado_anterior' => !empty($_POST['id_estado_anterior']) ? intval($_POST['id_estado_anterior']) : null,
            'id_estado_nuevo' => !empty($_POST['id_estado_nuevo']) ? intval($_POST['id_estado_nuevo']) : null
        ];
        
        // Si hay cambio de estado, actualizar el equipo
        if ($data['id_estado_nuevo'] && $data['id_estado_nuevo'] != $data['id_estado_anterior']) {
            $sqlUpdate = "UPDATE equipos SET id_estado = :id_estado, id_usuario_actualizacion = :id_usuario WHERE id = :id_equipo";
            $stmtUpdate = $db->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':id_estado' => $data['id_estado_nuevo'],
                ':id_usuario' => $_SESSION['user_id'],
                ':id_equipo' => $data['id_equipo']
            ]);
        }
        
        $result = $mantenimientoModel->update($id, $data);
        
        if ($result) {
            // Preparar datos legibles para auditoría
            $datosAnterioresLegibles = prepararDatosMantenimientoParaAuditoria($db, $datosAnteriores);
            $datosNuevosLegibles = prepararDatosMantenimientoParaAuditoria($db, $data);
            
            // Registrar auditoría
            logAudit($db, 'mantenimientos', $id, 'UPDATE', $datosAnterioresLegibles, $datosNuevosLegibles);
            
            echo json_encode(['success' => true, 'message' => 'Mantenimiento actualizado exitosamente']);
        } else {
            throw new Exception("Error al actualizar el mantenimiento");
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Obtener mantenimiento
 */
function obtenerMantenimiento() {
    global $mantenimientoModel;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            throw new Exception('ID no proporcionado');
        }
        
        $mantenimiento = $mantenimientoModel->getById($id);
        
        if ($mantenimiento) {
            echo json_encode(['success' => true, 'data' => $mantenimiento]);
        } else {
            throw new Exception('Mantenimiento no encontrado');
        }
    } catch (Exception $e) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Obtener todos los mantenimientos
 */
function obtenerTodosMantenimientos() {
    global $mantenimientoModel;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $mantenimientos = $mantenimientoModel->getAll();
        echo json_encode(['success' => true, 'data' => $mantenimientos]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Obtener mantenimientos por equipo
 */
function obtenerMantenimientosPorEquipo() {
    global $db;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        if (!isset($_GET['id_equipo'])) {
            throw new Exception("ID de equipo no proporcionado");
        }
        
        $id_equipo = intval($_GET['id_equipo']);
        
        // Verificar que la conexión existe
        if (!$db) {
            throw new Exception("No hay conexión a la base de datos");
        }
        
        // Verificar si existe la columna activo
        $hasActivo = hasActivoColumn($db);
        
        $sql = "SELECT m.id, m.fecha_mantenimiento, m.descripcion, m.observaciones, 
                m.tecnico_responsable, m.id_estado_anterior, m.id_estado_nuevo,
                td.nombre as tipo_demanda,
                est_ant.nombre as estado_anterior,
                est_nue.nombre as estado_nuevo,
                u.nombre_completo as usuario_registro
                FROM mantenimientos m
                LEFT JOIN tipos_demanda td ON m.id_tipo_demanda = td.id
                LEFT JOIN estados_equipo est_ant ON m.id_estado_anterior = est_ant.id
                LEFT JOIN estados_equipo est_nue ON m.id_estado_nuevo = est_nue.id
                LEFT JOIN usuarios u ON m.id_usuario_registro = u.id
                WHERE m.id_equipo = :id_equipo";
        
        // Solo agregar filtro de activo si la columna existe
        if ($hasActivo) {
            $sql .= " AND m.activo = 1";
        }
        
        $sql .= " ORDER BY m.fecha_mantenimiento DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id_equipo', $id_equipo, PDO::PARAM_INT);
        $stmt->execute();
        
        $mantenimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $mantenimientos]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Eliminar mantenimiento
 */
function eliminarMantenimiento() {
    global $mantenimientoModel, $db;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $id = $_POST['id'] ?? null;
        
        if (!$id) {
            throw new Exception("ID no proporcionado");
        }
        
        // Obtener datos anteriores para auditoría
        $datosAnteriores = $mantenimientoModel->getById($id);
        
        // Eliminar usando SQL directo ya que el modelo no tiene método delete
        $query = "UPDATE mantenimientos SET activo = 0 WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $result = $stmt->execute();
        
        if ($result) {
            // Registrar auditoría
            $datosAnterioresLegibles = prepararDatosMantenimientoParaAuditoria($db, $datosAnteriores);
            logAudit($db, 'mantenimientos', $id, 'DELETE', $datosAnterioresLegibles, null);
            
            echo json_encode(['success' => true, 'message' => 'Mantenimiento eliminado exitosamente']);
        } else {
            throw new Exception("Error al eliminar el mantenimiento");
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Preparar datos de mantenimiento para auditoría (convertir IDs a nombres)
 */
function prepararDatosMantenimientoParaAuditoria($db, $data) {
    if (!$data) return null;
    
    $datosLegibles = [];
    
    // Copiar campos simples
    $camposSimples = ['fecha_mantenimiento', 'descripcion', 'tecnico_responsable', 'observaciones'];
    
    foreach ($camposSimples as $campo) {
        if (isset($data[$campo])) {
            $datosLegibles[$campo] = $data[$campo];
        }
    }
    
    // Convertir IDs a nombres
    if (isset($data['id_equipo'])) {
        $stmt = $db->prepare("SELECT codigo_patrimonial FROM equipos WHERE id = :id");
        $stmt->execute([':id' => $data['id_equipo']]);
        $datosLegibles['equipo'] = $stmt->fetchColumn() ?: 'ID: ' . $data['id_equipo'];
    }
    
    if (isset($data['id_tipo_demanda'])) {
        $stmt = $db->prepare("SELECT nombre FROM tipos_demanda WHERE id = :id");
        $stmt->execute([':id' => $data['id_tipo_demanda']]);
        $datosLegibles['tipo_demanda'] = $stmt->fetchColumn() ?: 'ID: ' . $data['id_tipo_demanda'];
    }
    
    if (isset($data['id_estado_anterior'])) {
        $stmt = $db->prepare("SELECT nombre FROM estados_equipo WHERE id = :id");
        $stmt->execute([':id' => $data['id_estado_anterior']]);
        $datosLegibles['estado_anterior'] = $stmt->fetchColumn() ?: 'ID: ' . $data['id_estado_anterior'];
    }
    
    if (isset($data['id_estado_nuevo'])) {
        $stmt = $db->prepare("SELECT nombre FROM estados_equipo WHERE id = :id");
        $stmt->execute([':id' => $data['id_estado_nuevo']]);
        $datosLegibles['estado_nuevo'] = $stmt->fetchColumn() ?: 'ID: ' . $data['id_estado_nuevo'];
    }
    
    if (isset($data['id_usuario_registro'])) {
        $stmt = $db->prepare("SELECT nombre_completo FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $data['id_usuario_registro']]);
        $datosLegibles['usuario_registro'] = $stmt->fetchColumn() ?: 'ID: ' . $data['id_usuario_registro'];
    }
    
    return $datosLegibles;
}
?>
