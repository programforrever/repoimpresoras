<?php
// Cargar configuración PRIMERO (antes de session_start)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isLoggedIn()) {
    redirect('controllers/auth.php');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Dashboard'; ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Estilos Personalizados -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    
    <!-- JavaScript Global Variables -->
    <script>
        const BASE_URL = '<?php echo BASE_URL; ?>';
    </script>
    
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <!--<i class="fas fa-print"></i>-->
            <h5 class="mb-0">Sistema de Impresoras</h5>
            <small><?php echo $_SESSION['rol_nombre']; ?></small>
        </div>
        
        <div class="sidebar-menu">
            <a href="<?php echo BASE_URL; ?>/views/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
            
            <a href="<?php echo BASE_URL; ?>/views/equipos/index.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'equipos') !== false ? 'active' : ''; ?>">
                <i class="fas fa-print"></i> Equipos
            </a>
            
            <a href="<?php echo BASE_URL; ?>/views/mantenimientos/index.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'mantenimientos') !== false ? 'active' : ''; ?>">
                <i class="fas fa-wrench"></i> Mantenimientos
            </a>
            
            <a href="<?php echo BASE_URL; ?>/views/repuestos/index.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'repuestos') !== false ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i> Repuestos
            </a>
            
            <a href="<?php echo BASE_URL; ?>/views/importacion/index.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'importacion') !== false ? 'active' : ''; ?>">
                <i class="fas fa-file-excel"></i> Importación Masiva
            </a>
            
            <a href="<?php echo BASE_URL; ?>/views/reportes/index.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'reportes') !== false ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Reportes
            </a>
            
            <?php if (hasRole(ROL_ADMIN)): ?>
            <hr style="border-color: rgba(255,255,255,0.2);">
            
            <a href="<?php echo BASE_URL; ?>/views/usuarios/index.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'usuarios') !== false ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Usuarios
            </a>
            
            <a href="<?php echo BASE_URL; ?>/views/configuracion/index.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'configuracion') !== false ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Configuración
            </a>
            
            <a href="<?php echo BASE_URL; ?>/views/auditoria/index.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'auditoria') !== false ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> Auditoría
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-link d-md-none me-2" id="toggleSidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="page-title"><?php echo $page_title ?? 'Dashboard'; ?></span>
            </div>
            
            <div class="topbar-right">
                <!-- Botón de Cambio de Tema -->
                <button class="theme-toggle" id="themeToggle" title="Cambiar tema">
                    <i class="fas fa-moon"></i>
                </button>
                
                <!-- Dropdown Usuario -->
                <div class="dropdown">
                    <div class="user-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['nombre_completo'], 0, 1)); ?>
                        </div>
                        <div class="user-info d-none d-md-flex">
                            <span class="user-name"><?php echo $_SESSION['nombre_completo']; ?></span>
                            <span class="user-role"><?php echo $_SESSION['rol_nombre']; ?></span>
                        </div>
                        <i class="fas fa-chevron-down ms-2"></i>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>/views/perfil/index.php">
                                <i class="fas fa-user me-2"></i> Mi Perfil
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/controllers/auth.php?action=logout">
                                <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Content -->
        <div class="content-wrapper">
            <?php
            $flash = getFlashMessage();
            if ($flash):
            ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $flash['type'] == 'success' ? 'check-circle' : ($flash['type'] == 'danger' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>