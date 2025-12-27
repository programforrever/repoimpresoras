<?php
/**
 * Vista para editar mantenimiento con repuestos
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verificar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/views/login.php');
    exit;
}

// Verificar ID
if (!isset($_GET['id'])) {
    $_SESSION['mensaje'] = 'ID de mantenimiento no proporcionado';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Equipo.php';
require_once __DIR__ . '/../../models/Mantenimiento.php';
require_once __DIR__ . '/../../models/Repuesto.php';
require_once __DIR__ . '/../../models/MantenimientoRepuesto.php';

$database = new Database();
$db = $database->getConnection();
$equipoModel = new Equipo($db);
$mantenimientoModel = new Mantenimiento($db);
$repuestoModel = new Repuesto($db);
$mantRepuestoModel = new MantenimientoRepuesto($db);

$id = intval($_GET['id']);
$mantenimiento = $mantenimientoModel->getById($id);

if (!$mantenimiento) {
    $_SESSION['mensaje'] = 'Mantenimiento no encontrado';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: index.php');
    exit;
}

// Obtener datos relacionados
$equipos = $equipoModel->getAll();
$repuestos = $repuestoModel->getAll();
$repuestos_asociados = $mantRepuestoModel->getByMantenimiento($id);

// Obtener tipos de demanda y estados
$query_tipos = "SELECT * FROM tipos_demanda WHERE activo = 1 ORDER BY nombre";
$stmt_tipos = $db->prepare($query_tipos);
$stmt_tipos->execute();
$tipos_demanda = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);

$query_estados = "SELECT * FROM estados_equipo WHERE activo = 1 ORDER BY nombre";
$stmt_estados = $db->prepare($query_estados);
$stmt_estados->execute();
$estados = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Editar Mantenimiento - " . APP_NAME;
$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // Actualizar el mantenimiento
        $data_mant = [
            'id_equipo' => intval($_POST['id_equipo']),
            'id_tipo_demanda' => intval($_POST['id_tipo_demanda']),
            'fecha_mantenimiento' => $_POST['fecha_mantenimiento'],
            'descripcion' => $_POST['descripcion'] ?? '',
            'tecnico_responsable' => $_POST['tecnico_responsable'] ?? '',
            'observaciones' => $_POST['observaciones'] ?? '',
            'id_estado_anterior' => !empty($_POST['id_estado_anterior']) ? intval($_POST['id_estado_anterior']) : null,
            'id_estado_nuevo' => !empty($_POST['id_estado_nuevo']) ? intval($_POST['id_estado_nuevo']) : null
        ];
        
        $result = $mantenimientoModel->update($id, $data_mant);
        
        if (!$result) {
            throw new Exception("Error al actualizar el mantenimiento");
        }
        
        // Actualizar estado del equipo si cambió
        if (!empty($data_mant['id_estado_nuevo'])) {
            $equipoModel->cambiarEstado($data_mant['id_equipo'], $data_mant['id_estado_nuevo']);
        }
        
        // Procesar repuestos eliminados
        if (!empty($_POST['repuestos_eliminar'])) {
            foreach ($_POST['repuestos_eliminar'] as $id_rep_eliminar) {
                // Obtener datos antes de eliminar
                $rep_datos = $mantRepuestoModel->getById($id_rep_eliminar);
                
                if ($rep_datos) {
                    // Devolver al stock
                    $resultado = $repuestoModel->actualizarStock(
                        $rep_datos['id_repuesto'], 
                        $rep_datos['cantidad'], 
                        'entrada'
                    );
                    
                    // Registrar movimiento
                    $query_mov = "INSERT INTO repuestos_movimientos 
                                 (id_repuesto, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, 
                                  motivo, referencia, id_usuario_registro)
                                 VALUES (:id_repuesto, 'entrada', :cantidad, :stock_anterior, :stock_nuevo, 
                                        'Devolución por edición de mantenimiento', :referencia, :id_usuario)";
                    
                    $stmt_mov = $db->prepare($query_mov);
                    $referencia = "MANT-" . $id;
                    $stmt_mov->bindParam(':id_repuesto', $rep_datos['id_repuesto']);
                    $stmt_mov->bindParam(':cantidad', $rep_datos['cantidad']);
                    $stmt_mov->bindParam(':stock_anterior', $resultado['stock_anterior']);
                    $stmt_mov->bindParam(':stock_nuevo', $resultado['stock_nuevo']);
                    $stmt_mov->bindParam(':referencia', $referencia);
                    $stmt_mov->bindParam(':id_usuario', $_SESSION['user_id']);
                    $stmt_mov->execute();
                    
                    // Eliminar el registro
                    $mantRepuestoModel->delete($id_rep_eliminar);
                }
            }
        }
        
        // Procesar repuestos nuevos
        if (!empty($_POST['repuestos_nuevos'])) {
            foreach ($_POST['repuestos_nuevos'] as $index => $id_repuesto) {
                if (empty($id_repuesto)) continue;
                
                $cantidad = intval($_POST['cantidades_nuevas'][$index] ?? 1);
                $parte_requerida = $_POST['partes_requeridas_nuevas'][$index] ?? '';
                $fecha_cambio = $_POST['fechas_cambio_nuevas'][$index] ?? $_POST['fecha_mantenimiento'];
                $observacion_rep = $_POST['observaciones_repuestos_nuevas'][$index] ?? '';
                
                // Obtener precio del repuesto
                $repuesto = $repuestoModel->getById($id_repuesto);
                $costo_total = $cantidad * ($repuesto['precio_unitario'] ?? 0);
                
                $data_rep = [
                    'id_mantenimiento' => $id,
                    'id_repuesto' => $id_repuesto,
                    'cantidad' => $cantidad,
                    'fecha_cambio' => $fecha_cambio,
                    'parte_requerida' => $parte_requerida,
                    'observaciones' => $observacion_rep,
                    'costo_total' => $costo_total,
                    'id_usuario_registro' => $_SESSION['user_id']
                ];
                
                $id_mant_rep = $mantRepuestoModel->create($data_rep);
                
                if ($id_mant_rep) {
                    // Descontar del stock
                    $resultado = $repuestoModel->actualizarStock($id_repuesto, $cantidad, 'salida');
                    
                    // Registrar movimiento
                    $query_mov = "INSERT INTO repuestos_movimientos 
                                 (id_repuesto, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, 
                                  motivo, referencia, id_usuario_registro)
                                 VALUES (:id_repuesto, 'salida', :cantidad, :stock_anterior, :stock_nuevo, 
                                        'Utilizado en mantenimiento', :referencia, :id_usuario)";
                    
                    $stmt_mov = $db->prepare($query_mov);
                    $referencia = "MANT-" . $id;
                    $stmt_mov->bindParam(':id_repuesto', $id_repuesto);
                    $stmt_mov->bindParam(':cantidad', $cantidad);
                    $stmt_mov->bindParam(':stock_anterior', $resultado['stock_anterior']);
                    $stmt_mov->bindParam(':stock_nuevo', $resultado['stock_nuevo']);
                    $stmt_mov->bindParam(':referencia', $referencia);
                    $stmt_mov->bindParam(':id_usuario', $_SESSION['user_id']);
                    $stmt_mov->execute();
                }
            }
        }
        
        // Registrar auditoría
        registrarAuditoria(
            $db,
            'mantenimientos',
            $id,
            'update',
            json_encode($mantenimiento),
            json_encode($data_mant)
        );
        
        $db->commit();
        
        $_SESSION['mensaje'] = 'Mantenimiento actualizado exitosamente';
        $_SESSION['tipo_mensaje'] = 'success';
        header('Location: index.php');
        exit;
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'danger';
        
        // Recargar datos
        $mantenimiento = $mantenimientoModel->getById($id);
        $repuestos_asociados = $mantRepuestoModel->getByMantenimiento($id);
    }
}
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $_SESSION['theme'] ?? 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <!-- Estilos personalizados -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    
    <style>
        [data-theme="dark"] {
            background-color: #1a1d20;
            color: #e9ecef;
        }
        
        [data-theme="dark"] .card {
            background-color: #2b3035;
            color: #e9ecef;
        }
        
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background-color: #363a40;
            color: #e9ecef;
            border-color: #495057;
        }
        
        [data-theme="dark"] .list-group-item {
            background-color: #2b3035;
            border-color: #495057;
            color: #e9ecef;
        }
        
        .required::after {
            content: " *";
            color: red;
        }
        
        .repuesto-item {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #0d6efd;
        }
        
        .repuesto-existente {
            border-left-color: #198754;
        }
        
        [data-theme="dark"] .repuesto-item {
            background-color: #363a40;
        }
        
        .info-equipo {
            background-color: #e7f1ff;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #0d6efd;
        }
        
        [data-theme="dark"] .info-equipo {
            background-color: #1e3a5f;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/views/dashboard.php">Inicio</a></li>
                <li class="breadcrumb-item"><a href="index.php">Mantenimientos</a></li>
                <li class="breadcrumb-item active">Editar Mantenimiento</li>
            </ol>
        </nav>
        
        <!-- Título -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h2><i class="fas fa-edit"></i> Editar Mantenimiento</h2>
                <p class="text-muted mb-0">ID: <strong><?php echo $id; ?></strong></p>
            </div>
            <div class="col-md-6 text-end">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>
        
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Formulario -->
        <form method="POST" action="" id="formMantenimiento" accept-charset="UTF-8">
            <div class="row">
                <!-- Columna izquierda -->
                <div class="col-lg-8">
                    <!-- Datos del Mantenimiento -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-file-alt"></i> Datos del Mantenimiento</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Equipo -->
                                <div class="col-md-12 mb-3">
                                    <label for="id_equipo" class="form-label required">Equipo</label>
                                    <select class="form-select" id="id_equipo" name="id_equipo" required>
                                        <option value="">Seleccione un equipo...</option>
                                        <?php foreach ($equipos as $equipo): ?>
                                        <option value="<?php echo $equipo['id']; ?>" 
                                                <?php echo ($mantenimiento['id_equipo'] == $equipo['id']) ? 'selected' : ''; ?>
                                                data-marca="<?php echo htmlspecialchars($equipo['marca']); ?>"
                                                data-modelo="<?php echo htmlspecialchars($equipo['modelo']); ?>"
                                                data-serie="<?php echo htmlspecialchars($equipo['serie']); ?>"
                                                data-estado="<?php echo $equipo['id_estado']; ?>">
                                            <?php echo htmlspecialchars($equipo['nombre']); ?> - 
                                            <?php echo htmlspecialchars($equipo['marca']); ?> 
                                            <?php echo htmlspecialchars($equipo['modelo']); ?> 
                                            (<?php echo htmlspecialchars($equipo['serie']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <!-- Info del equipo seleccionado -->
                                    <div id="infoEquipo" class="info-equipo mt-3">
                                        <h6><i class="fas fa-info-circle"></i> Información del Equipo</h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <small class="text-muted">Marca:</small><br>
                                                <strong id="equipo_marca"><?php echo htmlspecialchars($mantenimiento['equipo_marca'] ?? '-'); ?></strong>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted">Modelo:</small><br>
                                                <strong id="equipo_modelo"><?php echo htmlspecialchars($mantenimiento['equipo_modelo'] ?? '-'); ?></strong>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted">Serie:</small><br>
                                                <strong id="equipo_serie"><?php echo htmlspecialchars($mantenimiento['equipo_serie'] ?? '-'); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Tipo de Demanda -->
                                <div class="col-md-6 mb-3">
                                    <label for="id_tipo_demanda" class="form-label required">Tipo de Demanda</label>
                                    <select class="form-select" id="id_tipo_demanda" name="id_tipo_demanda" required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($tipos_demanda as $tipo): ?>
                                        <option value="<?php echo $tipo['id']; ?>" <?php echo ($mantenimiento['id_tipo_demanda'] == $tipo['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tipo['nombre']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Fecha -->
                                <div class="col-md-6 mb-3">
                                    <label for="fecha_mantenimiento" class="form-label required">Fecha de Mantenimiento</label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="fecha_mantenimiento" 
                                           name="fecha_mantenimiento" 
                                           value="<?php echo $mantenimiento['fecha_mantenimiento']; ?>"
                                           required>
                                </div>
                                
                                <!-- Técnico -->
                                <div class="col-md-12 mb-3">
                                    <label for="tecnico_responsable" class="form-label">Técnico Responsable</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="tecnico_responsable" 
                                           name="tecnico_responsable"
                                           value="<?php echo htmlspecialchars($mantenimiento['tecnico_responsable'] ?? ''); ?>"
                                           placeholder="Nombre del técnico">
                                </div>
                                
                                <!-- Descripción -->
                                <div class="col-md-12 mb-3">
                                    <label for="descripcion" class="form-label required">Descripción</label>
                                    <textarea class="form-control" 
                                              id="descripcion" 
                                              name="descripcion" 
                                              rows="4"
                                              required><?php echo htmlspecialchars($mantenimiento['descripcion']); ?></textarea>
                                </div>
                                
                                <!-- Observaciones -->
                                <div class="col-md-12 mb-3">
                                    <label for="observaciones" class="form-label">Observaciones</label>
                                    <textarea class="form-control" 
                                              id="observaciones" 
                                              name="observaciones" 
                                              rows="3"><?php echo htmlspecialchars($mantenimiento['observaciones'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Repuestos Utilizados -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-boxes"></i> Repuestos Utilizados</h5>
                            <button type="button" class="btn btn-sm btn-primary" id="btnAgregarRepuesto">
                                <i class="fas fa-plus"></i> Agregar Repuesto
                            </button>
                        </div>
                        <div class="card-body">
                            <!-- Repuestos existentes -->
                            <?php if (count($repuestos_asociados) > 0): ?>
                            <h6 class="text-muted mb-3">Repuestos Actuales:</h6>
                            <div id="repuestosExistentes">
                                <?php foreach ($repuestos_asociados as $rep_asoc): ?>
                                <div class="repuesto-item repuesto-existente">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <strong><?php echo htmlspecialchars($rep_asoc['codigo']); ?></strong> - 
                                            <?php echo htmlspecialchars($rep_asoc['nombre_repuesto']); ?>
                                        </div>
                                        <div class="col-md-2">
                                            <small class="text-muted">Cantidad:</small><br>
                                            <strong><?php echo $rep_asoc['cantidad']; ?></strong> <?php echo htmlspecialchars($rep_asoc['unidad_medida']); ?>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Parte:</small><br>
                                            <?php echo htmlspecialchars($rep_asoc['parte_requerida']); ?>
                                        </div>
                                        <div class="col-md-2">
                                            <small class="text-muted">Costo:</small><br>
                                            S/ <?php echo number_format($rep_asoc['costo_total'], 2); ?>
                                        </div>
                                        <div class="col-md-1 text-end">
                                            <button type="button" 
                                                    class="btn btn-danger btn-sm btn-eliminar-existente" 
                                                    data-id="<?php echo $rep_asoc['id']; ?>"
                                                    title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <hr>
                            <?php endif; ?>
                            
                            <!-- Nuevos repuestos -->
                            <h6 class="text-muted mb-3">Agregar Nuevos Repuestos:</h6>
                            <div id="contenedorRepuestosNuevos">
                                <p class="text-muted text-center py-3">
                                    <i class="fas fa-info-circle"></i> 
                                    Haga clic en "Agregar Repuesto" para añadir nuevos repuestos.
                                </p>
                            </div>
                            
                            <!-- Campo oculto para repuestos a eliminar -->
                            <input type="hidden" id="repuestosEliminar" name="repuestos_eliminar[]" value="">
                        </div>
                    </div>
                </div>
                
                <!-- Columna derecha -->
                <div class="col-lg-4">
                    <!-- Cambio de Estado -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-sync-alt"></i> Cambio de Estado</h5>
                        </div>
                        <div class="card-body">
                            <!-- Estado Anterior -->
                            <div class="mb-3">
                                <label for="id_estado_anterior" class="form-label">Estado Anterior</label>
                                <select class="form-select" id="id_estado_anterior" name="id_estado_anterior">
                                    <option value="">Sin cambio</option>
                                    <?php foreach ($estados as $estado): ?>
                                    <option value="<?php echo $estado['id']; ?>" <?php echo ($mantenimiento['id_estado_anterior'] == $estado['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($estado['nombre']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Estado Nuevo -->
                            <div class="mb-3">
                                <label for="id_estado_nuevo" class="form-label">Estado Nuevo</label>
                                <select class="form-select" id="id_estado_nuevo" name="id_estado_nuevo">
                                    <option value="">Sin cambio</option>
                                    <?php foreach ($estados as $estado): ?>
                                    <option value="<?php echo $estado['id']; ?>" <?php echo ($mantenimiento['id_estado_nuevo'] == $estado['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($estado['nombre']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="alert alert-info small mb-0">
                                <i class="fas fa-lightbulb"></i>
                                El estado del equipo se actualizará automáticamente si selecciona un estado nuevo.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Información -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-info-circle"></i> Información</h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0 small">
                                <li class="mb-2">
                                    <i class="fas fa-user text-muted"></i>
                                    <strong>Registrado por:</strong><br>
                                    <?php echo htmlspecialchars($mantenimiento['usuario_registro'] ?? 'N/A'); ?>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-calendar text-muted"></i>
                                    <strong>Fecha de registro:</strong><br>
                                    <?php echo date('d/m/Y H:i', strtotime($mantenimiento['fecha_registro'])); ?>
                                </li>
                                <li>
                                    <i class="fas fa-box text-muted"></i>
                                    <strong>Repuestos actuales:</strong><br>
                                    <?php echo count($repuestos_asociados); ?> repuesto(s)
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Acciones -->
                    <div class="card">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Guardar Cambios
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
    
    <!-- Template para repuesto nuevo -->
    <template id="templateRepuestoNuevo">
        <div class="repuesto-item">
            <div class="row align-items-end">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Repuesto</label>
                    <select class="form-select select-repuesto" name="repuestos_nuevos[]" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($repuestos as $rep): ?>
                        <option value="<?php echo $rep['id']; ?>" 
                                data-precio="<?php echo $rep['precio_unitario']; ?>"
                                data-stock="<?php echo $rep['stock_actual']; ?>"
                                data-unidad="<?php echo $rep['unidad_medida']; ?>">
                            <?php echo htmlspecialchars($rep['codigo']); ?> - 
                            <?php echo htmlspecialchars($rep['nombre']); ?>
                            (Stock: <?php echo $rep['stock_actual']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Cantidad</label>
                    <input type="number" class="form-control cantidad-repuesto" 
                           name="cantidades_nuevas[]" min="1" value="1" required>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Parte Requerida</label>
                    <input type="text" class="form-control" 
                           name="partes_requeridas_nuevas[]" 
                           placeholder="Ej: Fusor" required>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Fecha Cambio</label>
                    <input type="date" class="form-control" 
                           name="fechas_cambio_nuevas[]" 
                           value="<?php echo $mantenimiento['fecha_mantenimiento']; ?>" required>
                </div>
                <div class="col-md-1 mb-2 text-end">
                    <button type="button" class="btn btn-danger btn-sm btn-eliminar-nuevo" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="col-md-12">
                    <input type="text" class="form-control form-control-sm" 
                           name="observaciones_repuestos_nuevas[]" 
                           placeholder="Observaciones del repuesto (opcional)">
                </div>
            </div>
        </div>
    </template>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        let repuestosAEliminar = [];
        
        $(document).ready(function() {
            // Inicializar Select2
            $('#id_equipo').select2({
                theme: 'bootstrap-5',
                placeholder: 'Seleccione un equipo...',
                width: '100%'
            });
            
            // Mostrar info del equipo seleccionado
            $('#id_equipo').on('change', function() {
                const selected = $(this).find(':selected');
                if (selected.val()) {
                    $('#equipo_marca').text(selected.data('marca'));
                    $('#equipo_modelo').text(selected.data('modelo'));
                    $('#equipo_serie').text(selected.data('serie'));
                    $('#infoEquipo').slideDown();
                } else {
                    $('#infoEquipo').slideUp();
                }
            });
            
            // Agregar repuesto nuevo
            $('#btnAgregarRepuesto').on('click', function() {
                const template = $('#templateRepuestoNuevo').html();
                const primeraVez = $('#contenedorRepuestosNuevos p').length > 0;
                
                if (primeraVez) {
                    $('#contenedorRepuestosNuevos').html('');
                }
                
                $('#contenedorRepuestosNuevos').append(template);
                
                // Inicializar Select2 para el nuevo select
                $('#contenedorRepuestosNuevos .select-repuesto:last').select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Seleccione repuesto...',
                    width: '100%'
                });
            });
            
            // Eliminar repuesto nuevo
            $(document).on('click', '.btn-eliminar-nuevo', function() {
                $(this).closest('.repuesto-item').remove();
                
                // Si no quedan repuestos, mostrar mensaje
                if ($('#contenedorRepuestosNuevos .repuesto-item').length === 0) {
                    $('#contenedorRepuestosNuevos').html(
                        '<p class="text-muted text-center py-3">' +
                        '<i class="fas fa-info-circle"></i> ' +
                        'Haga clic en "Agregar Repuesto" para añadir nuevos repuestos.' +
                        '</p>'
                    );
                }
            });
            
            // Eliminar repuesto existente
            $(document).on('click', '.btn-eliminar-existente', function() {
                const id = $(this).data('id');
                const item = $(this).closest('.repuesto-item');
                
                Swal.fire({
                    title: '¿Está seguro?',
                    text: 'El repuesto será devuelto al inventario',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        repuestosAEliminar.push(id);
                        item.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }
                });
            });
            
            // Validar stock al cambiar cantidad o repuesto
            $(document).on('change', '.select-repuesto, .cantidad-repuesto', function() {
                const item = $(this).closest('.repuesto-item');
                const select = item.find('.select-repuesto');
                const cantidad = parseInt(item.find('.cantidad-repuesto').val()) || 0;
                const stock = parseInt(select.find(':selected').data('stock')) || 0;
                
                if (cantidad > stock) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Stock insuficiente',
                        text: `Stock disponible: ${stock}`,
                        confirmButtonText: 'OK'
                    });
                    item.find('.cantidad-repuesto').val(stock);
                }
            });
            
            // Validación del formulario
            $('#formMantenimiento').on('submit', function(e) {
                // Agregar repuestos a eliminar al formulario
                $('#repuestosEliminar').remove();
                repuestosAEliminar.forEach(id => {
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'repuestos_eliminar[]',
                        value: id
                    }).appendTo('#formMantenimiento');
                });
                
                const equipo = $('#id_equipo').val();
                const tipo = $('#id_tipo_demanda').val();
                const descripcion = $('#descripcion').val().trim();
                
                if (!equipo || !tipo || !descripcion) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Por favor complete todos los campos obligatorios'
                    });
                    return false;
                }
                
                return true;
            });
        });
    </script>
</body>
</html>
