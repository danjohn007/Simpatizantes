<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="<?php echo BASE_URL; ?>/public/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/vendor/icons/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <?php
    // Cargar colores personalizados desde configuración
    $db_colors = Database::getInstance();
    $sql_colors = "SELECT clave, valor FROM configuracion WHERE tipo = 'color'";
    $colores = $db_colors->query($sql_colors);
    $color_primario = '#ff8000';
    $color_secundario = '#111111';
    foreach ($colores as $color) {
        if ($color['clave'] === 'color_primario') $color_primario = $color['valor'];
        if ($color['clave'] === 'color_secundario') $color_secundario = $color['valor'];
    }
    ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/theme.css?v=<?php echo @filemtime(BASE_PATH . '/public/css/theme.css') ?: 1; ?>">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <!-- Menu toggle for mobile -->
            <button class="btn btn-link text-white menu-toggle me-2" id="sidebarToggle" type="button">
                <i class="bi bi-list fs-4"></i>
            </button>
            
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>/public/dashboard.php">
                <i class="bi bi-person-check-fill me-2"></i>
                <?php echo APP_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" 
                           data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="#">
                                    <i class="bi bi-shield-check me-2"></i>
                                    Rol: <?php echo ucfirst($_SESSION['rol']); ?>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>/public/perfil.php">
                                    <i class="bi bi-person-fill me-2"></i>Mi Perfil
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>/public/logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Sidebar overlay (only visible on mobile when sidebar is open) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar d-md-block" id="sidebar">
                <nav class="nav flex-column py-3">
                    <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>/public/dashboard.php">
                        <i class="bi bi-speedometer2"></i>Dashboard
                    </a>
                    
                    <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], '/public/simpatizantes/') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>/public/simpatizantes/">
                        <i class="bi bi-people-fill"></i>Simpatizantes
                    </a>

                    <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'captura.php') !== false) ? 'active' : ''; ?>"
                       href="<?php echo BASE_URL; ?>/public/captura.php">
                        <i class="bi bi-phone-fill"></i>Captura en campo
                    </a>
                    
                    <?php if (in_array($_SESSION['rol'], ['super_admin', 'admin', 'coordinador'])): ?>
                    <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'campanas') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>/public/campanas/">
                        <i class="bi bi-megaphone-fill"></i>Campañas
                    </a>
                    <?php endif; ?>
                    
                    <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], '/comunicacion/') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>/public/comunicacion/">
                        <i class="bi bi-chat-dots-fill"></i>Comunicación
                    </a>
                    
                    <?php if (in_array($_SESSION['rol'], ['super_admin', 'admin', 'coordinador'])): ?>
                    <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], '/atencion/') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>/public/atencion/">
                        <i class="bi bi-megaphone-fill"></i>Atención ciudadana
                    </a>
                    <?php endif; ?>
                    
                    <?php if (in_array($_SESSION['rol'], ['super_admin', 'admin', 'coordinador'])): ?>
                    <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], '/campo/') !== false) ? 'active' : ''; ?>"
                       href="<?php echo BASE_URL; ?>/public/campo/">
                        <i class="bi bi-bullseye"></i>Metas y zonas
                    </a>
                    <?php endif; ?>

                    <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'mapa-calor') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>/public/mapa-calor.php">
                        <i class="bi bi-map-fill"></i>Mapa de Calor
                    </a>
                    
                    <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'reportes') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>/public/reportes/">
                        <i class="bi bi-graph-up"></i>Reportes
                    </a>
                    
                    <?php if (in_array($_SESSION['rol'], ['super_admin', 'admin'])): ?>
                    <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'usuarios') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>/public/usuarios/">
                        <i class="bi bi-person-badge-fill"></i>Usuarios
                    </a>
                    
                    <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'auditoria') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>/public/auditoria/">
                        <i class="bi bi-journal-text"></i>Auditoría
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['rol'] === 'super_admin'): ?>
                    <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'configuracion') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>/public/configuracion/">
                        <i class="bi bi-gear-fill"></i>Configuración
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
            
            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 ms-sm-auto content-wrapper">
                <?php
                // Mensajes flash (éxito / error) guardados en sesión por otras pantallas
                $flashes = [];
                if (!empty($_SESSION['success'])) { $flashes[] = ['success', $_SESSION['success'], 'check-circle-fill']; unset($_SESSION['success']); }
                if (!empty($_SESSION['error']))   { $flashes[] = ['danger',  $_SESSION['error'],   'exclamation-triangle-fill']; unset($_SESSION['error']); }
                if (empty($flashes) && ($_GET['success'] ?? '') === '1') { $flashes[] = ['success', 'Operación realizada correctamente', 'check-circle-fill']; }
                foreach ($flashes as $f): ?>
                    <div class="alert alert-<?php echo $f[0]; ?> alert-dismissible fade show d-flex align-items-center" role="alert">
                        <i class="bi bi-<?php echo $f[2]; ?> me-2"></i>
                        <div><?php echo htmlspecialchars($f[1]); ?></div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                <?php endforeach; ?>

