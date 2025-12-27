<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/views/login.php');
    exit;
}

require_once __DIR__ . '/../../config/config.php';

$page_title = 'Importación Masiva';
$extra_css = '';
$extra_js = <<<EOD
<script>
// BASE_URL ya está declarado en header.php
let archivoSubido = false;

$(document).ready(function() {
    console.log('Módulo de importación cargado');
    console.log('BASE_URL:', BASE_URL);
    
    // Verificar elementos
    console.log('Select existe:', $('#tipoImportacion').length);
    console.log('Botón existe:', $('#btnDescargarPlantilla').length);
    
    // Configurar drag and drop
    const dropZone = $('#dropZone');
    
    dropZone.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('drag-over');
    });
    
    dropZone.on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-over');
    });
    
    dropZone.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-over');
        
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            $('#archivoExcel')[0].files = files;
            mostrarNombreArchivo(files[0]);
        }
    });
    
    // Click en zona para abrir selector
    dropZone.on('click', function() {
        $('#archivoExcel').click();
    });
    
    // Cambio en input file
    $('#archivoExcel').on('change', function() {
        if (this.files.length > 0) {
            mostrarNombreArchivo(this.files[0]);
        }
    });
    
    // Cambio de tipo de importación
    $('#tipoImportacion').on('change', function() {
        const tipo = $(this).val();
        console.log('Tipo seleccionado:', tipo);
        
        if (tipo) {
            console.log('Habilitando botón...');
            $('#btnDescargarPlantilla').prop('disabled', false);
        } else {
            console.log('Deshabilitando botón...');
            $('#btnDescargarPlantilla').prop('disabled', true);
        }
    });
    
    // Descargar plantilla
    $('#btnDescargarPlantilla').on('click', function() {
        const tipo = $('#tipoImportacion').val();
        if (!tipo) {
            Swal.fire('Error', 'Seleccione un tipo de importación', 'error');
            return;
        }
        
        window.location.href = BASE_URL + '/controllers/importacion.php?action=descargarPlantilla&tipo=' + tipo;
    });
    
    // Subir archivo
    $('#btnSubirArchivo').on('click', function() {
        subirArchivo();
    });
    
    // Procesar importación
    $('#btnProcesar').on('click', function() {
        procesarImportacion();
    });
    
    // Cancelar
    $('#btnCancelar').on('click', function() {
        resetearFormulario();
    });
});

function mostrarNombreArchivo(file) {
    const fileName = file.name;
    const fileSize = (file.size / 1024).toFixed(2);
    
    $('#fileName').text(fileName);
    $('#fileSize').text(fileSize + ' KB');
    $('#fileInfo').show();
    $('#btnSubirArchivo').prop('disabled', false);
}

function subirArchivo() {
    const tipo = $('#tipoImportacion').val();
    const archivo = $('#archivoExcel')[0].files[0];
    
    if (!tipo) {
        Swal.fire('Error', 'Seleccione un tipo de importación', 'error');
        return;
    }
    
    if (!archivo) {
        Swal.fire('Error', 'Seleccione un archivo Excel', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('archivo', archivo);
    formData.append('tipo', tipo);
    formData.append('action', 'subirArchivo');
    
    // Mostrar loading
    Swal.fire({
        title: 'Subiendo archivo...',
        text: 'Por favor espere',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    $.ajax({
        url: BASE_URL + '/controllers/importacion.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                archivoSubido = true;
                Swal.close();
                previsualizar();
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr.responseText);
            Swal.fire('Error', 'Error al subir el archivo', 'error');
        }
    });
}

function previsualizar() {
    $.ajax({
        url: BASE_URL + '/controllers/importacion.php',
        method: 'GET',
        data: { action: 'previsualizar' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                mostrarPreview(response.headers, response.preview, response.total);
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr.responseText);
            Swal.fire('Error', 'Error al previsualizar datos', 'error');
        }
    });
}

function mostrarPreview(headers, data, total) {
    let html = '<table class="table table-bordered table-sm preview-table">';
    
    // Headers
    html += '<thead><tr>';
    for (let key in headers) {
        html += '<th>' + headers[key] + '</th>';
    }
    html += '</tr></thead>';
    
    // Datos (máximo 10 filas)
    html += '<tbody>';
    data.forEach(function(fila) {
        html += '<tr>';
        for (let key in fila) {
            html += '<td>' + (fila[key] || '-') + '</td>';
        }
        html += '</tr>';
    });
    html += '</tbody>';
    html += '</table>';
    
    $('#previewTable').html(html);
    $('#totalRegistros').text(total);
    $('#previewSection').show();
    $('#btnProcesar').prop('disabled', false);
    $('#btnCancelar').prop('disabled', false);
    
    // Scroll hacia preview
    $('html, body').animate({
        scrollTop: $('#previewSection').offset().top - 100
    }, 500);
}

function procesarImportacion() {
    if (!archivoSubido) {
        Swal.fire('Error', 'Primero debe subir un archivo', 'error');
        return;
    }
    
    Swal.fire({
        title: '¿Procesar importación?',
        text: 'Se importarán los datos del archivo Excel',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, procesar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#28a745'
    }).then((result) => {
        if (result.isConfirmed) {
            ejecutarImportacion();
        }
    });
}

function ejecutarImportacion() {
    Swal.fire({
        title: 'Procesando...',
        text: 'Importando datos, por favor espere',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    $.ajax({
        url: BASE_URL + '/controllers/importacion.php',
        method: 'POST',
        data: { action: 'procesarImportacion' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let mensaje = `
                    <div class="resultado-importacion">
                        <p><strong>✅ Registros insertados:</strong> \${response.insertados}</p>
                        <p><strong>🔄 Registros actualizados:</strong> \${response.actualizados}</p>
                        <p><strong>📊 Total procesado:</strong> \${response.total_procesado}</p>
                `;
                
                if (response.errores && response.errores.length > 0) {
                    mensaje += `<p><strong>⚠️ Errores encontrados:</strong> \${response.errores.length}</p>`;
                    mensaje += '<div class="errores-lista"><ul>';
                    response.errores.forEach(function(error) {
                        mensaje += '<li>' + error + '</li>';
                    });
                    mensaje += '</ul></div>';
                }
                
                mensaje += '</div>';
                
                Swal.fire({
                    title: 'Importación Completada',
                    html: mensaje,
                    icon: 'success',
                    width: '600px',
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    resetearFormulario();
                });
            } else {
                Swal.fire('Error', response.error || response.message, 'error');
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr.responseText);
            Swal.fire('Error', 'Error al procesar la importación', 'error');
        }
    });
}

function resetearFormulario() {
    $('#tipoImportacion').val('');
    $('#archivoExcel').val('');
    $('#fileInfo').hide();
    $('#previewSection').hide();
    $('#btnSubirArchivo').prop('disabled', true);
    $('#btnDescargarPlantilla').prop('disabled', true);
    $('#btnProcesar').prop('disabled', true);
    $('#btnCancelar').prop('disabled', true);
    archivoSubido = false;
}
</script>
EOD;

include __DIR__ . '/../../includes/header.php';
?>

<style>
.content-card {
    background: var(--bg-card);
    border-radius: var(--border-radius);
    padding: 30px;
    box-shadow: var(--shadow);
    margin: 40px auto;
    max-width: 1200px;
}

.step-container {
    display: flex;
    justify-content: space-between;
    margin-bottom: 40px;
    flex-wrap: wrap;
}

.step {
    flex: 1;
    text-align: center;
    padding: 20px;
    position: relative;
}

.step::after {
    content: '→';
    position: absolute;
    right: -20px;
    top: 30px;
    font-size: 2rem;
    color: var(--primary-color);
}

.step:last-child::after {
    display: none;
}

.step-number {
    width: 50px;
    height: 50px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: bold;
    margin: 0 auto 10px;
}

.step h5 {
    color: var(--text-primary);
    margin-bottom: 5px;
}

.step p {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.form-section {
    margin-bottom: 30px;
}

.form-section h4 {
    color: var(--text-primary);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.drop-zone {
    border: 3px dashed var(--border-color);
    border-radius: var(--border-radius);
    padding: 60px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    background: var(--bg-secondary, rgba(0,0,0,0.02));
}

.drop-zone:hover {
    border-color: var(--primary-color);
    background: var(--primary-color-10, rgba(74, 144, 226, 0.1));
}

.drop-zone.drag-over {
    border-color: var(--primary-color);
    background: var(--primary-color-20, rgba(74, 144, 226, 0.2));
    transform: scale(1.02);
}

.drop-zone i {
    font-size: 4rem;
    color: var(--primary-color);
    margin-bottom: 20px;
}

.file-info {
    background: var(--bg-secondary, rgba(0,0,0,0.02));
    padding: 15px;
    border-radius: var(--border-radius);
    margin-top: 15px;
    display: none;
}

.file-info i {
    color: var(--success-color);
    margin-right: 10px;
}

.preview-section {
    display: none;
    margin-top: 30px;
}

.preview-table {
    max-height: 400px;
    overflow: auto;
    font-size: 0.9rem;
}

.preview-table th {
    background: var(--primary-color);
    color: white;
    position: sticky;
    top: 0;
    z-index: 10;
}

.action-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 30px;
}

.btn {
    padding: 12px 30px;
    border-radius: var(--border-radius);
    border: none;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-color-dark, #3a7bc8);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
}

.btn-success {
    background: var(--success-color);
    color: white;
}

.btn-success:hover {
    background: #218838;
    transform: translateY(-2px);
}

.btn-secondary {
    background: var(--text-muted);
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn:disabled:hover {
    transform: none;
    box-shadow: none;
}

.resultado-importacion {
    text-align: left;
}

.resultado-importacion p {
    margin: 10px 0;
    font-size: 1.1rem;
}

.errores-lista {
    max-height: 200px;
    overflow-y: auto;
    margin-top: 10px;
    padding: 10px;
    background: #fff3cd;
    border-radius: 5px;
}

.errores-lista ul {
    margin: 0;
    padding-left: 20px;
}

.errores-lista li {
    margin: 5px 0;
    color: #856404;
}

.info-box {
    background: #d1ecf1;
    border-left: 4px solid #0c5460;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.info-box i {
    color: #0c5460;
    margin-right: 10px;
}

@media (max-width: 768px) {
    .step-container {
        flex-direction: column;
    }
    
    .step::after {
        content: '↓';
        right: auto;
        top: auto;
        bottom: -30px;
        left: 50%;
        transform: translateX(-50%);
    }
    
    .action-buttons {
        flex-direction: column;
    }
}
</style>

<div class="content-card">
    <h3 style="margin-bottom: 10px;">
        <i class="fas fa-file-excel"></i> Importación Masiva desde Excel
    </h3>
    <p style="color: var(--text-secondary); margin-bottom: 30px;">
        Importe equipos, marcas y modelos de forma masiva desde archivos Excel
    </p>
    
    <div class="info-box">
        <i class="fas fa-info-circle"></i>
        <strong>Instrucciones:</strong> Descargue la plantilla correspondiente, complete los datos y súbala para importar.
        Los registros existentes se actualizarán automáticamente.
    </div>
    
    <!-- Pasos -->
    <div class="step-container">
        <div class="step">
            <div class="step-number">1</div>
            <h5>Seleccione Tipo</h5>
            <p>Elija qué desea importar</p>
        </div>
        <div class="step">
            <div class="step-number">2</div>
            <h5>Descargue Plantilla</h5>
            <p>Obtenga el formato correcto</p>
        </div>
        <div class="step">
            <div class="step-number">3</div>
            <h5>Suba Archivo</h5>
            <p>Arrastre o seleccione el Excel</p>
        </div>
        <div class="step">
            <div class="step-number">4</div>
            <h5>Procese</h5>
            <p>Importe los datos</p>
        </div>
    </div>
    
    <!-- Formulario -->
    <div class="form-section">
        <h4><i class="fas fa-cog"></i> Configuración de Importación</h4>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                    Tipo de Importación *
                </label>
                <select id="tipoImportacion" class="form-control" style="width: 100%; padding: 10px;">
                    <option value="">Seleccione...</option>
                    <option value="marcas">📦 Marcas</option>
                    <option value="modelos">🔧 Modelos</option>
                    <option value="equipos">🖨️ Equipos</option>
                </select>
            </div>
            
            <div style="display: flex; align-items: flex-end;">
                <button id="btnDescargarPlantilla" class="btn btn-primary" disabled>
                    <i class="fas fa-download"></i> Descargar Plantilla
                </button>
            </div>
        </div>
    </div>
    
    <!-- Zona de Drop -->
    <div class="form-section">
        <h4><i class="fas fa-upload"></i> Subir Archivo Excel</h4>
        
        <div id="dropZone" class="drop-zone">
            <i class="fas fa-cloud-upload-alt"></i>
            <h4>Arrastre su archivo aquí o haga clic para seleccionar</h4>
            <p>Formatos aceptados: .xlsx, .xls (Máximo 5MB)</p>
            <input type="file" id="archivoExcel" accept=".xlsx,.xls" style="display: none;">
        </div>
        
        <div id="fileInfo" class="file-info">
            <i class="fas fa-file-excel fa-2x"></i>
            <strong id="fileName"></strong> (<span id="fileSize"></span>)
        </div>
        
        <div class="action-buttons">
            <button id="btnSubirArchivo" class="btn btn-primary" disabled>
                <i class="fas fa-upload"></i> Subir y Previsualizar
            </button>
        </div>
    </div>
    
    <!-- Preview -->
    <div id="previewSection" class="preview-section">
        <h4>
            <i class="fas fa-eye"></i> Vista Previa 
            <span style="font-size: 0.9rem; color: var(--text-secondary);">
                (Mostrando primeras 10 filas de <span id="totalRegistros">0</span> totales)
            </span>
        </h4>
        
        <div id="previewTable" style="overflow-x: auto;"></div>
        
        <div class="action-buttons">
            <button id="btnProcesar" class="btn btn-success" disabled>
                <i class="fas fa-check-circle"></i> Procesar Importación
            </button>
            <button id="btnCancelar" class="btn btn-secondary" disabled>
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
