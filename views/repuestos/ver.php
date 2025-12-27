<?php
/**
 * Vista de detalles y historial de repuesto
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
    $_SESSION['mensaje'] = 'ID de repuesto no proporcionado';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Repuesto.php';
require_once __DIR__ . '/../../models/MantenimientoRepuesto.php';

$database = new Database();
$db = $database->getConnection();
$repuestoModel = new Repuesto($db);
$mantRepuestoModel = new MantenimientoRepuesto($db);

$id = intval($_GET['id']);
$repuesto = $repuestoModel->getById($id);

if (!$repuesto) {
    $_SESSION['mensaje'] = 'Repuesto no encontrado';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: index.php');
    exit;
}

// Obtener historial de uso
$historial = $mantRepuestoModel->getByRepuesto($id);

// Obtener movimientos de stock
$query_movimientos = "SELECT rm.*, u.nombre_completo as usuario
                     FROM repuestos_movimientos rm
                     LEFT JOIN usuarios u ON rm.id_usuario_registro = u.id
                     WHERE rm.id_repuesto = :id_repuesto
                     ORDER BY rm.fecha_movimiento DESC
                     LIMIT 50";
$stmt_mov = $db->prepare($query_movimientos);
$stmt_mov->bindParam(':id_repuesto', $id);
$stmt_mov->execute();
$movimientos = $stmt_mov->fetchAll(PDO::FETCH_ASSOC);

// Calcular estadísticas
$total_usado = array_sum(array_column($historial, 'cantidad'));
$total_mantenimientos = count($historial);
$costo_total = array_sum(array_column($historial, 'costo_total'));

$page_title = "Detalles del Repuesto - " . APP_NAME;
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
        
        [data-theme="dark"] .list-group-item {
            background-color: #2b3035;
            border-color: #495057;
            color: #e9ecef;
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
        }
        
        .badge-salida {
            background-color: #f8d7da;
            color: #842029;
        }
        
        .badge-ajuste {
            background-color: #cff4fc;
            color: #055160;
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
                <li class="breadcrumb-item active">Detalles</li>
            </ol>
        </nav>
        
        <!-- Título -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h2><i class="fas fa-box"></i> <?php echo htmlspecialchars($repuesto['nombre']); ?></h2>
                <p class="text-muted mb-0">
                    Código: <strong><?php echo htmlspecialchars($repuesto['codigo']); ?></strong>
                </p>
            </div>
            <div class="col-md-6 text-end">
                <a href="editar.php?id=<?php echo $id; ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Editar
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>
        
        <!-- Información General -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> Información General</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <dl class="row">
                                    <dt class="col-sm-5">Código:</dt>
                                    <dd class="col-sm-7"><strong><?php echo htmlspecialchars($repuesto['codigo']); ?></strong></dd>
                                    
                                    <dt class="col-sm-5">Nombre:</dt>
                                    <dd class="col-sm-7"><?php echo htmlspecialchars($repuesto['nombre']); ?></dd>
                                    
                                    <dt class="col-sm-5">Marca:</dt>
                                    <dd class="col-sm-7"><?php echo htmlspecialchars($repuesto['marca'] ?? 'N/A'); ?></dd>
                                    
                                    <dt class="col-sm-5">Modelo Compatible:</dt>
                                    <dd class="col-sm-7"><?php echo htmlspecialchars($repuesto['modelo_compatible'] ?? 'N/A'); ?></dd>
                                </dl>
                            </div>
                            <div class="col-md-6">
                                <dl class="row">
                                    <dt class="col-sm-5">Stock Actual:</dt>
                                    <dd class="col-sm-7">
                                        <strong class="fs-5"><?php echo $repuesto['stock_actual']; ?></strong> 
                                        <?php echo htmlspecialchars($repuesto['unidad_medida']); ?>
                                    </dd>
                                    
                                    <dt class="col-sm-5">Stock Mínimo:</dt>
                                    <dd class="col-sm-7"><?php echo $repuesto['stock_minimo']; ?></dd>
                                    
                                    <dt class="col-sm-5">Precio Unitario:</dt>
                                    <dd class="col-sm-7">S/ <?php echo number_format($repuesto['precio_unitario'], 2); ?></dd>
                                    
                                    <dt class="col-sm-5">Estado:</dt>
                                    <dd class="col-sm-7">
                                        <?php
                                        $estado = $repuesto['estado_stock'];
                                        $badge_class = 'success';
                                        if ($estado == 'Stock Bajo') $badge_class = 'warning';
                                        if ($estado == 'Sin Stock') $badge_class = 'danger';
                                        ?>
                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                            <?php echo $estado; ?>
                                        </span>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                        
                        <?php if ($repuesto['descripcion']): ?>
                        <hr>
                        <h6>Descripción:</h6>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($repuesto['descripcion'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Estadísticas -->
            <div class="col-lg-4">
                <div class="card stat-card mb-3" style="border-color: #0d6efd;">
                    <div class="card-body">
                        <h6 class="text-muted mb-1">Veces Utilizado</h6>
                        <h3 class="mb-0 text-primary"><?php echo $total_mantenimientos; ?></h3>
                        <small class="text-muted">Mantenimientos</small>
                    </div>
                </div>
                
                <div class="card stat-card mb-3" style="border-color: #6c757d;">
                    <div class="card-body">
                        <h6 class="text-muted mb-1">Cantidad Total Usada</h6>
                        <h3 class="mb-0"><?php echo $total_usado; ?></h3>
                        <small class="text-muted"><?php echo htmlspecialchars($repuesto['unidad_medida']); ?></small>
                    </div>
                </div>
                
                <div class="card stat-card" style="border-color: #198754;">
                    <div class="card-body">
                        <h6 class="text-muted mb-1">Costo Total</h6>
                        <h3 class="mb-0 text-success">S/ <?php echo number_format($costo_total, 2); ?></h3>
                        <small class="text-muted">Acumulado</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="historial-tab" data-bs-toggle="tab" data-bs-target="#historial" type="button">
                    <i class="fas fa-history"></i> Historial de Uso
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="movimientos-tab" data-bs-toggle="tab" data-bs-target="#movimientos" type="button">
                    <i class="fas fa-exchange-alt"></i> Movimientos de Stock
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="myTabContent">
            <!-- Historial de Uso -->
            <div class="tab-pane fade show active" id="historial">
                <div class="card">
                    <div class="card-body">
                        <?php if (count($historial) > 0): ?>
                        <div class="table-responsive">
                            <table id="tablaHistorial" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Mantenimiento</th>
                                        <th>Equipo</th>
                                        <th>Parte Requerida</th>
                                        <th>Cantidad</th>
                                        <th>Técnico</th>
                                        <th>Costo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($historial as $item): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($item['fecha_cambio'])); ?></td>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>/views/mantenimientos/ver.php?id=<?php echo $item['id_mantenimiento']; ?>" 
                                               class="text-decoration-none">
                                                MANT-<?php echo $item['id_mantenimiento']; ?>
                                            </a>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['equipo']); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($item['marca_equipo']); ?> 
                                                <?php echo htmlspecialchars($item['modelo_equipo']); ?>
                                            </small>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['parte_requerida']); ?></td>
                                        <td class="text-center">
                                            <strong><?php echo $item['cantidad']; ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['tecnico_responsable'] ?? 'N/A'); ?></td>
                                        <td>S/ <?php echo number_format($item['costo_total'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-active">
                                        <th colspan="4" class="text-end">TOTALES:</th>
                                        <th class="text-center"><?php echo $total_usado; ?></th>
                                        <th></th>
                                        <th>S/ <?php echo number_format($costo_total, 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No hay historial de uso</h5>
                            <p class="text-muted">Este repuesto aún no ha sido utilizado en ningún mantenimiento.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Movimientos de Stock -->
            <div class="tab-pane fade" id="movimientos">
                <div class="card">
                    <div class="card-body">
                        <?php if (count($movimientos) > 0): ?>
                        <div class="table-responsive">
                            <table id="tablaMovimientos" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Cantidad</th>
                                        <th>Stock Anterior</th>
                                        <th>Stock Nuevo</th>
                                        <th>Motivo</th>
                                        <th>Referencia</th>
                                        <th>Usuario</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($movimientos as $mov): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></td>
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
                                        </td>
                                        <td class="text-center"><?php echo $mov['stock_anterior']; ?></td>
                                        <td class="text-center">
                                            <strong><?php echo $mov['stock_nuevo']; ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($mov['motivo'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($mov['referencia']): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($mov['referencia']); ?></span>
                                            <?php else: ?>
                                            -
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
                            <p class="text-muted">No se han realizado movimientos de stock para este repuesto.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Inicializar DataTables
            $('#tablaHistorial').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                order: [[0, 'desc']],
                pageLength: 25
            });
            
            $('#tablaMovimientos').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                order: [[0, 'desc']],
                pageLength: 25
            });
        });
    </script>
</body>
</html>
