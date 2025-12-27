<?php
/**
 * Vista de movimientos de inventario de repuestos
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
require_once __DIR__ . '/../../models/Repuesto.php';

$database = new Database();
$db = $database->getConnection();
$repuestoModel = new Repuesto($db);

// Filtros
$filtro_repuesto = $_GET['repuesto'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_desde = $_GET['desde'] ?? '';
$filtro_hasta = $_GET['hasta'] ?? '';

// Construir query con filtros
$query = "SELECT rm.*,
                 r.codigo,
                 r.nombre as nombre_repuesto,
                 r.unidad_medida,
                 u.nombre_completo as usuario
          FROM repuestos_movimientos rm
          INNER JOIN repuestos r ON rm.id_repuesto = r.id
          LEFT JOIN usuarios u ON rm.id_usuario_registro = u.id
          WHERE 1=1";

$params = [];

if ($filtro_repuesto) {
    $query .= " AND rm.id_repuesto = :id_repuesto";
    $params[':id_repuesto'] = $filtro_repuesto;
}

if ($filtro_tipo) {
    $query .= " AND rm.tipo_movimiento = :tipo";
    $params[':tipo'] = $filtro_tipo;
}

if ($filtro_desde) {
    $query .= " AND DATE(rm.fecha_movimiento) >= :fecha_desde";
    $params[':fecha_desde'] = $filtro_desde;
}

if ($filtro_hasta) {
    $query .= " AND DATE(rm.fecha_movimiento) <= :fecha_hasta";
    $params[':fecha_hasta'] = $filtro_hasta;
}

$query .= " ORDER BY rm.fecha_movimiento DESC LIMIT 500";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener todos los repuestos para el filtro
$repuestos = $repuestoModel->getAll();

// Calcular estadísticas
$total_entradas = 0;
$total_salidas = 0;
$total_ajustes = 0;

foreach ($movimientos as $mov) {
    switch ($mov['tipo_movimiento']) {
        case 'entrada':
            $total_entradas += $mov['cantidad'];
            break;
        case 'salida':
            $total_salidas += $mov['cantidad'];
            break;
        case 'ajuste':
            $total_ajustes += $mov['cantidad'];
            break;
    }
}

$page_title = "Movimientos de Inventario - " . APP_NAME;
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
    
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
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
        
        [data-theme="dark"] .table {
            color: #e9ecef;
        }
        
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background-color: #363a40;
            color: #e9ecef;
            border-color: #495057;
        }
        
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .badge-entrada {
            background-color: #d1e7dd;
            color: #0f5132;
            padding: 0.5em 0.75em;
        }
        
        .badge-salida {
            background-color: #f8d7da;
            color: #842029;
            padding: 0.5em 0.75em;
        }
        
        .badge-ajuste {
            background-color: #cff4fc;
            color: #055160;
            padding: 0.5em 0.75em;
        }
        
        [data-theme="dark"] .badge-entrada {
            background-color: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
        }
        
        [data-theme="dark"] .badge-salida {
            background-color: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }
        
        [data-theme="dark"] .badge-ajuste {
            background-color: rgba(59, 130, 246, 0.2);
            color: #67e8f9;
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
                <li class="breadcrumb-item"><a href="index.php">Repuestos</a></li>
                <li class="breadcrumb-item active">Movimientos</li>
            </ol>
        </nav>
        
        <!-- Título -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h2><i class="fas fa-exchange-alt"></i> Movimientos de Inventario</h2>
            </div>
            <div class="col-md-6 text-end">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>
        
        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stat-card" style="border-color: #198754;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Entradas</h6>
                                <h3 class="mb-0 text-success"><?php echo $total_entradas; ?></h3>
                            </div>
                            <div class="fs-1 text-success">
                                <i class="fas fa-arrow-up"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card stat-card" style="border-color: #dc3545;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Salidas</h6>
                                <h3 class="mb-0 text-danger"><?php echo $total_salidas; ?></h3>
                            </div>
                            <div class="fs-1 text-danger">
                                <i class="fas fa-arrow-down"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card stat-card" style="border-color: #0dcaf0;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Ajustes</h6>
                                <h3 class="mb-0 text-info"><?php echo $total_ajustes; ?></h3>
                            </div>
                            <div class="fs-1 text-info">
                                <i class="fas fa-sync-alt"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Filtros</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="formFiltros">
                    <div class="row">
                        <!-- Repuesto -->
                        <div class="col-md-3 mb-3">
                            <label for="repuesto" class="form-label">Repuesto</label>
                            <select class="form-select" id="repuesto" name="repuesto">
                                <option value="">Todos</option>
                                <?php foreach ($repuestos as $rep): ?>
                                <option value="<?php echo $rep['id']; ?>" <?php echo ($filtro_repuesto == $rep['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($rep['codigo']); ?> - 
                                    <?php echo htmlspecialchars($rep['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Tipo -->
                        <div class="col-md-3 mb-3">
                            <label for="tipo" class="form-label">Tipo de Movimiento</label>
                            <select class="form-select" id="tipo" name="tipo">
                                <option value="">Todos</option>
                                <option value="entrada" <?php echo ($filtro_tipo == 'entrada') ? 'selected' : ''; ?>>Entrada</option>
                                <option value="salida" <?php echo ($filtro_tipo == 'salida') ? 'selected' : ''; ?>>Salida</option>
                                <option value="ajuste" <?php echo ($filtro_tipo == 'ajuste') ? 'selected' : ''; ?>>Ajuste</option>
                            </select>
                        </div>
                        
                        <!-- Fecha Desde -->
                        <div class="col-md-2 mb-3">
                            <label for="desde" class="form-label">Desde</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="desde" 
                                   name="desde"
                                   value="<?php echo $filtro_desde; ?>">
                        </div>
                        
                        <!-- Fecha Hasta -->
                        <div class="col-md-2 mb-3">
                            <label for="hasta" class="form-label">Hasta</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="hasta" 
                                   name="hasta"
                                   value="<?php echo $filtro_hasta; ?>">
                        </div>
                        
                        <!-- Botones -->
                        <div class="col-md-2 mb-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filtrar
                                </button>
                                <a href="movimientos.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Limpiar
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tabla de movimientos -->
        <div class="card">
            <div class="card-body">
                <?php if (count($movimientos) > 0): ?>
                <div class="table-responsive">
                    <table id="tablaMovimientos" class="table table-hover">
                        <thead>
                            <tr>
                                <th>Fecha y Hora</th>
                                <th>Repuesto</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Stock Anterior</th>
                                <th>Stock Nuevo</th>
                                <th>Diferencia</th>
                                <th>Motivo</th>
                                <th>Referencia</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimientos as $mov): ?>
                            <tr>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($mov['fecha_movimiento'])); ?><br>
                                    <small class="text-muted"><?php echo date('H:i:s', strtotime($mov['fecha_movimiento'])); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($mov['codigo']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($mov['nombre_repuesto']); ?></small>
                                </td>
                                <td>
                                    <?php
                                    $badge_tipo = 'badge-entrada';
                                    $icono = 'arrow-up';
                                    if ($mov['tipo_movimiento'] == 'salida') {
                                        $badge_tipo = 'badge-salida';
                                        $icono = 'arrow-down';
                                    } elseif ($mov['tipo_movimiento'] == 'ajuste') {
                                        $badge_tipo = 'badge-ajuste';
                                        $icono = 'sync-alt';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_tipo; ?>">
                                        <i class="fas fa-<?php echo $icono; ?>"></i>
                                        <?php echo ucfirst($mov['tipo_movimiento']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <strong><?php echo $mov['cantidad']; ?></strong>
                                    <small class="text-muted d-block"><?php echo htmlspecialchars($mov['unidad_medida']); ?></small>
                                </td>
                                <td class="text-center"><?php echo $mov['stock_anterior']; ?></td>
                                <td class="text-center">
                                    <strong><?php echo $mov['stock_nuevo']; ?></strong>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $diferencia = $mov['stock_nuevo'] - $mov['stock_anterior'];
                                    $clase_dif = $diferencia > 0 ? 'text-success' : 'text-danger';
                                    $signo = $diferencia > 0 ? '+' : '';
                                    ?>
                                    <span class="<?php echo $clase_dif; ?>">
                                        <strong><?php echo $signo . $diferencia; ?></strong>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($mov['motivo']): ?>
                                        <small><?php echo htmlspecialchars($mov['motivo']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($mov['referencia']): ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($mov['referencia']); ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($mov['usuario'] ?? 'Sistema'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No hay movimientos registrados</h5>
                    <p class="text-muted">
                        <?php if ($filtro_repuesto || $filtro_tipo || $filtro_desde || $filtro_hasta): ?>
                        No se encontraron movimientos con los filtros aplicados.
                        <?php else: ?>
                        No se han realizado movimientos de inventario aún.
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Inicializar Select2
            $('#repuesto').select2({
                theme: 'bootstrap-5',
                placeholder: 'Seleccione un repuesto...',
                width: '100%',
                allowClear: true
            });
            
            // Inicializar DataTable
            $('#tablaMovimientos').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                order: [[0, 'desc']],
                pageLength: 50,
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-success btn-sm'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn btn-danger btn-sm'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimir',
                        className: 'btn btn-info btn-sm'
                    }
                ]
            });
        });
    </script>
</body>
</html>
