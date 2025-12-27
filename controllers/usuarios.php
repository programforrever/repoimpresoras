<?php
/**
 * Controlador de Usuarios
 * RF-02: Gestión de usuarios del sistema
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../includes/functions.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación y permisos
if (!isLoggedIn() || !hasRole(ROL_ADMIN)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$usuarioModel = new Usuario($db);

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'create':
        crearUsuario();
        break;
    
    case 'update':
        actualizarUsuario();
        break;
    
    case 'get':
        obtenerUsuario();
        break;
    
    case 'getPerfil':
        obtenerPerfilUsuario();
        break;
    
    case 'toggle_estado':
        toggleEstado();
        break;
    
    case 'delete':
        eliminarUsuario();
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Acción no válida']);
}

/**
 * Crear nuevo usuario
 */
function crearUsuario() {
    global $usuarioModel, $db;
    
    try {
        // Validar datos requeridos
        $required = ['username', 'nombre_completo', 'id_rol', 'password'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("El campo $field es requerido");
            }
        }
        
        // Validar contraseña
        if (strlen($_POST['password']) < 6) {
            throw new Exception("La contraseña debe tener al menos 6 caracteres");
        }
        
        if ($_POST['password'] !== $_POST['password_confirm']) {
            throw new Exception("Las contraseñas no coinciden");
        }
        
        // Preparar datos
        $data = [
            'username' => sanitize($_POST['username']),
            'password' => $_POST['password'],
            'nombre_completo' => sanitize($_POST['nombre_completo']),
            'email' => sanitize($_POST['email'] ?? ''),
            'telefono' => sanitize($_POST['telefono'] ?? ''),
            'id_rol' => intval($_POST['id_rol']),
            'activo' => intval($_POST['activo'] ?? 1)
        ];
        
        // Crear usuario
        $result = $usuarioModel->create($data);
        
        if ($result) {
            // Registrar auditoría
            logAudit($db, 'usuarios', $result, 'INSERT', null, $data);
            
            setFlashMessage('success', 'Usuario creado exitosamente');
            redirect('views/usuarios/index.php');
        } else {
            throw new Exception("Error al crear el usuario");
        }
        
    } catch (Exception $e) {
        setFlashMessage('danger', $e->getMessage());
        redirect('views/usuarios/index.php');
    }
}

/**
 * Actualizar usuario existente
 */
function actualizarUsuario() {
    global $usuarioModel, $db;
    
    try {
        $id = intval($_POST['id']);
        
        if (empty($id)) {
            throw new Exception("ID de usuario no válido");
        }
        
        // Obtener datos anteriores para auditoría
        $datosAnteriores = $usuarioModel->getById($id);
        
        // Preparar datos
        $data = [
            'id' => $id,
            'username' => sanitize($_POST['username']),
            'nombre_completo' => sanitize($_POST['nombre_completo']),
            'email' => sanitize($_POST['email'] ?? ''),
            'telefono' => sanitize($_POST['telefono'] ?? ''),
            'id_rol' => intval($_POST['id_rol']),
            'activo' => intval($_POST['activo'] ?? 1)
        ];
        
        // Si se proporciona nueva contraseña
        if (!empty($_POST['password'])) {
            if (strlen($_POST['password']) < 6) {
                throw new Exception("La contraseña debe tener al menos 6 caracteres");
            }
            
            if ($_POST['password'] !== $_POST['password_confirm']) {
                throw new Exception("Las contraseñas no coinciden");
            }
            
            $data['password'] = $_POST['password'];
        }
        
        // Actualizar usuario
        $result = $usuarioModel->update($id, $data);
        
        if ($result) {
            // Registrar auditoría
            logAudit($db, 'usuarios', $id, 'UPDATE', $datosAnteriores, $data);
            
            setFlashMessage('success', 'Usuario actualizado exitosamente');
            redirect('views/usuarios/index.php');
        } else {
            throw new Exception("Error al actualizar el usuario");
        }
        
    } catch (Exception $e) {
        setFlashMessage('danger', $e->getMessage());
        redirect('views/usuarios/index.php');
    }
}

/**
 * Obtener datos de un usuario
 */
function obtenerUsuario() {
    global $usuarioModel;
    
    $id = intval($_GET['id'] ?? 0);
    
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID no válido']);
        return;
    }
    
    $usuario = $usuarioModel->getById($id);
    
    if ($usuario) {
        // Remover la contraseña del resultado
        unset($usuario['password']);
        echo json_encode($usuario);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Usuario no encontrado']);
    }
}

/**
 * Activar/Desactivar usuario
 */
function toggleEstado() {
    global $usuarioModel, $db;
    
    try {
        $id = intval($_POST['id'] ?? 0);
        $activo = intval($_POST['activo'] ?? 0);
        
        if (empty($id)) {
            throw new Exception("ID no válido");
        }
        
        // No permitir desactivar el propio usuario
        if ($id == $_SESSION['user_id']) {
            throw new Exception("No puede desactivar su propio usuario");
        }
        
        // Obtener datos anteriores
        $datosAnteriores = $usuarioModel->getById($id);
        
        $result = $usuarioModel->toggleEstado($id, $activo);
        
        if ($result) {
            // Registrar auditoría
            $accion = $activo ? 'ACTIVAR' : 'DESACTIVAR';
            logAudit($db, 'usuarios', $id, $accion, 
                     ['activo' => $datosAnteriores['activo']], 
                     ['activo' => $activo]);
            
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
 * Eliminar usuario
 */
function eliminarUsuario() {
    global $usuarioModel, $db;
    
    try {
        $id = intval($_POST['id'] ?? 0);
        
        if (empty($id)) {
            throw new Exception("ID no válido");
        }
        
        // No permitir eliminar el propio usuario
        if ($id == $_SESSION['user_id']) {
            throw new Exception("No puede eliminar su propio usuario");
        }
        
        // Obtener datos anteriores para auditoría
        $datosAnteriores = $usuarioModel->getById($id);
        
        if (!$datosAnteriores) {
            throw new Exception("Usuario no encontrado");
        }
        
        $result = $usuarioModel->delete($id);
        
        if ($result) {
            // Registrar auditoría
            logAudit($db, 'usuarios', $id, 'DELETE', $datosAnteriores, null);
            
            echo json_encode(['success' => true, 'message' => 'Usuario eliminado exitosamente']);
        } else {
            throw new Exception("Error al eliminar el usuario");
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Obtener perfil completo del usuario con estadísticas
 */
function obtenerPerfilUsuario() {
    global $usuarioModel, $db;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $id = intval($_GET['id']);
        
        if (!$id) {
            throw new Exception("ID de usuario no válido");
        }
        
        // Obtener datos del usuario
        $usuario = $usuarioModel->getById($id);
        
        if (!$usuario) {
            throw new Exception("Usuario no encontrado");
        }
        
        // Obtener estadísticas de auditoría
        $sqlStats = "SELECT 
                        COUNT(*) as total_acciones,
                        SUM(CASE WHEN accion = 'INSERT' THEN 1 ELSE 0 END) as creaciones,
                        SUM(CASE WHEN accion = 'UPDATE' THEN 1 ELSE 0 END) as modificaciones,
                        SUM(CASE WHEN accion = 'DELETE' THEN 1 ELSE 0 END) as eliminaciones
                     FROM auditoria 
                     WHERE id_usuario = :id_usuario";
        
        $stmtStats = $db->prepare($sqlStats);
        $stmtStats->execute([':id_usuario' => $id]);
        $estadisticas = $stmtStats->fetch(PDO::FETCH_ASSOC);
        
        // Obtener actividad reciente (últimas 10 acciones)
        $sqlActividad = "SELECT 
                            tabla,
                            accion,
                            fecha_hora,
                            id_registro
                         FROM auditoria 
                         WHERE id_usuario = :id_usuario
                         ORDER BY fecha_hora DESC
                         LIMIT 10";
        
        $stmtActividad = $db->prepare($sqlActividad);
        $stmtActividad->execute([':id_usuario' => $id]);
        $actividad = $stmtActividad->fetchAll(PDO::FETCH_ASSOC);
        
        // Preparar respuesta
        $response = [
            'usuario' => $usuario,
            'estadisticas' => $estadisticas,
            'actividad' => $actividad
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
