<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'actualizar':
            actualizarPerfil($db);
            break;
            
        case 'cambiarPassword':
            cambiarPassword($db);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function actualizarPerfil($db) {
    header('Content-Type: application/json; charset=UTF-8');
    
    $idUsuario = $_SESSION['user_id'];
    
    // Obtener datos anteriores
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $idUsuario]);
    $datosAnteriores = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Validar datos
    $nombreCompleto = trim($_POST['nombre_completo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    
    if (empty($nombreCompleto)) {
        echo json_encode(['success' => false, 'message' => 'El nombre completo es obligatorio']);
        return;
    }
    
    // Validar email si se proporciona
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'El email no es válido']);
        return;
    }
    
    try {
        // Actualizar perfil
        $sql = "UPDATE usuarios 
                SET nombre_completo = :nombre_completo,
                    email = :email,
                    telefono = :telefono,
                    fecha_actualizacion = CURRENT_TIMESTAMP
                WHERE id = :id";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            ':nombre_completo' => $nombreCompleto,
            ':email' => $email,
            ':telefono' => $telefono,
            ':id' => $idUsuario
        ]);
        
        if ($result) {
            // Actualizar sesión
            $_SESSION['nombre_completo'] = $nombreCompleto;
            
            // Registrar en auditoría
            $datosNuevos = [
                'nombre_completo' => $nombreCompleto,
                'email' => $email,
                'telefono' => $telefono
            ];
            
            logAudit($db, 'usuarios', $idUsuario, 'UPDATE', 
                [
                    'nombre_completo' => $datosAnteriores['nombre_completo'],
                    'email' => $datosAnteriores['email'],
                    'telefono' => $datosAnteriores['telefono']
                ], 
                $datosNuevos
            );
            
            echo json_encode(['success' => true, 'message' => 'Perfil actualizado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar el perfil']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
}

function cambiarPassword($db) {
    header('Content-Type: application/json; charset=UTF-8');
    
    $idUsuario = $_SESSION['user_id'];
    $passwordActual = $_POST['password_actual'] ?? '';
    $passwordNuevo = $_POST['password_nuevo'] ?? '';
    
    if (empty($passwordActual) || empty($passwordNuevo)) {
        echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
        return;
    }
    
    if (strlen($passwordNuevo) < 6) {
        echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 6 caracteres']);
        return;
    }
    
    try {
        // Verificar contraseña actual
        $stmt = $db->prepare("SELECT password FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $idUsuario]);
        $passwordHash = $stmt->fetchColumn();
        
        if (!password_verify($passwordActual, $passwordHash)) {
            echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta']);
            return;
        }
        
        // Actualizar contraseña
        $nuevoHash = password_hash($passwordNuevo, PASSWORD_DEFAULT);
        
        $sql = "UPDATE usuarios 
                SET password = :password,
                    fecha_actualizacion = CURRENT_TIMESTAMP
                WHERE id = :id";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            ':password' => $nuevoHash,
            ':id' => $idUsuario
        ]);
        
        if ($result) {
            // Registrar en auditoría
            logAudit($db, 'usuarios', $idUsuario, 'UPDATE', 
                ['cambio' => 'Contraseña actualizada'], 
                ['cambio' => 'Contraseña actualizada (hash oculto por seguridad)']
            );
            
            echo json_encode(['success' => true, 'message' => 'Contraseña cambiada correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al cambiar la contraseña']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
}
?>
