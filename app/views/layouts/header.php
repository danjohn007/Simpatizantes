<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <?php
    // Cargar colores personalizados desde configuración
    $db_colors = Database::getInstance();
    $sql_colors = "SELECT clave, valor FROM configuracion WHERE tipo = 'color'";
    $colores = $db_colors->query($sql_colors);
    $color_primario = '#667eea';
    $color_secundario = '#764ba2';
    foreach ($colores as $color) {
        if ($color['clave'] === 'color_primario') $color_primario = $color['valor'];
        if ($color['clave'] === 'color_secundario') $color_secundario = $color['valor'];
    }
    ?>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, <?php echo $color_primario; ?> 0%, <?php echo $color_secundario; ?> 100%);
            --primary-color: <?php echo $color_primario; ?>;
            --secondary-color: <?php echo $color_secundario; ?>;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: var(--primary-gradient);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .sidebar {
            min-height: calc(100vh - 56px);
            background: white;
            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
        }
        
        .sidebar .nav-link {
            color: #333;
            padding: 12px 20px;
            border-radius: 5px;
            margin: 5px 10px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover {
            background: #f8f9fa;
            color: #667eea;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            background: var(--primary-gradient);
            color: white;
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .card {
            border-radius: 10px;
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .btn-gradient {
            background: var(--primary-gradient);
            border: none;
            color: white;
        }
        
        .btn-gradient:hover {
            opacity: 0.9;
            color: white;
        }
        
        .content-wrapper {
            padding: 20px;
        }
        
        /* Mobile sidebar overlay */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 56px;
                left: -100%;
                width: 280px;
                height: calc(100vh - 56px);
                z-index: 1040;
                transition: left 0.3s ease-in-out;
                overflow-y: auto;
            }
            
            .sidebar.show {
                left: 0;
                box-shadow: 2px 0 10px rgba(0,0,0,0.2);
            }
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 56px;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 1030;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
            
            .content-wrapper {
                margin-left: 0 !important;
            }
            
            .menu-toggle {
                display: inline-block;
            }
        }
        
        @media (min-width: 769px) {
            .menu-toggle {
                display: none;
            }
        }
    </style>
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
                    
                    <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'simpatizantes') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>/public/simpatizantes/">
                        <i class="bi bi-people-fill"></i>Simpatizantes
                    </a>
                    
                    <?php if (in_array($_SESSION['rol'], ['super_admin', 'admin', 'coordinador'])): ?>
                    <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'campanas') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>/public/campanas/">
                        <i class="bi bi-megaphone-fill"></i>Campañas
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
