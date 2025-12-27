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
        case 'listar':
            listarAuditoria($db);
            break;
            
        case 'detalle':
            detalleAuditoria($db);
            break;
            
        case 'timeline':
            timelineUsuario($db);
            break;
            
        case 'exportar':
            exportarExcel($db);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function listarAuditoria($db) {
    header('Content-Type: application/json; charset=UTF-8');
    
    $usuario = $_GET['usuario'] ?? null;
    $tabla = $_GET['tabla'] ?? null;
    $accion = $_GET['accion'] ?? null;
    $fechaInicio = $_GET['fecha_inicio'] ?? null;
    $fechaFin = $_GET['fecha_fin'] ?? null;
    $texto = $_GET['texto'] ?? null;
    
    $sql = "SELECT 
                a.id,
                a.tabla,
                a.id_registro,
                a.accion,
                a.datos_anteriores,
                a.datos_nuevos,
                a.fecha_hora,
                a.ip_usuario,
                a.user_agent,
                u.nombre_completo as usuario,
                u.username
            FROM auditoria a
            LEFT JOIN usuarios u ON a.id_usuario = u.id
            WHERE 1=1";
    
    $params = [];
    
    if ($usuario) {
        $sql .= " AND a.id_usuario = :usuario";
        $params[':usuario'] = $usuario;
    }
    
    if ($tabla) {
        $sql .= " AND a.tabla = :tabla";
        $params[':tabla'] = $tabla;
    }
    
    if ($accion) {
        $sql .= " AND a.accion = :accion";
        $params[':accion'] = $accion;
    }
    
    if ($fechaInicio) {
        $sql .= " AND DATE(a.fecha_hora) >= :fecha_inicio";
        $params[':fecha_inicio'] = $fechaInicio;
    }
    
    if ($fechaFin) {
        $sql .= " AND DATE(a.fecha_hora) <= :fecha_fin";
        $params[':fecha_fin'] = $fechaFin;
    }
    
    if ($texto) {
        $sql .= " AND (a.datos_anteriores LIKE :texto OR a.datos_nuevos LIKE :texto OR u.nombre_completo LIKE :texto)";
        $params[':texto'] = "%$texto%";
    }
    
    $sql .= " ORDER BY a.fecha_hora DESC LIMIT 1000";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Procesar datos para convertir IDs a nombres
    foreach ($data as &$registro) {
        if ($registro['datos_anteriores']) {
            $registro['datos_anteriores'] = procesarDatosAuditoria($db, $registro['tabla'], $registro['datos_anteriores']);
        }
        if ($registro['datos_nuevos']) {
            $registro['datos_nuevos'] = procesarDatosAuditoria($db, $registro['tabla'], $registro['datos_nuevos']);
        }
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
}

function detalleAuditoria($db) {
    header('Content-Type: application/json; charset=UTF-8');
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
        return;
    }
    
    $sql = "SELECT 
                a.id,
                a.tabla,
                a.id_registro,
                a.accion,
                a.datos_anteriores,
                a.datos_nuevos,
                a.fecha_hora,
                a.ip_usuario,
                a.user_agent,
                u.nombre_completo as usuario,
                u.username,
                u.email
            FROM auditoria a
            LEFT JOIN usuarios u ON a.id_usuario = u.id
            WHERE a.id = :id";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($data) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
    }
}

function timelineUsuario($db) {
    header('Content-Type: application/json; charset=UTF-8');
    
    $usuario = $_GET['usuario'] ?? null;
    
    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'Usuario no proporcionado']);
        return;
    }
    
    // Obtener información del usuario
    $sqlUsuario = "SELECT nombre_completo, username, email FROM usuarios WHERE id = :id";
    $stmtUsuario = $db->prepare($sqlUsuario);
    $stmtUsuario->bindParam(':id', $usuario);
    $stmtUsuario->execute();
    $infoUsuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
    
    // Obtener timeline de actividad
    $sql = "SELECT 
                a.id,
                a.tabla,
                a.id_registro,
                a.accion,
                a.datos_anteriores,
                a.datos_nuevos,
                a.fecha_hora,
                a.ip_usuario
            FROM auditoria a
            WHERE a.id_usuario = :usuario
            ORDER BY a.fecha_hora DESC
            LIMIT 500";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':usuario', $usuario);
    $stmt->execute();
    
    $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas
    $sqlStats = "SELECT 
                    accion,
                    COUNT(*) as total
                FROM auditoria
                WHERE id_usuario = :usuario
                GROUP BY accion";
    
    $stmtStats = $db->prepare($sqlStats);
    $stmtStats->bindParam(':usuario', $usuario);
    $stmtStats->execute();
    
    $estadisticas = $stmtStats->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'usuario' => $infoUsuario,
        'actividades' => $actividades,
        'estadisticas' => $estadisticas
    ]);
}

function exportarExcel($db) {
    $usuario = $_GET['usuario'] ?? null;
    $tabla = $_GET['tabla'] ?? null;
    $accion = $_GET['accion'] ?? null;
    $fechaInicio = $_GET['fecha_inicio'] ?? null;
    $fechaFin = $_GET['fecha_fin'] ?? null;
    
    $sql = "SELECT 
                a.id,
                a.tabla,
                a.id_registro,
                a.accion,
                a.datos_anteriores,
                a.datos_nuevos,
                a.fecha_hora,
                a.ip_usuario,
                u.nombre_completo as usuario,
                u.username
            FROM auditoria a
            LEFT JOIN usuarios u ON a.id_usuario = u.id
            WHERE 1=1";
    
    $params = [];
    
    if ($usuario) {
        $sql .= " AND a.id_usuario = :usuario";
        $params[':usuario'] = $usuario;
    }
    
    if ($tabla) {
        $sql .= " AND a.tabla = :tabla";
        $params[':tabla'] = $tabla;
    }
    
    if ($accion) {
        $sql .= " AND a.accion = :accion";
        $params[':accion'] = $accion;
    }
    
    if ($fechaInicio) {
        $sql .= " AND DATE(a.fecha_hora) >= :fecha_inicio";
        $params[':fecha_inicio'] = $fechaInicio;
    }
    
    if ($fechaFin) {
        $sql .= " AND DATE(a.fecha_hora) <= :fecha_fin";
        $params[':fecha_fin'] = $fechaFin;
    }
    
    $sql .= " ORDER BY a.fecha_hora DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generar CSV (Excel compatible)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="auditoria_' . date('Y-m-d_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8 en Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezados
    fputcsv($output, [
        'ID',
        'Fecha y Hora',
        'Usuario',
        'Tabla',
        'ID Registro',
        'Acción',
        'Datos Anteriores',
        'Datos Nuevos',
        'IP'
    ], ';');
    
    // Datos
    foreach ($data as $row) {
        fputcsv($output, [
            $row['id'],
            $row['fecha_hora'],
            $row['usuario'] . ' (' . $row['username'] . ')',
            $row['tabla'],
            $row['id_registro'],
            $row['accion'],
            $row['datos_anteriores'],
            $row['datos_nuevos'],
            $row['ip_usuario']
        ], ';');
    }
    
    fclose($output);
    exit;
}

/**
 * Procesar datos de auditoría para convertir IDs a nombres legibles
 */
function procesarDatosAuditoria($db, $tabla, $datosJson) {
    $datos = json_decode($datosJson, true);
    if (!$datos || !is_array($datos)) {
        return $datosJson; // Retornar original si no es JSON válido
    }
    
    $datosLegibles = $datos;
    
    // Mapeo de campos ID a sus tablas correspondientes
    $mapeoIds = [
        'id_estado' => ['tabla' => 'estados_equipo', 'campo_nombre' => 'nombre', 'alias' => 'estado'],
        'id_marca' => ['tabla' => 'marcas', 'campo_nombre' => 'nombre', 'alias' => 'marca'],
        'id_modelo' => ['tabla' => 'modelos', 'campo_nombre' => 'nombre', 'alias' => 'modelo'],
        'id_distrito' => ['tabla' => 'distritos_fiscales', 'campo_nombre' => 'nombre', 'alias' => 'distrito'],
        'id_sede' => ['tabla' => 'sedes', 'campo_nombre' => 'nombre', 'alias' => 'sede'],
        'id_macro_proceso' => ['tabla' => 'macro_procesos', 'campo_nombre' => 'nombre', 'alias' => 'macro_proceso'],
        'id_despacho' => ['tabla' => 'despachos', 'campo_nombre' => 'nombre', 'alias' => 'despacho'],
        'id_usuario_final' => ['tabla' => 'usuarios_finales', 'campo_nombre' => 'nombre_completo', 'alias' => 'usuario_final'],
        'id_tipo_demanda' => ['tabla' => 'tipos_demanda', 'campo_nombre' => 'nombre', 'alias' => 'tipo_demanda'],
        'id_equipo' => ['tabla' => 'equipos', 'campo_nombre' => 'codigo_patrimonial', 'alias' => 'equipo'],
        'id_estado_anterior' => ['tabla' => 'estados_equipo', 'campo_nombre' => 'nombre', 'alias' => 'estado_anterior'],
        'id_estado_nuevo' => ['tabla' => 'estados_equipo', 'campo_nombre' => 'nombre', 'alias' => 'estado_nuevo'],
        'id_usuario_registro' => ['tabla' => 'usuarios', 'campo_nombre' => 'nombre_completo', 'alias' => 'usuario_registro'],
    ];
    
    // Procesar cada campo
    foreach ($datos as $campo => $valor) {
        // Si el campo es un ID y existe en el mapeo
        if (isset($mapeoIds[$campo]) && $valor !== null && $valor !== '') {
            $config = $mapeoIds[$campo];
            
            try {
                $stmt = $db->prepare("SELECT {$config['campo_nombre']} FROM {$config['tabla']} WHERE id = :id");
                $stmt->execute([':id' => $valor]);
                $nombre = $stmt->fetchColumn();
                
                if ($nombre) {
                    // Reemplazar el campo id_* con su alias legible
                    unset($datosLegibles[$campo]);
                    $datosLegibles[$config['alias']] = $nombre;
                } else {
                    // Si no se encuentra el nombre, mostrar "ID: X"
                    unset($datosLegibles[$campo]);
                    $datosLegibles[$config['alias']] = "ID: $valor";
                }
            } catch (Exception $e) {
                // Si hay error, dejar el valor original
                error_log("Error procesando campo $campo: " . $e->getMessage());
            }
        }
    }
    
    return json_encode($datosLegibles, JSON_UNESCAPED_UNICODE);
}
?>
