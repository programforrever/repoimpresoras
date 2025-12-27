<?php
/**
 * Vista para crear nuevo repuesto
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

$page_title = "Nuevo Repuesto - " . APP_NAME;
$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../models/Repuesto.php';
    
    $database = new Database();
    $db = $database->getConnection();
    $repuestoModel = new Repuesto($db);
    
    try {
        // Validar código único
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
            // Registrar auditoría
            registrarAuditoria(
                $db,
                'repuestos',
                $id,
                'create',
                null,
                json_encode($data)
            );
            
            $_SESSION['mensaje'] = 'Repuesto registrado exitosamente';
            $_SESSION['tipo_mensaje'] = 'success';
            header('Location: index.php');
            exit;
        } else {
            throw new Exception("Error al registrar el repuesto");
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
                <li class="breadcrumb-item active">Nuevo Repuesto</li>
            </ol>
        </nav>
        
        <!-- Título -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h2><i class="fas fa-plus-circle"></i> Nuevo Repuesto</h2>
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
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="" id="formRepuesto">
                            <div class="row">
                                <!-- Código -->
                                <div class="col-md-6 mb-3">
                                    <label for="codigo" class="form-label required">Código</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="codigo" 
                                           name="codigo" 
                                           required
                                           placeholder="Ej: REP-001">
                                    <small class="text-muted">Código único identificador</small>
                                </div>
                                
                                <!-- Nombre -->
                                <div class="col-md-6 mb-3">
                                    <label for="nombre" class="form-label required">Nombre</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="nombre" 
                                           name="nombre" 
                                           required
                                           placeholder="Ej: Tóner Negro">
                                </div>
                            </div>
                            
                            <!-- Descripción -->
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción</label>
                                <textarea class="form-control" 
                                          id="descripcion" 
                                          name="descripcion" 
                                          rows="3"
                                          placeholder="Descripción detallada del repuesto"></textarea>
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
                                    <input type="hidden" id="marca" name="marca">
                                </div>
                                
                                <!-- Modelo Compatible -->
                                <div class="col-md-6 mb-3">
                                    <label for="id_modelo" class="form-label">Modelo Compatible</label>
                                    <select class="form-select" 
                                            id="id_modelo" 
                                            name="id_modelo"
                                            disabled>
                                        <option value="">Seleccione primero una marca</option>
                                    </select>
                                    <input type="hidden" id="modelo_compatible" name="modelo_compatible">
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
                                           value="0"
                                           required>
                                    <small class="text-muted">Nivel de alerta</small>
                                </div>
                                
                                <!-- Stock Actual -->
                                <div class="col-md-4 mb-3">
                                    <label for="stock_actual" class="form-label required">Stock Actual</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="stock_actual" 
                                           name="stock_actual" 
                                           min="0"
                                           value="0"
                                           required>
                                </div>
                                
                                <!-- Unidad de Medida -->
                                <div class="col-md-4 mb-3">
                                    <label for="unidad_medida" class="form-label">Unidad de Medida</label>
                                    <select class="form-select" id="unidad_medida" name="unidad_medida">
                                        <option value="Unidad" selected>Unidad</option>
                                        <option value="Kit">Kit</option>
                                        <option value="Caja">Caja</option>
                                        <option value="Metro">Metro</option>
                                        <option value="Paquete">Paquete</option>
                                        <option value="Juego">Juego</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Precio Unitario -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="precio_unitario" class="form-label">Precio Unitario (S/)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="precio_unitario" 
                                           name="precio_unitario" 
                                           step="0.01"
                                           min="0"
                                           value="0.00">
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <!-- Botones -->
                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Repuesto
                                </button>
                            </div>
                        </form>
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
        // Validación del formulario
        $(document).ready(function() {
            // Cargar marcas al inicio
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
            
            // Generar código automático (opcional)
            $('#nombre').on('blur', function() {
                const nombre = $(this).val();
                const codigo = $('#codigo').val();
                
                if (!codigo && nombre) {
                    // Generar código sugerido
                    const palabras = nombre.split(' ');
                    let sugerencia = 'REP-';
                    palabras.slice(0, 2).forEach(palabra => {
                        sugerencia += palabra.substring(0, 3).toUpperCase();
                    });
                    $('#codigo').attr('placeholder', 'Sugerido: ' + sugerencia);
                }
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
                        response.data.forEach(function(marca) {
                            options += `<option value="${marca.id}">${marca.nombre}</option>`;
                        });
                        $('#id_marca').html(options);
                    }
                },
                error: function() {
                    console.error('Error al cargar marcas');
                }
            });
        }
        
        // Función para cargar modelos por marca
        function cargarModelos(idMarca) {
            $.ajax({
                url: '<?php echo BASE_URL; ?>/controllers/repuestos.php?action=getModelos&id_marca=' + idMarca,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let options = '<option value="">Seleccione un modelo</option>';
                        response.data.forEach(function(modelo) {
                            options += `<option value="${modelo.id}">${modelo.nombre}</option>`;
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
    </script>
</body>
</html>
