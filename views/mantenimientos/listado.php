<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/views/login.php');
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Mantenimiento.php';

$database = new Database();
$db = $database->getConnection();
$mantenimientoModel = new Mantenimiento($db);

// Obtener TODOS los mantenimientos
$mantenimientos = $mantenimientoModel->getAll();

$page_title = 'Listado de Mantenimientos';
$extra_css = '';
$extra_js = <<<EOD
<script>
$(document).ready(function() {
    // Inicializar DataTable con botones de exportación
    $('#tablaMantenimientos').DataTable({
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
                    columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
                }
            },
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-success btn-sm',
                title: 'Listado de Mantenimientos',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
                }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-danger btn-sm',
                title: 'Listado de Mantenimientos',
                orientation: 'landscape',
                pageSize: 'LEGAL',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
                },
                customize: function(doc) {
                    doc.content[1].table.widths = Array(doc.content[1].table.body[0].length + 1).join('*').split('');
                    doc.styles.tableHeader.fillColor = '#4a90e2';
                    doc.styles.tableHeader.fontSize = 9;
                    doc.defaultStyle.fontSize = 8;
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Imprimir',
                className: 'btn btn-info btn-sm',
                title: 'Listado de Mantenimientos',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
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
                colvis: 'Columnas visibles',
                copyTitle: 'Copiado al portapapeles',
                copySuccess: {
                    _: '%d filas copiadas',
                    1: '1 fila copiada'
                }
            }
        },
        pageLength: 50,
        order: [[0, 'desc']],
        responsive: true
    });
});
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
    max-width: 1400px;
}

.dt-buttons {
    margin-bottom: 15px;
}

.dt-buttons .btn {
    margin-right: 5px;
    margin-bottom: 5px;
}

.badge {
    padding: 6px 12px;
    border-radius: 5px;
    font-size: 0.85rem;
    font-weight: 500;
}

.badge-preventivo {
    background-color: #28a745;
    color: white;
}

.badge-correctivo {
    background-color: #ffc107;
    color: #333;
}

.badge-emergencia {
    background-color: #dc3545;
    color: white;
}

.badge-repuestos {
    background-color: #17a2b8;
    color: white;
}

.table-responsive {
    overflow-x: auto;
}

.btn-back {
    background: var(--text-muted);
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.btn-back:hover {
    background: #5a6268;
    color: white;
    transform: translateY(-2px);
}
</style>

<div class="content-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h3><i class="fas fa-list"></i> Listado Completo de Mantenimientos</h3>
            <p style="color: var(--text-secondary); margin: 5px 0 0 0;">
                Exportable a Excel, PDF, Impresión y más
            </p>
        </div>
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="table-responsive">
        <table id="tablaMantenimientos" class="table table-striped table-hover" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Equipo</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Tipo Demanda</th>
                    <th>Técnico</th>
                    <th>Descripción</th>
                    <th>Repuestos</th>
                    <th>Estado Inicial</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mantenimientos as $mant): ?>
                <tr>
                    <td><?php echo $mant['id']; ?></td>
                    <td><?php echo date('d/m/Y', strtotime($mant['fecha_mantenimiento'])); ?></td>
                    <td><?php echo htmlspecialchars($mant['codigo_patrimonial'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($mant['marca'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($mant['modelo'] ?? 'N/A'); ?></td>
                    <td>
                        <?php
                        $tipo = $mant['tipo_demanda'] ?? 'N/A';
                        $badge_class = 'badge-preventivo';
                        if (stripos($tipo, 'correctivo') !== false) {
                            $badge_class = 'badge-correctivo';
                        } elseif (stripos($tipo, 'emergencia') !== false) {
                            $badge_class = 'badge-emergencia';
                        }
                        ?>
                        <span class="badge <?php echo $badge_class; ?>">
                            <?php echo htmlspecialchars($tipo); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($mant['tecnico_responsable'] ?? 'No especificado'); ?></td>
                    <td>
                        <?php 
                        $desc = $mant['descripcion'] ?? '';
                        echo htmlspecialchars(mb_substr($desc, 0, 100)) . (mb_strlen($desc) > 100 ? '...' : '');
                        ?>
                    </td>
                    <td>
                        <?php 
                        $cant_repuestos = $mant['cantidad_repuestos'] ?? 0;
                        if ($cant_repuestos > 0): 
                        ?>
                            <span class="badge badge-repuestos">
                                <i class="fas fa-boxes"></i> <?php echo $cant_repuestos; ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Sin repuestos</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($mant['estado_anterior'] ?? 'N/A'); ?></td>
                    <td>
                        <a href="editar.php?id=<?php echo $mant['id']; ?>" 
                           class="btn btn-sm btn-primary" 
                           title="Ver/Editar">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
