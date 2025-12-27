<?php
/**
 * Vista para editar repuesto
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

$database = new Database();
$db = $database->getConnection();
$repuestoModel = new Repuesto($db);

$id = intval($_GET['id']);
$repuesto = $repuestoModel->getById($id);

if (!$repuesto) {
    $_SESSION['mensaje'] = 'Repuesto no encontrado';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: index.php');
    exit;
}

$page_title = "Editar Repuesto - " . APP_NAME;
$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar') {
    try {
        // Validar código único (excepto el mismo registro)
        $existe = $repuestoModel->getByCodigo($_POST['codigo']);
        if ($existe && $existe['id'] != $id) {
            throw new Exception('Ya existe otro repuesto con ese código');
        }
        
        // Obtener datos anteriores para auditoría
        $datosAnteriores = $repuesto;
        
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
            
            $_SESSION['mensaje'] = 'Repuesto actualizado exitosamente';
            $_SESSION['tipo_mensaje'] = 'success';
            header('Location: index.php');
            exit;
        } else {
            throw new Exception("Error al actualizar el repuesto");
        }
        
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'danger';
        // Recargar datos
        $repuesto = $repuestoModel->getById($id);
    }
}

// Procesar ajuste de stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajustar_stock') {
    try {
        $tipo_movimiento = $_POST['tipo_movimiento'];
        $cantidad = intval($_POST['cantidad_ajuste']);
        $motivo = $_POST['motivo'] ?? '';
        
        if ($cantidad <= 0) {
            throw new Exception("La cantidad debe ser mayor a cero");
        }
        
        $resultado = $repuestoModel->actualizarStock($id, $cantidad, $tipo_movimiento);
        
        if ($resultado) {
            // Registrar movimiento
            $query = "INSERT INTO repuestos_movimientos 
                     (id_repuesto, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, motivo, id_usuario_registro)
                     VALUES (:id_repuesto, :tipo, :cantidad, :stock_anterior, :stock_nuevo, :motivo, :id_usuario)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id_repuesto', $id);
            $stmt->bindParam(':tipo', $tipo_movimiento);
            $stmt->bindParam(':cantidad', $cantidad);
            $stmt->bindParam(':stock_anterior', $resultado['stock_anterior']);
            $stmt->bindParam(':stock_nuevo', $resultado['stock_nuevo']);
            $stmt->bindParam(':motivo', $motivo);
            $stmt->bindParam(':id_usuario', $_SESSION['user_id']);
            $stmt->execute();
            
            $mensaje = "Stock actualizado: {$resultado['stock_anterior']} → {$resultado['stock_nuevo']}";
            $tipo_mensaje = 'success';
            
            // Recargar datos
            $repuesto = $repuestoModel->getById($id);
        } else {
            throw new Exception("Error al actualizar el stock (puede ser stock insuficiente)");
        }
        
    } catch (Exception $e) {
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
        
        .info-box {
            background-color: #e7f1ff;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #0d6efd;
        }
        
        [data-theme="dark"] .info-box {
            background-color: #1e3a5f;
        }
        
        .stock-badge {
            padding: 0.5em 1em;
            font-size: 1.1em;
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
                <li class="breadcrumb-item"><a href="index.php">Repuestos</a></li>
                <li class="breadcrumb-item active">Editar Repuesto</li>
            </ol>
        </nav>
        
        <!-- Título -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h2><i class="fas fa-edit"></i> Editar Repuesto</h2>
                <p class="text-muted mb-0">
                    Código: <strong><?php echo htmlspecialchars($repuesto['codigo']); ?></strong>
                </p>
            </div>
            <div class="col-md-6 text-end">
                <a href="ver.php?id=<?php echo $id; ?>" class="btn btn-info">
                    <i class="fas fa-eye"></i> Ver Detalles
                </a>
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
        
        <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="alert alert-<?php echo $_SESSION['tipo_mensaje']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['mensaje']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php 
            unset($_SESSION['mensaje']);
            unset($_SESSION['tipo_mensaje']);
        endif; 
        ?>
        
        <div class="row">
            <!-- Columna izquierda - Formulario -->
            <div class="col-lg-8">
                <!-- Información del Stock -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <h6 class="text-muted mb-2">Stock Actual</h6>
                                <h2 class="mb-0">
                                    <?php echo $repuesto['stock_actual']; ?>
                                    <small class="text-muted fs-6"><?php echo htmlspecialchars($repuesto['unidad_medida']); ?></small>
                                </h2>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-muted mb-2">Stock Mínimo</h6>
                                <h2 class="mb-0"><?php echo $repuesto['stock_minimo']; ?></h2>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-muted mb-2">Estado</h6>
                                <?php
                                $estado = $repuesto['estado_stock'];
                                $clase = 'stock-normal';
                                if ($estado == 'Stock Bajo') $clase = 'stock-bajo';
                                if ($estado == 'Sin Stock') $clase = 'stock-sin';
                                ?>
                                <span class="stock-badge <?php echo $clase; ?>">
                                    <?php echo $estado; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Formulario de edición -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-alt"></i> Información del Repuesto</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="formRepuesto">
                            <input type="hidden" name="action" value="editar">
                            
                            <div class="row">
                                <!-- Código -->
                                <div class="col-md-6 mb-3">
                                    <label for="codigo" class="form-label required">Código</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="codigo" 
                                           name="codigo" 
                                           value="<?php echo htmlspecialchars($repuesto['codigo']); ?>"
                                           required>
                                </div>
                                
                                <!-- Nombre -->
                                <div class="col-md-6 mb-3">
                                    <label for="nombre" class="form-label required">Nombre</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="nombre" 
                                           name="nombre" 
                                           value="<?php echo htmlspecialchars($repuesto['nombre']); ?>"
                                           required>
                                </div>
                            </div>
                            
                            <!-- Descripción -->
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción</label>
                                <textarea class="form-control" 
                                          id="descripcion" 
                                          name="descripcion" 
                                          rows="3"><?php echo htmlspecialchars($repuesto['descripcion']); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <!-- Marca -->
                                <div class="col-md-6 mb-3">
                                    <label for="id_marca" class="form-label">Marca</label>
                                    <select class="form-select" 
                                            id="id_marca" 
                                            name="id_marca">
                                        <option value="">Seleccione una marca</option>
                                    </select>
                                    <input type="hidden" id="marca" name="marca" value="<?php echo htmlspecialchars($repuesto['marca']); ?>">
                                </div>
                                
                                <!-- Modelo Compatible -->
                                <div class="col-md-6 mb-3">
                                    <label for="id_modelo" class="form-label">Modelo Compatible</label>
                                    <select class="form-select" 
                                            id="id_modelo" 
                                            name="id_modelo">
                                        <option value="">Seleccione primero una marca</option>
                                    </select>
                                    <input type="hidden" id="modelo_compatible" name="modelo_compatible" value="<?php echo htmlspecialchars($repuesto['modelo_compatible']); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <!-- Stock Mínimo -->
                                <div class="col-md-4 mb-3">
                                    <label for="stock_minimo" class="form-label required">Stock Mínimo</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="stock_minimo" 
                                           name="stock_minimo" 
                                           min="0"
                                           value="<?php echo $repuesto['stock_minimo']; ?>"
                                           required>
                                    <small class="text-muted">Nivel de alerta</small>
                                </div>
                                
                                <!-- Unidad de Medida -->
                                <div class="col-md-4 mb-3">
                                    <label for="unidad_medida" class="form-label">Unidad de Medida</label>
                                    <select class="form-select" id="unidad_medida" name="unidad_medida">
                                        <option value="Unidad" <?php echo ($repuesto['unidad_medida'] == 'Unidad') ? 'selected' : ''; ?>>Unidad</option>
                                        <option value="Kit" <?php echo ($repuesto['unidad_medida'] == 'Kit') ? 'selected' : ''; ?>>Kit</option>
                                        <option value="Caja" <?php echo ($repuesto['unidad_medida'] == 'Caja') ? 'selected' : ''; ?>>Caja</option>
                                        <option value="Metro" <?php echo ($repuesto['unidad_medida'] == 'Metro') ? 'selected' : ''; ?>>Metro</option>
                                        <option value="Paquete" <?php echo ($repuesto['unidad_medida'] == 'Paquete') ? 'selected' : ''; ?>>Paquete</option>
                                        <option value="Juego" <?php echo ($repuesto['unidad_medida'] == 'Juego') ? 'selected' : ''; ?>>Juego</option>
                                    </select>
                                </div>
                                
                                <!-- Precio Unitario -->
                                <div class="col-md-4 mb-3">
                                    <label for="precio_unitario" class="form-label">Precio Unitario (S/)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="precio_unitario" 
                                           name="precio_unitario" 
                                           step="0.01"
                                           min="0"
                                           value="<?php echo $repuesto['precio_unitario']; ?>">
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <!-- Botones -->
                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Columna derecha - Ajuste de Stock -->
            <div class="col-lg-4">
                <!-- Ajustar Stock -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-exchange-alt"></i> Ajustar Stock</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="formAjusteStock">
                            <input type="hidden" name="action" value="ajustar_stock">
                            
                            <!-- Tipo de Movimiento -->
                            <div class="mb-3">
                                <label for="tipo_movimiento" class="form-label required">Tipo de Movimiento</label>
                                <select class="form-select" id="tipo_movimiento" name="tipo_movimiento" required>
                                    <option value="entrada">Entrada (Aumentar)</option>
                                    <option value="salida">Salida (Reducir)</option>
                                    <option value="ajuste">Ajuste (Aumentar)</option>
                                </select>
                            </div>
                            
                            <!-- Cantidad -->
                            <div class="mb-3">
                                <label for="cantidad_ajuste" class="form-label required">Cantidad</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="cantidad_ajuste" 
                                       name="cantidad_ajuste" 
                                       min="1"
                                       value="1"
                                       required>
                            </div>
                            
                            <!-- Motivo -->
                            <div class="mb-3">
                                <label for="motivo" class="form-label">Motivo</label>
                                <textarea class="form-control" 
                                          id="motivo" 
                                          name="motivo" 
                                          rows="3"
                                          placeholder="Ej: Compra, Devolución, Corrección..."></textarea>
                            </div>
                            
                            <div class="alert alert-info small mb-3">
                                <i class="fas fa-info-circle"></i>
                                <strong>Stock actual:</strong> <?php echo $repuesto['stock_actual']; ?> <?php echo htmlspecialchars($repuesto['unidad_medida']); ?>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check"></i> Aplicar Ajuste
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Información adicional -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle"></i> Información</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <small class="text-muted">Registrado por:</small><br>
                                <strong><?php echo htmlspecialchars($repuesto['usuario_registro'] ?? 'N/A'); ?></strong>
                            </li>
                            <li class="mb-2">
                                <small class="text-muted">Fecha de registro:</small><br>
                                <strong>
                                    <?php 
                                    if ($repuesto['fecha_registro']) {
                                        echo date('d/m/Y H:i', strtotime($repuesto['fecha_registro']));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </strong>
                            </li>
                        </ul>
                        
                        <hr>
                        
                        <div class="d-grid gap-2">
                            <a href="ver.php?id=<?php echo $id; ?>" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-history"></i> Ver Historial Completo
                            </a>
                            <a href="movimientos.php?repuesto=<?php echo $id; ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-exchange-alt"></i> Ver Movimientos
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Cargar marcas y seleccionar la actual
            cargarMarcas();
            
            // Evento al cambiar marca
            $('#id_marca').on('change', function() {
                const idMarca = $(this).val();
                const marcaNombre = $(this).find('option:selected').text();
                
                $('#marca').val(marcaNombre !== 'Seleccione una marca' ? marcaNombre : '');
                $('#modelo_compatible').val('');
                
                if (idMarca) {
                    cargarModelos(idMarca);
                } else {
                    $('#id_modelo').prop('disabled', true).html('<option value="">Seleccione primero una marca</option>');
                }
            });
            
            // Evento al cambiar modelo
            $('#id_modelo').on('change', function() {
                const modeloNombre = $(this).find('option:selected').text();
                $('#modelo_compatible').val(modeloNombre !== 'Seleccione un modelo' ? modeloNombre : '');
            });
        });
        
        // Función para cargar marcas
        function cargarMarcas() {
            $.ajax({
                url: '<?php echo BASE_URL; ?>/controllers/repuestos.php?action=getMarcas',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let options = '<option value="">Seleccione una marca</option>';
                        let marcaActual = '<?php echo addslashes($repuesto['marca']); ?>';
                        let marcaSeleccionada = null;
                        
                        response.data.forEach(function(marca) {
                            let selected = marca.nombre === marcaActual ? 'selected' : '';
                            if (selected) marcaSeleccionada = marca.id;
                            options += `<option value="${marca.id}" ${selected}>${marca.nombre}</option>`;
                        });
                        $('#id_marca').html(options);
                        
                        // Si hay marca seleccionada, cargar sus modelos
                        if (marcaSeleccionada) {
                            cargarModelos(marcaSeleccionada, '<?php echo addslashes($repuesto['modelo_compatible']); ?>');
                        }
                    }
                },
                error: function() {
                    console.error('Error al cargar marcas');
                }
            });
        }
        
        // Función para cargar modelos por marca
        function cargarModelos(idMarca, modeloActual = null) {
            $.ajax({
                url: '<?php echo BASE_URL; ?>/controllers/repuestos.php?action=getModelos&id_marca=' + idMarca,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let options = '<option value="">Seleccione un modelo</option>';
                        response.data.forEach(function(modelo) {
                            let selected = modeloActual && modelo.nombre === modeloActual ? 'selected' : '';
                            options += `<option value="${modelo.id}" ${selected}>${modelo.nombre}</option>`;
                        });
                        $('#id_modelo').html(options).prop('disabled', false);
                    }
                },
                error: function() {
                    console.error('Error al cargar modelos');
                    $('#id_modelo').html('<option value="">Error al cargar modelos</option>');
                }
            });
        }
        
        // Validación del formulario de edición
        $('#formRepuesto').on('submit', function(e) {
            const codigo = $('#codigo').val().trim();
            const nombre = $('#nombre').val().trim();
            
            if (!codigo || !nombre) {
                e.preventDefault();
                alert('Por favor complete los campos obligatorios');
                return false;
            }
            
            return true;
        });
        
        // Validación del ajuste de stock
        $('#formAjusteStock').on('submit', function(e) {
            const tipo = $('#tipo_movimiento').val();
            const cantidad = parseInt($('#cantidad_ajuste').val());
            const stockActual = <?php echo $repuesto['stock_actual']; ?>;
            
            if (tipo === 'salida' && cantidad > stockActual) {
                e.preventDefault();
                alert('La cantidad a descontar no puede ser mayor al stock actual (' + stockActual + ')');
                return false;
            }
            
            return confirm('¿Está seguro de realizar este ajuste de stock?');
        });
    </script>
</body>
</html>
