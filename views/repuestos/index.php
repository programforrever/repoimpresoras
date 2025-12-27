<?php
/**
 * Vista principal de Repuestos
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

// Incluir modelos
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Repuesto.php';

$database = new Database();
$db = $database->getConnection();
$repuestoModel = new Repuesto($db);

// Obtener repuestos
$repuestos = $repuestoModel->getAll();
$stockBajo = $repuestoModel->getStockBajo();
$totalRepuestos = $repuestoModel->count();

$page_title = "Gestión de Repuestos - " . APP_NAME;
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
    
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <!-- Estilos personalizados -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    
    <style>
        .stock-badge {
            padding: 0.35em 0.65em;
            font-size: 0.85em;
            font-weight: 600;
            border-radius: 0.375rem;
        }
        
        .stock-normal {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        
        .stock-bajo {
            background-color: #fff3cd;
            color: #664d03;
        }
        
        .stock-sin {
            background-color: #f8d7da;
            color: #842029;
        }
        
        .card-stats {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        
        .card-stats:hover {
            transform: translateY(-5px);
        }
        
        .card-stats.primary {
            border-color: var(--bs-primary);
        }
        
        .card-stats.warning {
            border-color: var(--bs-warning);
        }
        
        .card-stats.success {
            border-color: var(--bs-success);
        }
        
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
        
        [data-theme="dark"] .stock-normal {
            background-color: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
        }
        
        [data-theme="dark"] .stock-bajo {
            background-color: rgba(245, 158, 11, 0.2);
            color: #fcd34d;
        }
        
        [data-theme="dark"] .stock-sin {
            background-color: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
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
                <li class="breadcrumb-item active">Repuestos</li>
            </ol>
        </nav>
        
        <!-- Título y botones -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h2><i class="fas fa-boxes"></i> Gestión de Repuestos</h2>
            </div>
            <div class="col-md-6 text-end">
                <a href="crear.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuevo Repuesto
                </a>
                <a href="movimientos.php" class="btn btn-outline-secondary">
                    <i class="fas fa-exchange-alt"></i> Movimientos
                </a>
            </div>
        </div>
        
        <!-- Tarjetas de estadísticas -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card card-stats primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Repuestos</h6>
                                <h3 class="mb-0"><?php echo $totalRepuestos; ?></h3>
                            </div>
                            <div class="fs-1 text-primary">
                                <i class="fas fa-boxes"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card card-stats warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Stock Bajo</h6>
                                <h3 class="mb-0 text-warning"><?php echo count($stockBajo); ?></h3>
                            </div>
                            <div class="fs-1 text-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card card-stats success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Stock Total</h6>
                                <h3 class="mb-0 text-success">
                                    <?php
                                    $stockTotal = array_sum(array_column($repuestos, 'stock_actual'));
                                    echo number_format($stockTotal);
                                    ?>
                                </h3>
                            </div>
                            <div class="fs-1 text-success">
                                <i class="fas fa-cubes"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alertas de stock bajo -->
        <?php if (count($stockBajo) > 0): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Atención:</strong> Hay <?php echo count($stockBajo); ?> repuesto(s) con stock bajo o agotado.
            <a href="#" class="alert-link" data-bs-toggle="collapse" data-bs-target="#stockBajoDetalle">Ver detalle</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        
        <div class="collapse mb-3" id="stockBajoDetalle">
            <div class="card">
                <div class="card-body">
                    <h6>Repuestos con stock bajo:</h6>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($stockBajo as $item): ?>
                        <li class="mb-2">
                            <span class="badge bg-warning"><?php echo $item['codigo']; ?></span>
                            <?php echo htmlspecialchars($item['nombre']); ?> - 
                            <strong><?php echo $item['stock_actual']; ?></strong> unidades
                            (Mínimo: <?php echo $item['stock_minimo']; ?>)
                            <a href="editar.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary ms-2">
                                <i class="fas fa-plus"></i> Agregar Stock
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Tabla de repuestos -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaRepuestos" class="table table-hover">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Marca</th>
                                <th>Modelo Compatible</th>
                                <th>Stock Actual</th>
                                <th>Stock Mínimo</th>
                                <th>Estado</th>
                                <th>Precio Unit.</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($repuestos as $repuesto): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($repuesto['codigo']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($repuesto['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($repuesto['marca'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($repuesto['modelo_compatible'] ?? '-'); ?></td>
                                <td class="text-center">
                                    <strong><?php echo $repuesto['stock_actual']; ?></strong> 
                                    <small class="text-muted"><?php echo $repuesto['unidad_medida']; ?></small>
                                </td>
                                <td class="text-center"><?php echo $repuesto['stock_minimo']; ?></td>
                                <td>
                                    <?php
                                    $estado = $repuesto['estado_stock'];
                                    $clase = 'stock-normal';
                                    if ($estado == 'Stock Bajo') $clase = 'stock-bajo';
                                    if ($estado == 'Sin Stock') $clase = 'stock-sin';
                                    ?>
                                    <span class="stock-badge <?php echo $clase; ?>">
                                        <?php echo $estado; ?>
                                    </span>
                                </td>
                                <td>S/ <?php echo number_format($repuesto['precio_unitario'], 2); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="ver.php?id=<?php echo $repuesto['id']; ?>" 
                                           class="btn btn-info" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="editar.php?id=<?php echo $repuesto['id']; ?>" 
                                           class="btn btn-warning" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-danger" 
                                                onclick="eliminarRepuesto(<?php echo $repuesto['id']; ?>)"
                                                title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        $(document).ready(function() {
            // Inicializar DataTable
            $('#tablaRepuestos').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                order: [[0, 'asc']],
                pageLength: 25
            });
        });
        
        // Función para eliminar repuesto
        function eliminarRepuesto(id) {
            Swal.fire({
                title: '¿Está seguro?',
                text: 'El repuesto será desactivado',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '<?php echo BASE_URL; ?>/controllers/repuestos.php',
                        method: 'POST',
                        data: {
                            action: 'delete',
                            id: id
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Eliminado!',
                                    text: response.message,
                                    showConfirmButton: false,
                                    timer: 1500
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'No se pudo eliminar el repuesto', 'error');
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>
