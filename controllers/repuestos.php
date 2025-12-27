<?php
/**
 * Controlador de Repuestos
 */

// Configurar codificación UTF-8
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Repuesto.php';
require_once __DIR__ . '/../models/MantenimientoRepuesto.php';
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
$repuestoModel = new Repuesto($db);
$mantRepuestoModel = new MantenimientoRepuesto($db);

// Obtener acción
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Routing
switch ($action) {
    case 'create':
        crearRepuesto();
        break;
    case 'update':
        actualizarRepuesto();
        break;
    case 'delete':
        eliminarRepuesto();
        break;
    case 'get':
        obtenerRepuesto();
        break;
    case 'getAll':
        obtenerTodosRepuestos();
        break;
    case 'buscar':
        buscarRepuestos();
        break;
    case 'stockBajo':
        obtenerStockBajo();
        break;
    case 'actualizarStock':
        actualizarStock();
        break;
    case 'agregarAMantenimiento':
        agregarRepuestoAMantenimiento();
        break;
    case 'quitarDeMantenimiento':
        quitarRepuestoDeMantenimiento();
        break;
    case 'getByMantenimiento':
        obtenerRepuestosPorMantenimiento();
        break;
    case 'getHistorialRepuesto':
        obtenerHistorialRepuesto();
        break;
    case 'getMarcas':
        obtenerMarcas();
        break;
    case 'getModelos':
        obtenerModelos();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}

/**
 * Crear repuesto
 */
function crearRepuesto() {
    global $repuestoModel, $db;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        // Validar que el código no exista
        $existe = $repuestoModel->getByCodigo($_POST['codigo']);
        if ($existe) {
            throw new Exception('Ya existe un repuesto con ese código');
        }
        
        $data = [
            'codigo' => $_POST['codigo'],
            'nombre' => $_POST['nombre'],
            'descripcion' => $_POST['descripcion'] ?? '',
            'marca' => $_POST['marca'] ?? '',
            'modelo_compatible' => $_POST['modelo_compatible'] ?? '',
            'stock_minimo' => intval($_POST['stock_minimo'] ?? 0),
            'stock_actual' => intval($_POST['stock_actual'] ?? 0),
            'precio_unitario' => floatval($_POST['precio_unitario'] ?? 0),
            'unidad_medida' => $_POST['unidad_medida'] ?? 'Unidad',
            'id_usuario_registro' => $_SESSION['user_id']
        ];
        
        $id = $repuestoModel->create($data);
        
        if ($id) {
            // Registrar en auditoría
            registrarAuditoria(
                $db,
                'repuestos',
                $id,
                'create',
                null,
                json_encode($data)
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Repuesto registrado exitosamente',
                'id' => $id
            ]);
        } else {
            throw new Exception("Error al registrar el repuesto");
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Actualizar repuesto
 */
function actualizarRepuesto() {
    global $repuestoModel, $db;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $id = intval($_POST['id']);
        
        // Obtener datos anteriores para auditoría
        $datosAnteriores = $repuestoModel->getById($id);
        
        // Validar código único (excepto el mismo registro)
        $existe = $repuestoModel->getByCodigo($_POST['codigo']);
        if ($existe && $existe['id'] != $id) {
            throw new Exception('Ya existe otro repuesto con ese código');
        }
        
        $data = [
            'codigo' => $_POST['codigo'],
            'nombre' => $_POST['nombre'],
            'descripcion' => $_POST['descripcion'] ?? '',
            'marca' => $_POST['marca'] ?? '',
            'modelo_compatible' => $_POST['modelo_compatible'] ?? '',
            'stock_minimo' => intval($_POST['stock_minimo'] ?? 0),
            'precio_unitario' => floatval($_POST['precio_unitario'] ?? 0),
            'unidad_medida' => $_POST['unidad_medida'] ?? 'Unidad'
        ];
        
        $result = $repuestoModel->update($id, $data);
        
        if ($result) {
            // Registrar auditoría
            registrarAuditoria(
                $db,
                'repuestos',
                $id,
                'update',
                json_encode($datosAnteriores),
                json_encode($data)
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Repuesto actualizado exitosamente'
            ]);
        } else {
            throw new Exception("Error al actualizar el repuesto");
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Eliminar repuesto (soft delete)
 */
function eliminarRepuesto() {
    global $repuestoModel, $db;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $id = $_POST['id'] ?? null;
        
        if (!$id) {
            throw new Exception("ID no proporcionado");
        }
        
        // Obtener datos anteriores
        $datosAnteriores = $repuestoModel->getById($id);
        
        $result = $repuestoModel->delete($id);
        
        if ($result) {
            // Registrar auditoría
            registrarAuditoria(
                $db,
                'repuestos',
                $id,
                'delete',
                json_encode($datosAnteriores),
                null
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Repuesto eliminado exitosamente'
            ]);
        } else {
            throw new Exception("Error al eliminar el repuesto");
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Obtener repuesto
 */
function obtenerRepuesto() {
    global $repuestoModel;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            throw new Exception('ID no proporcionado');
        }
        
        $repuesto = $repuestoModel->getById($id);
        
        if ($repuesto) {
            echo json_encode(['success' => true, 'data' => $repuesto]);
        } else {
            throw new Exception('Repuesto no encontrado');
        }
    } catch (Exception $e) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Obtener todos los repuestos
 */
function obtenerTodosRepuestos() {
    global $repuestoModel;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $incluir_inactivos = isset($_GET['incluir_inactivos']) && $_GET['incluir_inactivos'] == '1';
        $repuestos = $repuestoModel->getAll($incluir_inactivos);
        echo json_encode(['success' => true, 'data' => $repuestos]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Buscar repuestos
 */
function buscarRepuestos() {
    global $repuestoModel;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $termino = $_GET['q'] ?? '';
        
        if (empty($termino)) {
            $repuestos = $repuestoModel->getAll();
        } else {
            $repuestos = $repuestoModel->buscar($termino);
        }
        
        echo json_encode(['success' => true, 'data' => $repuestos]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Obtener repuestos con stock bajo
 */
function obtenerStockBajo() {
    global $repuestoModel;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $repuestos = $repuestoModel->getStockBajo();
        echo json_encode(['success' => true, 'data' => $repuestos]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Actualizar stock
 */
function actualizarStock() {
    global $repuestoModel, $db;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $id = intval($_POST['id']);
        $cantidad = intval($_POST['cantidad']);
        $tipo = $_POST['tipo'] ?? 'entrada';
        $motivo = $_POST['motivo'] ?? '';
        
        $resultado = $repuestoModel->actualizarStock($id, $cantidad, $tipo);
        
        if ($resultado) {
            // Registrar movimiento
            $query = "INSERT INTO repuestos_movimientos 
                     (id_repuesto, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, motivo, id_usuario_registro)
                     VALUES (:id_repuesto, :tipo, :cantidad, :stock_anterior, :stock_nuevo, :motivo, :id_usuario)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id_repuesto', $id);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':cantidad', $cantidad);
            $stmt->bindParam(':stock_anterior', $resultado['stock_anterior']);
            $stmt->bindParam(':stock_nuevo', $resultado['stock_nuevo']);
            $stmt->bindParam(':motivo', $motivo);
            $stmt->bindParam(':id_usuario', $_SESSION['user_id']);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Stock actualizado exitosamente',
                'data' => $resultado
            ]);
        } else {
            throw new Exception("Error al actualizar el stock");
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Agregar repuesto a un mantenimiento
 */
function agregarRepuestoAMantenimiento() {
    global $mantRepuestoModel, $repuestoModel, $db;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $data = [
            'id_mantenimiento' => intval($_POST['id_mantenimiento']),
            'id_repuesto' => intval($_POST['id_repuesto']),
            'cantidad' => intval($_POST['cantidad']),
            'fecha_cambio' => $_POST['fecha_cambio'],
            'parte_requerida' => $_POST['parte_requerida'],
            'observaciones' => $_POST['observaciones'] ?? '',
            'costo_total' => floatval($_POST['costo_total'] ?? 0),
            'id_usuario_registro' => $_SESSION['user_id']
        ];
        
        // Iniciar transacción
        $db->beginTransaction();
        
        // Agregar el registro
        $id = $mantRepuestoModel->create($data);
        
        if (!$id) {
            throw new Exception("Error al agregar el repuesto al mantenimiento");
        }
        
        // Descontar del stock
        $resultado = $repuestoModel->actualizarStock($data['id_repuesto'], $data['cantidad'], 'salida');
        
        if (!$resultado) {
            throw new Exception("Error al actualizar el stock del repuesto");
        }
        
        // Registrar movimiento de inventario
        $query = "INSERT INTO repuestos_movimientos 
                 (id_repuesto, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, 
                  motivo, referencia, id_usuario_registro)
                 VALUES (:id_repuesto, 'salida', :cantidad, :stock_anterior, :stock_nuevo, 
                        :motivo, :referencia, :id_usuario)";
        
        $stmt = $db->prepare($query);
        $motivo = "Utilizado en mantenimiento";
        $referencia = "MANT-" . $data['id_mantenimiento'];
        
        $stmt->bindParam(':id_repuesto', $data['id_repuesto']);
        $stmt->bindParam(':cantidad', $data['cantidad']);
        $stmt->bindParam(':stock_anterior', $resultado['stock_anterior']);
        $stmt->bindParam(':stock_nuevo', $resultado['stock_nuevo']);
        $stmt->bindParam(':motivo', $motivo);
        $stmt->bindParam(':referencia', $referencia);
        $stmt->bindParam(':id_usuario', $_SESSION['user_id']);
        $stmt->execute();
        
        // Commit
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Repuesto agregado al mantenimiento exitosamente',
            'id' => $id
        ]);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Quitar repuesto de un mantenimiento
 */
function quitarRepuestoDeMantenimiento() {
    global $mantRepuestoModel, $repuestoModel, $db;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $id = intval($_POST['id']);
        
        // Obtener datos del registro
        $registro = $mantRepuestoModel->getById($id);
        
        if (!$registro) {
            throw new Exception("Registro no encontrado");
        }
        
        // Iniciar transacción
        $db->beginTransaction();
        
        // Devolver al stock
        $resultado = $repuestoModel->actualizarStock(
            $registro['id_repuesto'], 
            $registro['cantidad'], 
            'entrada'
        );
        
        if (!$resultado) {
            throw new Exception("Error al devolver el repuesto al stock");
        }
        
        // Eliminar el registro
        $result = $mantRepuestoModel->delete($id);
        
        if (!$result) {
            throw new Exception("Error al quitar el repuesto del mantenimiento");
        }
        
        // Registrar movimiento de inventario
        $query = "INSERT INTO repuestos_movimientos 
                 (id_repuesto, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, 
                  motivo, referencia, id_usuario_registro)
                 VALUES (:id_repuesto, 'entrada', :cantidad, :stock_anterior, :stock_nuevo, 
                        :motivo, :referencia, :id_usuario)";
        
        $stmt = $db->prepare($query);
        $motivo = "Devolución de mantenimiento";
        $referencia = "MANT-" . $registro['id_mantenimiento'];
        
        $stmt->bindParam(':id_repuesto', $registro['id_repuesto']);
        $stmt->bindParam(':cantidad', $registro['cantidad']);
        $stmt->bindParam(':stock_anterior', $resultado['stock_anterior']);
        $stmt->bindParam(':stock_nuevo', $resultado['stock_nuevo']);
        $stmt->bindParam(':motivo', $motivo);
        $stmt->bindParam(':referencia', $referencia);
        $stmt->bindParam(':id_usuario', $_SESSION['user_id']);
        $stmt->execute();
        
        // Commit
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Repuesto removido del mantenimiento exitosamente'
        ]);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Obtener repuestos de un mantenimiento
 */
function obtenerRepuestosPorMantenimiento() {
    global $mantRepuestoModel;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $id_mantenimiento = $_GET['id_mantenimiento'] ?? null;
        
        if (!$id_mantenimiento) {
            throw new Exception('ID de mantenimiento no proporcionado');
        }
        
        $repuestos = $mantRepuestoModel->getByMantenimiento($id_mantenimiento);
        
        echo json_encode(['success' => true, 'data' => $repuestos]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Obtener historial de uso de un repuesto
 */
function obtenerHistorialRepuesto() {
    global $mantRepuestoModel;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $id_repuesto = $_GET['id_repuesto'] ?? null;
        $limite = isset($_GET['limite']) ? intval($_GET['limite']) : null;
        
        if (!$id_repuesto) {
            throw new Exception('ID de repuesto no proporcionado');
        }
        
        $historial = $mantRepuestoModel->getByRepuesto($id_repuesto, $limite);
        
        echo json_encode(['success' => true, 'data' => $historial]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Obtener todas las marcas activas
 */
function obtenerMarcas() {
    global $db;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $query = "SELECT id, nombre FROM marcas WHERE activo = 1 ORDER BY nombre ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $marcas]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Obtener modelos por marca
 */
function obtenerModelos() {
    global $db;
    
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        $id_marca = $_GET['id_marca'] ?? null;
        
        if ($id_marca) {
            $query = "SELECT id, nombre FROM modelos WHERE id_marca = :id_marca AND activo = 1 ORDER BY nombre ASC";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id_marca', $id_marca);
        } else {
            $query = "SELECT id, nombre, id_marca FROM modelos WHERE activo = 1 ORDER BY nombre ASC";
            $stmt = $db->prepare($query);
        }
        
        $stmt->execute();
        $modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $modelos]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
