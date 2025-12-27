<?php
/**
 * Vista para crear nuevo mantenimiento con repuestos
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

// Obtener ID del equipo si viene por parámetro
$id_equipo = $_GET['equipo'] ?? null;
$equipo_seleccionado = null;
if ($id_equipo) {
    $equipo_seleccionado = $equipoModel->getById($id_equipo);
}

// Obtener datos para los selects
$equipos = $equipoModel->getAll();
$repuestos = $repuestoModel->getAll();

// Obtener tipos de demanda y estados
$query_tipos = "SELECT * FROM tipos_demanda WHERE activo = 1 ORDER BY nombre";
$stmt_tipos = $db->prepare($query_tipos);
$stmt_tipos->execute();
$tipos_demanda = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);

$query_estados = "SELECT * FROM estados_equipo WHERE activo = 1 ORDER BY nombre";
$stmt_estados = $db->prepare($query_estados);
$stmt_estados->execute();
$estados = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Nuevo Mantenimiento - " . APP_NAME;
$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // Crear el mantenimiento
        $data_mant = [
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
        
        $id_mantenimiento = $mantenimientoModel->create($data_mant);
        
        if (!$id_mantenimiento) {
            throw new Exception("Error al crear el mantenimiento");
        }
        
        // Actualizar estado del equipo si cambió
        if (!empty($data_mant['id_estado_nuevo'])) {
            $equipoModel->cambiarEstado($data_mant['id_equipo'], $data_mant['id_estado_nuevo']);
        }
        
        // Agregar repuestos si hay
        if (!empty($_POST['repuestos'])) {
            $mantRepuestoModel = new MantenimientoRepuesto($db);
            
            foreach ($_POST['repuestos'] as $index => $id_repuesto) {
                if (empty($id_repuesto)) continue;
                
                $cantidad = intval($_POST['cantidades'][$index] ?? 1);
                $parte_requerida = $_POST['partes_requeridas'][$index] ?? '';
                $fecha_cambio = $_POST['fechas_cambio'][$index] ?? $_POST['fecha_mantenimiento'];
                $observacion_rep = $_POST['observaciones_repuestos'][$index] ?? '';
                
                // Obtener precio del repuesto
                $repuesto = $repuestoModel->getById($id_repuesto);
                $costo_total = $cantidad * ($repuesto['precio_unitario'] ?? 0);
                
                $data_rep = [
                    'id_mantenimiento' => $id_mantenimiento,
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
                    $referencia = "MANT-" . $id_mantenimiento;
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
            $id_mantenimiento,
            'create',
            null,
            json_encode($data_mant)
        );
        
        $db->commit();
        
        $_SESSION['mensaje'] = 'Mantenimiento registrado exitosamente';
        $_SESSION['tipo_mensaje'] = 'success';
        header('Location: index.php');
        exit;
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'danger';
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
        
        .required::after {
            content: " *";
            color: red;
        }
        
        .repuesto-item {
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #0d6efd;
            transition: all 0.3s ease;
        }
        
        .repuesto-item:hover {
            box-shadow: 0 0.125rem 0.5rem rgba(0,0,0,0.1);
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
        
        .stock-badge {
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
        }
        
        #btnAgregarRepuesto {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.7);
            }
            50% {
                box-shadow: 0 0 0 10px rgba(13, 110, 253, 0);
            }
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
                <li class="breadcrumb-item active">Nuevo Mantenimiento</li>
            </ol>
        </nav>
        
        <!-- Título -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h2><i class="fas fa-wrench"></i> Nuevo Mantenimiento</h2>
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
                                                <?php echo ($equipo_seleccionado && $equipo_seleccionado['id'] == $equipo['id']) ? 'selected' : ''; ?>
                                                data-marca="<?php echo htmlspecialchars($equipo['marca'] ?? ''); ?>"
                                                data-modelo="<?php echo htmlspecialchars($equipo['modelo'] ?? ''); ?>"
                                                data-serie="<?php echo htmlspecialchars($equipo['numero_serie'] ?? ''); ?>"
                                                data-estado="<?php echo $equipo['id_estado']; ?>">
                                            <?php echo htmlspecialchars($equipo['codigo_patrimonial'] ?? ''); ?> - 
                                            <?php echo htmlspecialchars($equipo['marca'] ?? ''); ?> 
                                            <?php echo htmlspecialchars($equipo['modelo'] ?? ''); ?> 
                                            (<?php echo htmlspecialchars($equipo['numero_serie'] ?? 'S/N'); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <!-- Info del equipo seleccionado -->
                                    <div id="infoEquipo" class="info-equipo mt-3" style="display: none;">
                                        <h6><i class="fas fa-info-circle"></i> Información del Equipo</h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <small class="text-muted">Marca:</small><br>
                                                <strong id="equipo_marca">-</strong>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted">Modelo:</small><br>
                                                <strong id="equipo_modelo">-</strong>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted">Serie:</small><br>
                                                <strong id="equipo_serie">-</strong>
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
                                        <option value="<?php echo $tipo['id']; ?>">
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
                                           value="<?php echo date('Y-m-d'); ?>"
                                           required>
                                </div>
                                
                                <!-- Técnico -->
                                <div class="col-md-12 mb-3">
                                    <label for="tecnico_responsable" class="form-label">Técnico Responsable</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="tecnico_responsable" 
                                           name="tecnico_responsable"
                                           placeholder="Nombre del técnico">
                                </div>
                                
                                <!-- Descripción -->
                                <div class="col-md-12 mb-3">
                                    <label for="descripcion" class="form-label required">Descripción</label>
                                    <textarea class="form-control" 
                                              id="descripcion" 
                                              name="descripcion" 
                                              rows="4"
                                              required
                                              placeholder="Describa el trabajo realizado..."></textarea>
                                </div>
                                
                                <!-- Observaciones -->
                                <div class="col-md-12 mb-3">
                                    <label for="observaciones" class="form-label">Observaciones</label>
                                    <textarea class="form-control" 
                                              id="observaciones" 
                                              name="observaciones" 
                                              rows="3"
                                              placeholder="Observaciones adicionales..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Repuestos Utilizados -->
                    <div class="card mb-4 border-primary">
                        <div class="card-header bg-primary bg-opacity-10 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-boxes"></i> Repuestos Utilizados 
                                <span class="badge bg-secondary">Opcional</span>
                            </h5>
                            <button type="button" class="btn btn-primary" id="btnAgregarRepuesto">
                                <i class="fas fa-plus-circle"></i> Agregar Repuesto
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="contenedorRepuestos">
                                <p class="text-muted text-center py-3">
                                    <i class="fas fa-info-circle"></i> 
                                    No hay repuestos agregados. Haga clic en "Agregar Repuesto" para añadir.
                                </p>
                            </div>
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
                                    <option value="<?php echo $estado['id']; ?>">
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
                                    <option value="<?php echo $estado['id']; ?>">
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
                    
                    <!-- Acciones -->
                    <div class="card">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Guardar Mantenimiento
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
    
    <!-- Template para repuesto -->
    <template id="templateRepuesto">
        <div class="repuesto-item border rounded p-3 mb-3 bg-light">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-box"></i> Repuesto</h6>
                        <button type="button" class="btn btn-danger btn-sm btn-eliminar-repuesto" title="Eliminar">
                            <i class="fas fa-trash"></i> Eliminar
                        </button>
                    </div>
                    <hr class="mt-2">
                </div>
            </div>
            
            <div class="row align-items-end">
                <!-- Filtros de búsqueda -->
                <div class="col-md-3 mb-2">
                    <label class="form-label">Filtrar por Marca</label>
                    <select class="form-select filtro-marca-repuesto">
                        <option value="">Todas las marcas</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Filtrar por Modelo</label>
                    <select class="form-select filtro-modelo-repuesto" disabled>
                        <option value="">Todos los modelos</option>
                    </select>
                </div>
                <div class="col-md-6 mb-2">
                    <label class="form-label text-muted">
                        <small><i class="fas fa-info-circle"></i> Use los filtros para encontrar repuestos compatibles</small>
                    </label>
                </div>
            </div>
            
            <div class="row align-items-end">
                <div class="col-md-5 mb-2">
                    <label class="form-label required">Seleccione el Repuesto</label>
                    <select class="form-select select-repuesto" name="repuestos[]" required>
                        <option value="">Seleccione un repuesto...</option>
                        <?php foreach ($repuestos as $rep): ?>
                        <option value="<?php echo $rep['id']; ?>" 
                                data-precio="<?php echo $rep['precio_unitario'] ?? 0; ?>"
                                data-stock="<?php echo $rep['stock_actual'] ?? 0; ?>"
                                data-unidad="<?php echo $rep['unidad_medida'] ?? 'Unidad'; ?>"
                                data-marca="<?php echo htmlspecialchars($rep['marca'] ?? ''); ?>"
                                data-modelo="<?php echo htmlspecialchars($rep['modelo_compatible'] ?? ''); ?>"
                                data-codigo="<?php echo htmlspecialchars($rep['codigo'] ?? ''); ?>"
                                data-nombre="<?php echo htmlspecialchars($rep['nombre'] ?? ''); ?>">
                            <?php echo htmlspecialchars($rep['codigo'] ?? ''); ?> - 
                            <?php echo htmlspecialchars($rep['nombre'] ?? ''); ?>
                            (Stock: <?php echo $rep['stock_actual'] ?? 0; ?> <?php echo $rep['unidad_medida'] ?? 'Unidad'; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted stock-info d-none">
                        Stock disponible: <span class="stock-disponible badge bg-info">0</span>
                    </small>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label required">Cantidad</label>
                    <input type="number" class="form-control cantidad-repuesto" 
                           name="cantidades[]" min="1" value="1" required>
                    <small class="text-danger d-none error-stock"></small>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label required">Parte Requerida</label>
                    <input type="text" class="form-control" 
                           name="partes_requeridas[]" 
                           placeholder="Ej: Fusor, Rodillo, Toner" required>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label required">Fecha Cambio</label>
                    <input type="date" class="form-control" 
                           name="fechas_cambio[]" 
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-12 mb-2">
                    <label class="form-label">Observaciones</label>
                    <input type="text" class="form-control" 
                           name="observaciones_repuestos[]" 
                           placeholder="Observaciones adicionales del repuesto (opcional)">
                </div>
            </div>
        </div>
    </template>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        let marcasData = [];
        let modelosData = [];
        
        $(document).ready(function() {
            // Cargar marcas y modelos para filtros
            cargarMarcasYModelos();
            
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
                    $('#id_estado_anterior').val(selected.data('estado'));
                    $('#infoEquipo').slideDown();
                } else {
                    $('#infoEquipo').slideUp();
                }
            });
            
            // Si hay equipo preseleccionado, mostrar info
            <?php if ($equipo_seleccionado): ?>
            $('#id_equipo').trigger('change');
            <?php endif; ?>
            
            // Agregar repuesto
            $('#btnAgregarRepuesto').on('click', function() {
                const template = $('#templateRepuesto').html();
                const primeraVez = $('#contenedorRepuestos p').length > 0;
                
                if (primeraVez) {
                    $('#contenedorRepuestos').html('');
                }
                
                $('#contenedorRepuestos').append(template);
                
                const nuevoItem = $('#contenedorRepuestos .repuesto-item:last');
                
                // Inicializar Select2 para el nuevo select
                nuevoItem.find('.select-repuesto').select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Seleccione repuesto...',
                    width: '100%'
                });
                
                // Cargar filtros en el nuevo item
                cargarFiltrosEnItem(nuevoItem);
            });
            
            // Eliminar repuesto
            $(document).on('click', '.btn-eliminar-repuesto', function() {
                if (confirm('¿Está seguro de eliminar este repuesto?')) {
                    $(this).closest('.repuesto-item').remove();
                    
                    // Si no quedan repuestos, mostrar mensaje
                    if ($('#contenedorRepuestos .repuesto-item').length === 0) {
                        $('#contenedorRepuestos').html(
                            '<p class="text-muted text-center py-3">' +
                            '<i class="fas fa-info-circle"></i> ' +
                            'No hay repuestos agregados. Haga clic en "Agregar Repuesto" para añadir.' +
                            '</p>'
                        );
                    }
                }
            });
            
            // Filtrar por marca
            $(document).on('change', '.filtro-marca-repuesto', function() {
                const item = $(this).closest('.repuesto-item');
                const idMarca = $(this).val();
                const selectModelo = item.find('.filtro-modelo-repuesto');
                
                if (idMarca) {
                    // Cargar modelos de la marca
                    const modelosFiltrados = modelosData.filter(m => m.id_marca == idMarca);
                    let options = '<option value="">Todos los modelos</option>';
                    modelosFiltrados.forEach(modelo => {
                        options += `<option value="${modelo.id}">${modelo.nombre}</option>`;
                    });
                    selectModelo.html(options).prop('disabled', false);
                } else {
                    selectModelo.html('<option value="">Todos los modelos</option>').prop('disabled', true);
                }
                
                // Aplicar filtros
                aplicarFiltrosRepuestos(item);
            });
            
            // Filtrar por modelo
            $(document).on('change', '.filtro-modelo-repuesto', function() {
                const item = $(this).closest('.repuesto-item');
                aplicarFiltrosRepuestos(item);
            });
            
            // Validar stock al seleccionar repuesto
            $(document).on('change', '.select-repuesto', function() {
                const item = $(this).closest('.repuesto-item');
                const selected = $(this).find(':selected');
                const stock = parseInt(selected.data('stock')) || 0;
                const unidad = selected.data('unidad') || '';
                
                if (selected.val()) {
                    item.find('.stock-info').removeClass('d-none');
                    item.find('.stock-disponible').text(`${stock} ${unidad}`);
                    
                    // Ajustar cantidad si excede el stock
                    const cantidad = parseInt(item.find('.cantidad-repuesto').val()) || 1;
                    if (cantidad > stock) {
                        item.find('.cantidad-repuesto').val(stock);
                    }
                } else {
                    item.find('.stock-info').addClass('d-none');
                }
            });
            
            // Validar stock al cambiar cantidad
            $(document).on('change', '.cantidad-repuesto', function() {
                const item = $(this).closest('.repuesto-item');
                const select = item.find('.select-repuesto');
                const cantidad = parseInt($(this).val()) || 0;
                const stock = parseInt(select.find(':selected').data('stock')) || 0;
                const errorMsg = item.find('.error-stock');
                
                if (cantidad > stock) {
                    errorMsg.text(`Stock insuficiente. Disponible: ${stock}`).removeClass('d-none');
                    $(this).addClass('is-invalid');
                    return false;
                } else {
                    errorMsg.addClass('d-none');
                    $(this).removeClass('is-invalid');
                }
            });
            
            // Validación del formulario
            $('#formMantenimiento').on('submit', function(e) {
                const equipo = $('#id_equipo').val();
                const tipo = $('#id_tipo_demanda').val();
                const descripcion = $('#descripcion').val().trim();
                
                if (!equipo || !tipo || !descripcion) {
                    e.preventDefault();
                    alert('Por favor complete todos los campos obligatorios');
                    return false;
                }
                
                // Validar stock de repuestos
                let stockValido = true;
                $('.repuesto-item').each(function() {
                    const item = $(this);
                    const select = item.find('.select-repuesto');
                    const cantidad = parseInt(item.find('.cantidad-repuesto').val()) || 0;
                    const stock = parseInt(select.find(':selected').data('stock')) || 0;
                    
                    if (select.val() && cantidad > stock) {
                        alert('Hay repuestos con cantidad mayor al stock disponible');
                        stockValido = false;
                        return false;
                    }
                });
                
                if (!stockValido) {
                    e.preventDefault();
                    return false;
                }
                
                return true;
            });
        });
        
        // Cargar marcas y modelos desde la API
        function cargarMarcasYModelos() {
            $.ajax({
                url: '<?php echo BASE_URL; ?>/controllers/repuestos.php?action=getMarcas',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        marcasData = response.data;
                    }
                }
            });
            
            $.ajax({
                url: '<?php echo BASE_URL; ?>/controllers/repuestos.php?action=getModelos',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        modelosData = response.data;
                    }
                }
            });
        }
        
        // Cargar filtros en un item nuevo
        function cargarFiltrosEnItem(item) {
            // Cargar marcas
            let optionsMarcas = '<option value="">Todas las marcas</option>';
            marcasData.forEach(marca => {
                optionsMarcas += `<option value="${marca.id}">${marca.nombre}</option>`;
            });
            item.find('.filtro-marca-repuesto').html(optionsMarcas);
        }
        
        // Aplicar filtros a la lista de repuestos
        function aplicarFiltrosRepuestos(item) {
            const marcaSeleccionada = item.find('.filtro-marca-repuesto option:selected').text();
            const modeloSeleccionado = item.find('.filtro-modelo-repuesto option:selected').text();
            const selectRepuesto = item.find('.select-repuesto');
            
            selectRepuesto.find('option').each(function() {
                if ($(this).val() === '') return; // Skip la opción por defecto
                
                const marcaRepuesto = $(this).data('marca') || '';
                const modeloRepuesto = $(this).data('modelo') || '';
                let mostrar = true;
                
                if (marcaSeleccionada && marcaSeleccionada !== 'Todas las marcas') {
                    mostrar = mostrar && (marcaRepuesto === marcaSeleccionada);
                }
                
                if (modeloSeleccionado && modeloSeleccionado !== 'Todos los modelos') {
                    mostrar = mostrar && (modeloRepuesto === modeloSeleccionado);
                }
                
                if (mostrar) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
            
            // Actualizar Select2
            selectRepuesto.trigger('change.select2');
        }
    </script>
</body>
</html>
