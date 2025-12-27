<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Equipo.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn()) {
    redirect('controllers/auth.php');
}

$page_title = 'Gestión de Equipos';

$database = new Database();
$db = $database->getConnection();
$equipoModel = new Equipo($db);

// Obtener datos para los selects
$stmt = $db->query("SELECT * FROM estados_equipo WHERE activo = 1");
$estados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT * FROM marcas WHERE activo = 1 ORDER BY nombre");
$marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT * FROM modelos WHERE activo = 1 ORDER BY nombre");
$modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT * FROM distritos_fiscales WHERE activo = 1");
$distritos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT * FROM sedes WHERE activo = 1");
$sedes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT * FROM macro_procesos WHERE activo = 1");
$macroProcesos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT * FROM despachos WHERE activo = 1");
$despachos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT * FROM usuarios_finales WHERE activo = 1");
$usuariosFinales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$equipos = $equipoModel->getAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-print"></i> Gestión de Equipos</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEquipo">
        <i class="fas fa-plus"></i> Nuevo Equipo
    </button>
</div>

<!-- Filtros -->
<div class="content-card mb-4">
    <div class="row">
        <div class="col-md-3">
            <label class="form-label">Buscar</label>
            <input type="text" class="form-control" id="searchInput" placeholder="Código, marca o modelo...">
        </div>
        <div class="col-md-2">
            <label class="form-label">Clasificación</label>
            <select class="form-select" id="filterClasificacion">
                <option value="">Todas</option>
                <option value="impresora">Impresora</option>
                <option value="multifuncional">Multifuncional</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Estado</label>
            <select class="form-select" id="filterEstado">
                <option value="">Todos</option>
                <?php foreach ($estados as $estado): ?>
                <option value="<?php echo $estado['id']; ?>"><?php echo htmlspecialchars($estado['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Sede</label>
            <select class="form-select" id="filterSede">
                <option value="">Todas</option>
                <?php foreach ($sedes as $sede): ?>
                <option value="<?php echo $sede['id']; ?>"><?php echo htmlspecialchars($sede['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-secondary w-100" onclick="limpiarFiltros()">
                <i class="fas fa-times"></i> Limpiar
            </button>
        </div>
    </div>
</div>

<!-- Tabla de equipos -->
<div class="content-card">
    <div class="table-responsive">
        <table class="table table-hover data-table" id="tablaEquipos">
            <thead>
                <tr>
                    <th>Imagen</th>
                    <th>Código</th>
                    <th>Clasificación</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Serie</th>
                    <th>Estado</th>
                    <th>Sede</th>
                    <th>Ubicación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($equipos as $equipo): ?>
                <tr>
                    <td>
                        <?php if (!empty($equipo['imagen'])): ?>
                            <img src="<?php echo BASE_URL . '/' . $equipo['imagen']; ?>" 
                                 alt="Equipo" 
                                 class="rounded" 
                                 style="width: 50px; height: 50px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-secondary rounded d-flex align-items-center justify-content-center" 
                                 style="width: 50px; height: 50px;">
                                <i class="fas fa-print text-white"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo htmlspecialchars($equipo['codigo_patrimonial']); ?></strong></td>
                    <td>
                        <span class="badge bg-<?php echo $equipo['clasificacion'] == 'impresora' ? 'primary' : 'info'; ?>">
                            <?php echo ucfirst($equipo['clasificacion']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($equipo['marca']); ?></td>
                    <td><?php echo htmlspecialchars($equipo['modelo']); ?></td>
                    <td><?php echo $equipo['numero_serie'] ? htmlspecialchars($equipo['numero_serie']) : '-'; ?></td>
                    <td>
                        <?php if (!empty($equipo['estado_color'])): ?>
                            <span class="badge" style="background-color: <?php echo $equipo['estado_color']; ?>">
                                <?php echo htmlspecialchars($equipo['estado'] ?? 'Sin estado'); ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($equipo['estado'] ?? 'Sin estado'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $equipo['sede_nombre'] ? htmlspecialchars($equipo['sede_nombre']) : '-'; ?></td>
                    <td>
                        <?php if (!empty($equipo['ubicacion_fisica'])): ?>
                            <small>
                                <i class="fas fa-map-marker-alt me-1 text-primary"></i>
                                <?php echo htmlspecialchars($equipo['ubicacion_fisica']); ?>
                            </small>
                        <?php else: ?>
                            <small class="text-muted">-</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-info" 
                                    onclick="verEquipo(<?php echo $equipo['id']; ?>)"
                                    title="Ver detalles">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-warning" 
                                    onclick="editarEquipo(<?php echo $equipo['id']; ?>)"
                                    title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" 
                                    onclick="eliminarEquipo(<?php echo $equipo['id']; ?>)"
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

<!-- Modal Crear/Editar Equipo -->
<div class="modal fade" id="modalEquipo" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEquipoLabel">
                    <i class="fas fa-print"></i> <span id="modalTitle">Nuevo Equipo</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEquipo" method="POST" action="<?php echo BASE_URL; ?>/controllers/equipos.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create" id="formAction">
                <input type="hidden" name="id" id="equipoId">
                
                <div class="modal-body">
                    <div class="row">
                        <!-- Información Básica -->
                        <div class="col-12"><h6 class="border-bottom pb-2 mb-3">Información Básica</h6></div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="codigo_patrimonial" class="form-label">Código Patrimonial *</label>
                            <input type="text" class="form-control" id="codigo_patrimonial" name="codigo_patrimonial" required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="clasificacion" class="form-label">Clasificación *</label>
                            <select class="form-select" id="clasificacion" name="clasificacion" required>
                                <option value="">Seleccione...</option>
                                <option value="impresora">Impresora</option>
                                <option value="multifuncional">Multifuncional</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="id_marca" class="form-label">Marca *</label>
                            <select class="form-select" id="id_marca" name="id_marca" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($marcas as $marca): ?>
                                <option value="<?php echo $marca['id']; ?>"><?php echo htmlspecialchars($marca['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Si no encuentra la marca, <a href="<?php echo BASE_URL; ?>/views/configuracion/index.php" target="_blank">créela aquí</a></small>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="id_modelo" class="form-label">Modelo *</label>
                            <select class="form-select" id="id_modelo" name="id_modelo" required>
                                <option value="">Seleccione primero una marca...</option>
                            </select>
                            <small class="text-muted">Si no encuentra el modelo, <a href="<?php echo BASE_URL; ?>/views/configuracion/index.php" target="_blank">créelo aquí</a></small>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="numero_serie" class="form-label">Número de Serie</label>
                            <input type="text" class="form-control" id="numero_serie" name="numero_serie">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="id_estado" class="form-label">Estado *</label>
                            <select class="form-select" id="id_estado" name="id_estado" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($estados as $estado): ?>
                                <option value="<?php echo $estado['id']; ?>"><?php echo htmlspecialchars($estado['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="garantia" class="form-label">Garantía</label>
                            <input type="text" class="form-control" id="garantia" name="garantia" placeholder="Ej: 12 meses">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="anio_adquisicion" class="form-label">Año de Adquisición</label>
                            <input type="number" class="form-control" id="anio_adquisicion" name="anio_adquisicion" min="1900" max="2099">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label d-block">Estabilizador</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="tiene_estabilizador" name="tiene_estabilizador" value="1">
                                <label class="form-check-label" for="tiene_estabilizador">Sí</label>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="imagen" class="form-label">Imagen del Equipo</label>
                            <input type="file" class="form-control" id="imagen" name="imagen" accept="image/*">
                            <small class="text-muted">Max 5MB (JPG, PNG, GIF)</small>
                        </div>

                        <!-- Ubicación -->
                        <div class="col-12 mt-3"><h6 class="border-bottom pb-2 mb-3">Ubicación</h6></div>

                        <div class="col-md-4 mb-3">
                            <label for="id_distrito" class="form-label">Distrito Fiscal</label>
                            <select class="form-select" id="id_distrito" name="id_distrito">
                                <option value="">Seleccione...</option>
                                <?php foreach ($distritos as $distrito): ?>
                                <option value="<?php echo $distrito['id']; ?>"><?php echo htmlspecialchars($distrito['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="id_sede" class="form-label">Sede</label>
                            <select class="form-select" id="id_sede" name="id_sede">
                                <option value="">Seleccione...</option>
                                <?php foreach ($sedes as $sede): ?>
                                <option value="<?php echo $sede['id']; ?>"><?php echo htmlspecialchars($sede['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="id_macro_proceso" class="form-label">Macro Proceso</label>
                            <select class="form-select" id="id_macro_proceso" name="id_macro_proceso">
                                <option value="">Seleccione...</option>
                                <?php foreach ($macroProcesos as $mp): ?>
                                <option value="<?php echo $mp['id']; ?>"><?php echo htmlspecialchars($mp['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="id_despacho" class="form-label">Despacho</label>
                            <select class="form-select" id="id_despacho" name="id_despacho">
                                <option value="">Seleccione...</option>
                                <?php foreach ($despachos as $despacho): ?>
                                <option value="<?php echo $despacho['id']; ?>"><?php echo htmlspecialchars($despacho['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="id_usuario_final" class="form-label">Usuario Final</label>
                            <select class="form-select" id="id_usuario_final" name="id_usuario_final">
                                <option value="">Seleccione...</option>
                                <?php foreach ($usuariosFinales as $uf): ?>
                                <option value="<?php echo $uf['id']; ?>"><?php echo htmlspecialchars($uf['nombre_completo']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="ubicacion_fisica" class="form-label">Ubicación Física</label>
                            <input type="text" class="form-control" id="ubicacion_fisica" name="ubicacion_fisica" placeholder="Ej: Piso 2, Oficina 201">
                        </div>

                        <!-- Observaciones -->
                        <div class="col-12 mt-3"><h6 class="border-bottom pb-2 mb-3">Observaciones</h6></div>

                        <div class="col-12 mb-3">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ver Detalles del Equipo -->
<div class="modal fade" id="modalVerEquipo" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle"></i> Detalles del Equipo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="detallesEquipo"></div>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = <<<'EOT'
<script>
// Asegurarse de que jQuery esté cargado
if (typeof jQuery === 'undefined') {
    console.error('jQuery no está cargado!');
} else {
    console.log('jQuery versión:', jQuery.fn.jquery);
}

$(document).ready(function() {
    console.log('=== INICIO DEBUG MODAL EQUIPOS ===');
    console.log('Selector #id_marca existe:', $('#id_marca').length > 0);
    console.log('Selector #id_modelo existe:', $('#id_modelo').length > 0);
    
    // Inicializar DataTable con botones de exportación
    $('#tablaEquipos').DataTable({
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12 col-md-12"B>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        buttons: [
            {
                extend: 'copy',
                text: '<i class="fas fa-copy"></i> Copiar',
                className: 'btn btn-secondary btn-sm',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-success btn-sm',
                title: 'Listado de Equipos',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-danger btn-sm',
                title: 'Listado de Equipos',
                orientation: 'landscape',
                pageSize: 'LEGAL',
                exportOptions: {
                    columns: ':visible'
                },
                customize: function(doc) {
                    doc.content[1].table.widths = Array(doc.content[1].table.body[0].length + 1).join('*').split('');
                    doc.styles.tableHeader.fillColor = '#4a90e2';
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Imprimir',
                className: 'btn btn-info btn-sm',
                title: 'Listado de Equipos',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'colvis',
                text: '<i class="fas fa-columns"></i> Columnas',
                className: 'btn btn-warning btn-sm'
            }
        ],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json',
            buttons: {
                copy: 'Copiar',
                excel: 'Excel',
                pdf: 'PDF',
                print: 'Imprimir',
                colvis: 'Visibilidad de columnas',
                copyTitle: 'Copiado al portapapeles',
                copySuccess: {
                    _: '%d filas copiadas',
                    1: '1 fila copiada'
                }
            }
        },
        pageLength: 25,
        order: [[0, 'asc']]
    });

    // Cargar modelos cuando se selecciona una marca
    $('#id_marca').on('change', function() {
        const id_marca = $(this).val();
        const $modeloSelect = $('#id_modelo');
        
        console.log('=== MARCA SELECCIONADA (MODAL) ===');
        console.log('ID Marca:', id_marca);
        console.log('Tipo:', typeof id_marca);
        
        if (!id_marca || id_marca === '') {
            console.log('Marca vacía, reseteando modelos');
            $modeloSelect.html('<option value="">Seleccione primero una marca...</option>');
            return;
        }
        
        $modeloSelect.html('<option value="">Cargando...</option>');
        
        const url = BASE_URL + '/controllers/equipos.php';
        const params = {
            action: 'getModelos',
            id_marca: id_marca
        };
        
        console.log('URL:', url);
        console.log('Parámetros:', params);
        console.log('URL completa:', url + '?' + $.param(params));
        
        $.ajax({
            url: url,
            method: 'GET',
            data: params,
            dataType: 'json',
            beforeSend: function() {
                console.log('Enviando petición AJAX...');
            },
            success: function(data) {
                console.log('=== RESPUESTA EXITOSA (MODAL) ===');
                console.log('Tipo de respuesta:', typeof data);
                console.log('Es array:', Array.isArray(data));
                console.log('Cantidad de modelos:', Array.isArray(data) ? data.length : 'N/A');
                console.log('Datos completos:', data);
                
                let options = '<option value="">Seleccione un modelo...</option>';
                
                if (Array.isArray(data) && data.length > 0) {
                    data.forEach(function(modelo) {
                        console.log('Agregando modelo:', modelo.id, modelo.nombre);
                        options += `<option value="${modelo.id}">${modelo.nombre}</option>`;
                    });
                    console.log('Total opciones generadas:', data.length + 1);
                } else {
                    console.warn('No hay modelos disponibles');
                    options = '<option value="">No hay modelos disponibles</option>';
                }
                
                $modeloSelect.html(options);
                console.log('Opciones HTML actualizadas');
            },
            error: function(xhr, status, error) {
                console.error('=== ERROR EN PETICIÓN (MODAL) ===');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('HTTP Status:', xhr.status);
                console.error('Respuesta del servidor:', xhr.responseText);
                console.error('Headers:', xhr.getAllResponseHeaders());
                
                $modeloSelect.html('<option value="">Error al cargar modelos</option>');
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error al cargar modelos',
                    html: `<p><strong>Status:</strong> ${status}</p>
                           <p><strong>Error:</strong> ${error}</p>
                           <p><strong>HTTP:</strong> ${xhr.status}</p>
                           <p>Ver consola para más detalles</p>`,
                    confirmButtonColor: '#dc3545'
                });
            },
            complete: function() {
                console.log('=== PETICIÓN COMPLETADA (MODAL) ===');
            }
        });
    });
    
    console.log('Event handler registrado para #id_marca (modal)');


    // Resetear formulario al cerrar modal
    $('#modalEquipo').on('hidden.bs.modal', function () {
        $('#formEquipo')[0].reset();
        $('#formAction').val('create');
        $('#equipoId').val('');
        $('#modalTitle').text('Nuevo Equipo');
        $('#id_modelo').html('<option value="">Seleccione primero una marca...</option>');
    });
});

function limpiarFiltros() {
    $('#searchInput').val('');
    $('#filterClasificacion').val('');
    $('#filterEstado').val('');
    $('#filterSede').val('');
}

function editarEquipo(id) {
    $.ajax({
        url: BASE_URL + '/controllers/equipos.php',
        method: 'GET',
        data: {
            action: 'get',
            id: id
        },
        dataType: 'json',
        success: function(equipo) {
            $('#modalTitle').text('Editar Equipo');
            $('#formAction').val('update');
            $('#equipoId').val(equipo.id);
        $('#codigo_patrimonial').val(equipo.codigo_patrimonial);
        $('#clasificacion').val(equipo.clasificacion);
        
        // Cargar marca y modelo
        if (equipo.id_marca) {
            $('#id_marca').val(equipo.id_marca);
            
            // Cargar modelos de la marca y seleccionar el modelo actual
            $.get(BASE_URL + '/controllers/equipos.php', {
                action: 'getModelos',
                id_marca: equipo.id_marca
            }, function(modelos) {
                let options = '<option value="">Seleccione un modelo...</option>';
                modelos.forEach(function(modelo) {
                    const selected = modelo.id == equipo.id_modelo ? 'selected' : '';
                    options += `<option value="${modelo.id}" ${selected}>${modelo.nombre}</option>`;
                });
                $('#id_modelo').html(options);
            }, 'json');
        }
        
        $('#numero_serie').val(equipo.numero_serie);
        $('#id_estado').val(equipo.id_estado);
        $('#garantia').val(equipo.garantia);
        $('#anio_adquisicion').val(equipo.anio_adquisicion);
        $('#tiene_estabilizador').prop('checked', equipo.tiene_estabilizador == 1);
        $('#id_distrito').val(equipo.id_distrito);
        $('#id_sede').val(equipo.id_sede);
        $('#id_macro_proceso').val(equipo.id_macro_proceso);
        $('#id_despacho').val(equipo.id_despacho);
        $('#id_usuario_final').val(equipo.id_usuario_final);
        $('#ubicacion_fisica').val(equipo.ubicacion_fisica);
        $('#observaciones').val(equipo.observaciones);
        
        $('#modalEquipo').modal('show');
        },
        error: function(xhr, status, error) {
            console.error('Error al cargar equipo:', xhr, status, error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo cargar la información del equipo'
            });
        }
    });
}

function verEquipo(id) {
    $.ajax({
        url: BASE_URL + '/controllers/equipos.php',
        method: 'GET',
        data: {
            action: 'get',
            id: id
        },
        dataType: 'json',
        success: function(equipo) {
            const imagenHtml = equipo.imagen ? 
                `<img src="${BASE_URL}/${equipo.imagen}" class="img-fluid rounded" style="max-height: 400px; object-fit: contain;">` :
                `<div class="text-center p-5 bg-light rounded"><i class="fas fa-print fa-5x text-muted"></i><p class="mt-3 text-muted">Sin imagen</p></div>`;
        
        const html = `
            <div class="row">
                <div class="col-md-5">
                    <div class="border rounded p-3 mb-3 equipo-detail-card">
                        ${imagenHtml}
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3 equipo-detail-card">
                                <strong class="text-muted d-block mb-1">Código Patrimonial</strong>
                                <span class="fs-6">${equipo.codigo_patrimonial}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 equipo-detail-card">
                                <strong class="text-muted d-block mb-1">Número de Serie</strong>
                                <span class="fs-6">${equipo.numero_serie || '-'}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 equipo-detail-card">
                                <strong class="text-muted d-block mb-1">Clasificación</strong>
                                <span class="fs-6">${equipo.clasificacion.charAt(0).toUpperCase() + equipo.clasificacion.slice(1)}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 equipo-detail-card">
                                <strong class="text-muted d-block mb-1">Marca</strong>
                                <span class="fs-6">${equipo.marca}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 equipo-detail-card">
                                <strong class="text-muted d-block mb-1">Modelo</strong>
                                <span class="fs-6">${equipo.modelo}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 equipo-detail-card">
                                <strong class="text-muted d-block mb-1">Estado</strong>
                                <span class="badge bg-success">${equipo.estado || 'Sin estado'}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 equipo-detail-card">
                                <strong class="text-muted d-block mb-1">Año de Adquisición</strong>
                                <span class="fs-6">${equipo.anio_adquisicion || '-'}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 equipo-detail-card">
                                <strong class="text-muted d-block mb-1">Garantía</strong>
                                <span class="fs-6">${equipo.garantia || '-'}</span>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="border rounded p-3 equipo-detail-card">
                                <strong class="text-muted d-block mb-1">Estabilizador</strong>
                                <span class="fs-6">${equipo.tiene_estabilizador ? 'Sí' : 'No'}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            ${equipo.observaciones ? `
            <div class="row mt-3">
                <div class="col-12">
                    <div class="border rounded p-3 equipo-detail-card">
                        <strong class="text-muted d-block mb-2">Observaciones</strong>
                        <p class="mb-0">${equipo.observaciones}</p>
                    </div>
                </div>
            </div>
            ` : ''}
            <div class="row mt-4">
                <div class="col-12">
                    <h6 class="border-bottom pb-2 mb-3">
                        <i class="fas fa-history"></i> Historial de Auditoría
                    </h6>
                    <div id="historialAuditoria">
                        <div class="text-center py-3">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="text-muted mt-2 mb-0">Cargando historial...</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#detallesEquipo').html(html);
        $('#modalVerEquipo').modal('show');
        
        // Cargar historial de auditoría
        cargarAuditoria(id);
        },
        error: function(xhr, status, error) {
            console.error('Error al cargar equipo:', xhr, status, error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo cargar la información del equipo'
            });
        }
    });
}

function cargarAuditoria(id_equipo) {
    $.get(BASE_URL + '/controllers/equipos.php', {
        action: 'getAuditoria',
        id: id_equipo
    }, function(auditoria) {
        console.log('Auditoría recibida:', auditoria);
        
        let html = '';
        
        if (!auditoria || auditoria.length === 0) {
            html = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No hay registros de auditoría para este equipo.
                </div>
            `;
        } else {
            html = '<div class="timeline">';
            
            auditoria.forEach(function(registro, index) {
                const fecha = new Date(registro.fecha_hora);
                const fechaFormateada = fecha.toLocaleDateString('es-PE', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                const horaFormateada = fecha.toLocaleTimeString('es-PE', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                
                // Determinar icono y color según la acción
                let icono = 'fa-edit';
                let colorBadge = 'bg-info';
                let colorIcono = 'text-info';
                
                switch(registro.accion) {
                    case 'CREAR':
                        icono = 'fa-plus-circle';
                        colorBadge = 'bg-success';
                        colorIcono = 'text-success';
                        break;
                    case 'MODIFICAR':
                        icono = 'fa-edit';
                        colorBadge = 'bg-primary';
                        colorIcono = 'text-primary';
                        break;
                    case 'ELIMINAR':
                        icono = 'fa-trash';
                        colorBadge = 'bg-danger';
                        colorIcono = 'text-danger';
                        break;
                    case 'CAMBIO_ESTADO':
                        icono = 'fa-exchange-alt';
                        colorBadge = 'bg-warning';
                        colorIcono = 'text-warning';
                        break;
                }
                
                // Usar el nombre completo del usuario o el nombre guardado en la auditoría
                const usuarioNombre = registro.usuario_nombre_completo || registro.usuario_nombre || 'Sistema';
                
                html += `
                    <div class="timeline-item">
                        <div class="timeline-marker ${colorIcono}">
                            <i class="fas ${icono}"></i>
                        </div>
                        <div class="timeline-content equipo-detail-card border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge ${colorBadge} me-2">${registro.accion}</span>
                                    <strong>${usuarioNombre}</strong>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-calendar-alt me-1"></i>${fechaFormateada}
                                    <i class="fas fa-clock ms-2 me-1"></i>${horaFormateada}
                                </small>
                            </div>
                            <p class="mb-0">${registro.descripcion}</p>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        }
        
        $('#historialAuditoria').html(html);
    }, 'json').fail(function(xhr, status, error) {
        console.error('Error al cargar auditoría:', xhr, status, error);
        console.error('Respuesta del servidor:', xhr.responseText);
        
        $('#historialAuditoria').html(`
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> Error al cargar el historial de auditoría.
                <br><small>${xhr.responseText || 'Error desconocido'}</small>
            </div>
        `);
    });
}

function eliminarEquipo(id) {
    Swal.fire({
        icon: 'warning',
        title: '¿Está seguro?',
        text: '¿Desea eliminar este equipo? Esta acción no se puede deshacer.',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: BASE_URL + '/controllers/equipos.php',
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
                            text: response.message || 'Equipo eliminado exitosamente',
                            confirmButtonColor: '#198754'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.error || 'Error al eliminar el equipo',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'Error al eliminar el equipo';
                    try {
                        const error = xhr.responseJSON;
                        errorMsg = error.error || errorMsg;
                    } catch(e) {
                        console.error('Error al parsear respuesta:', e);
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: errorMsg,
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
        }
    });
}
</script>
EOT;
?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
