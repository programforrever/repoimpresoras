<?php
/**
 * API de Configuración
 * Gestión de entidades maestras del sistema
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación y permisos
if (!isLoggedIn() || !hasRole(ROL_ADMIN)) {
    http_response_code(403);
    die(json_encode(['error' => 'Acceso denegado']));
}

$database = new Database();
$db = $database->getConnection();

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'save':
        guardarEntidad();
        break;
    
    case 'toggle':
        toggleEstado();
        break;
    
    case 'getDistritos':
        getDistritos();
        break;
    
    case 'getSedes':
        getSedes();
        break;
    
    case 'getMarcas':
        getMarcas();
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Acción no válida']);
}

/**
 * Guardar o actualizar entidad
 */
function guardarEntidad() {
    global $db;
    
    try {
        $tabla = sanitize($_POST['tabla'] ?? '');
        $id = intval($_POST['id'] ?? 0);
        
        // Validar tabla permitida
        $tablasPermitidas = [
            'estados_equipo',
            'marcas',
            'modelos',
            'distritos_fiscales', 
            'sedes', 
            'macro_procesos', 
            'despachos', 
            'usuarios_finales'
        ];
        
        if (!in_array($tabla, $tablasPermitidas)) {
            throw new Exception("Tabla no válida");
        }
        
        // Preparar datos según la tabla
        $datos = prepararDatos($tabla, $_POST);
        
        if ($id > 0) {
            // Actualizar
            $resultado = actualizarRegistro($db, $tabla, $id, $datos);
            $mensaje = "Registro actualizado exitosamente";
        } else {
            // Crear
            $resultado = crearRegistro($db, $tabla, $datos);
            $mensaje = "Registro creado exitosamente";
        }
        
        if ($resultado) {
            // Registrar auditoría
            logAudit($db, $tabla, $id > 0 ? $id : $resultado, $id > 0 ? 'UPDATE' : 'INSERT', null, $datos);
            
            echo json_encode(['success' => true, 'message' => $mensaje]);
        } else {
            throw new Exception("Error al guardar el registro");
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Preparar datos según la tabla
 */
function prepararDatos($tabla, $post) {
    $datos = [];
    
    switch ($tabla) {
        case 'estados_equipo':
            $datos = [
                'nombre' => sanitize($post['nombre']),
                'descripcion' => sanitize($post['descripcion'] ?? ''),
                'color' => sanitize($post['color'] ?? $post['color_hex'] ?? '#6c757d')
            ];
            break;
        
        case 'marcas':
            $datos = [
                'nombre' => sanitize($post['nombre']),
                'descripcion' => sanitize($post['descripcion'] ?? '')
            ];
            break;
        
        case 'modelos':
            $datos = [
                'nombre' => sanitize($post['nombre']),
                'id_marca' => intval($post['id_marca']),
                'descripcion' => sanitize($post['descripcion'] ?? '')
            ];
            break;
        
        case 'distritos_fiscales':
            $datos = [
                'nombre' => sanitize($post['nombre']),
                'codigo' => sanitize($post['codigo'] ?? '')
            ];
            break;
        
        case 'sedes':
            $datos = [
                'nombre' => sanitize($post['nombre']),
                'direccion' => sanitize($post['direccion'] ?? ''),
                'id_distrito' => !empty($post['id_distrito']) ? intval($post['id_distrito']) : null
            ];
            break;
        
        case 'macro_procesos':
            $datos = [
                'nombre' => sanitize($post['nombre']),
                'descripcion' => sanitize($post['descripcion'] ?? '')
            ];
            break;
        
        case 'despachos':
            $datos = [
                'nombre' => sanitize($post['nombre']),
                'id_sede' => !empty($post['id_sede']) ? intval($post['id_sede']) : null
            ];
            break;
        
        case 'usuarios_finales':
            $datos = [
                'nombre_completo' => sanitize($post['nombre_completo']),
                'dni' => sanitize($post['dni'] ?? ''),
                'cargo' => sanitize($post['cargo'] ?? ''),
                'telefono' => sanitize($post['telefono'] ?? ''),
                'email' => sanitize($post['email'] ?? '')
            ];
            break;
    }
    
    return $datos;
}

/**
 * Crear registro
 */
function crearRegistro($db, $tabla, $datos) {
    $campos = array_keys($datos);
    $placeholders = array_map(function($campo) { return ":$campo"; }, $campos);
    
    $sql = "INSERT INTO $tabla (" . implode(', ', $campos) . ") 
            VALUES (" . implode(', ', $placeholders) . ")";
    
    $stmt = $db->prepare($sql);
    
    foreach ($datos as $campo => $valor) {
        $stmt->bindValue(":$campo", $valor);
    }
    
    if ($stmt->execute()) {
        return $db->lastInsertId();
    }
    
    return false;
}

/**
 * Actualizar registro
 */
function actualizarRegistro($db, $tabla, $id, $datos) {
    $sets = array_map(function($campo) { return "$campo = :$campo"; }, array_keys($datos));
    
    $sql = "UPDATE $tabla SET " . implode(', ', $sets) . " WHERE id = :id";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    
    foreach ($datos as $campo => $valor) {
        $stmt->bindValue(":$campo", $valor);
    }
    
    return $stmt->execute();
}

/**
 * Cambiar estado (activar/desactivar)
 */
function toggleEstado() {
    global $db;
    
    try {
        $tabla = sanitize($_POST['tabla'] ?? '');
        $id = intval($_POST['id'] ?? 0);
        $activo = intval($_POST['activo'] ?? 0);
        
        // Validar tabla
        $tablasPermitidas = [
            'estados_equipo', 
            'distritos_fiscales', 
            'sedes', 
            'macro_procesos', 
            'despachos', 
            'usuarios_finales'
        ];
        
        if (!in_array($tabla, $tablasPermitidas)) {
            throw new Exception("Tabla no válida");
        }
        
        $sql = "UPDATE $tabla SET activo = :activo WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':activo', $activo, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            // Registrar auditoría
            $accion = $activo ? 'ACTIVAR' : 'DESACTIVAR';
            logAudit($db, $tabla, $id, $accion, ['activo' => !$activo], ['activo' => $activo]);
            
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Error al cambiar el estado");
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Obtener lista de distritos
 */
function getDistritos() {
    global $db;
    
    $stmt = $db->query("SELECT id, nombre FROM distritos_fiscales WHERE activo = 1 ORDER BY nombre");
    $distritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($distritos);
}

/**
 * Obtener lista de sedes
 */
function getSedes() {
    global $db;
    
    $stmt = $db->query("SELECT id, nombre FROM sedes WHERE activo = 1 ORDER BY nombre");
    $sedes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($sedes);
}

/**
 * Obtener lista de marcas
 */
function getMarcas() {
    global $db;
    
    $stmt = $db->query("SELECT id, nombre FROM marcas WHERE activo = 1 ORDER BY nombre");
    $marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($marcas);
}
