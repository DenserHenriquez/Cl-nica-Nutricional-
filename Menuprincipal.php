<?php
// Menuprincipal.php
// Muestra el nombre del usuario autenticado desde la base de datos 'clinica', tabla 'usuarios'
// Requiere que Login.php haya establecido las variables de sesión

session_start();
if (!isset($_SESSION['id_usuarios'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/db_connection.php';

$userId = (int)($_SESSION['id_usuarios'] ?? 0);
$userName = $_SESSION['nombre'] ?? '';
$userRole = $_SESSION['rol'] ?? 'Paciente'; // Por defecto Paciente si no hay rol

// Consultar el nombre y rol actualizado desde la BD para asegurar consistencia
if ($userId > 0) {
    if ($stmt = $conexion->prepare('SELECT Nombre_completo, Rol FROM usuarios WHERE id_usuarios = ? LIMIT 1')) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($dbName, $dbRole);
        if ($stmt->fetch()) {
            if ($dbName) {
                $userName = $dbName;
                $_SESSION['nombre'] = $dbName;
            }
            if ($dbRole) {
                $userRole = $dbRole;
                $_SESSION['rol'] = $dbRole;
            }
        }
        $stmt->close();
    }
}

// Determinar mensaje de bienvenida según rol
$welcomeMessage = 'Bienvenido';
if ($userRole === 'Medico') {
    $welcomeMessage = 'Bienvenido Doctor';
} elseif ($userRole === 'Administrador') {
    $welcomeMessage = 'Bienvenido Administrador';
} elseif ($userRole === 'Paciente') {
    $welcomeMessage = 'Bienvenido';
}

// Definir qué opciones puede ver cada rol
$menuItems = [
    // Actualizar perfil disponible solo para Medico y Administrador (no para Paciente)
    'actualizar_perfil' => ['Medico', 'Administrador', 'Paciente'],
    'estado_paciente' => ['Medico', 'Administrador'],
    'panel_evolucion' => ['Medico', 'Paciente', 'Administrador'],
    'busqueda_avanzada' => ['Medico', 'Administrador'],
    'citas_medicas' => ['Medico', 'Administrador'],
    'disponibilidad_citas' => ['Medico', 'Administrador', 'Paciente'],
    'registro_pacientes' => ['Medico', 'Administrador'],
    'registro_alimentos' => ['Medico', 'Paciente', 'Administrador'],
    'clasificacion_alimentos' => ['Medico', 'Administrador'],
    'crear_receta' => ['Medico', 'Administrador'],
    'gestion_receta' => ['Medico', 'Administrador'],
    'seguimiento_ejercicio' => ['Medico', 'Paciente', 'Administrador'],
    'retroalimentacion' => ['Medico', 'Paciente', 'Administrador']
];

// Función para verificar si el usuario tiene acceso a un item del menú
function hasAccess($itemKey, $userRole, $menuItems) {
    return isset($menuItems[$itemKey]) && in_array($userRole, $menuItems[$itemKey], true);
}

function e($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Menu Principal | Clinica Nutricional</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-900: #0d5132; /* verde profundo */
            --primary-700: #146c43; /* verde medio */
            --primary-500: #198754; /* verde */
            --primary-300: #75b798; /* verde claro */
            --white: #ffffff;
            --text-900: #0b1b34;
            --text-700: #22426e;
            --shadow: 0 10px 25px rgba(25, 135, 84, 0.18);
            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 10px;
        }

        /* Reset y base */
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", sans-serif;
            color: var(--text-900);
            background: linear-gradient(180deg, #f7fbff 0%, #f3f8ff 100%);
            overflow-x: hidden;
        }
        a { color: inherit; text-decoration: none; }

        /* Ocultar barras de desplazamiento pero mantener funcionalidad */
        ::-webkit-scrollbar {
            display: none;
        }
        * {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Barra superior */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: #ffffff;
            color: #198754;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid #e9ecef;
        }
        .topbar__inner {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 8px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            max-width: none;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            letter-spacing: .3px;
            margin-left: 0;
            flex-shrink: 0;
        }
        .brand__logo {
            width: 30px; 
            height: 30px; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
        }
        .brand__logo i { 
            font-size: 24px;
            color: #198754; 
        }
        .brand__name { 
            font-size: 1.25rem; 
            font-weight: 700;
            color: #198754;
        }

        .topbar__actions { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            margin-right: 0;
        }
        .topbar__actions a {
            display: inline-block;
            padding: 8px 16px;
            background: #198754;
            border: 1px solid #198754;
            border-radius: 20px;
            color: #fff;
            font-size: .9rem;
            font-weight: 500;
            transition: all .2s ease;
            white-space: nowrap;
        }
        .topbar__actions a:hover { 
            background: #146c43; 
            border-color: #146c43;
            transform: translateY(-1px); 
        }

        .user-pill {
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            padding: 5px 12px 5px 5px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 20px; 
            color: #198754; 
            font-weight: 600; 
            letter-spacing: .2px;
            white-space: nowrap;
            font-size: .9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        .user-pill:hover {
            background: #e9ecef;
            border-color: #198754;
        }
        .user-avatar {
            width: 26px; 
            height: 26px; 
            border-radius: 50%;
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
            background: #198754;
            color: #ffffff; 
            font-weight: 800;
            font-size: .8rem;
            border: 1px solid #198754;
        }
        
        /* Dropdown del usuario */
        .user-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            min-width: 280px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1100;
            overflow: hidden;
        }
        .user-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .user-dropdown::before {
            content: '';
            position: absolute;
            top: -6px;
            right: 20px;
            width: 12px;
            height: 12px;
            background: #ffffff;
            border-left: 1px solid #e0e0e0;
            border-top: 1px solid #e0e0e0;
            transform: rotate(45deg);
        }
        .user-dropdown-header {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            background: #ffffff;
            text-align: left;
        }
        .user-dropdown-header .avatar-large {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 12px;
        }
        .user-dropdown-header .name {
            font-weight: 700;
            color: #000000;
            font-size: 1.05rem;
            margin-bottom: 4px;
        }
        .user-dropdown-header .role {
            font-size: 0.9rem;
            color: #666666;
            font-weight: 400;
        }
        .user-dropdown-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 20px;
            color: #000000;
            text-decoration: none;
            transition: all 0.15s ease;
            border-bottom: none;
            background: #ffffff;
            font-size: 0.95rem;
            font-weight: 400;
        }
        .user-dropdown-item:last-child {
            border-bottom: none;
        }
        .user-dropdown-item:hover {
            background-color: #f5f5f5;
            color: #000000;
        }
        .user-dropdown-item i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
            color: #333333;
            transition: none;
        }
        .user-dropdown-item:hover i {
            color: #333333;
        }
        .user-dropdown-item span {
            background: none !important;
            border: none !important;
            padding: 0 !important;
            border-radius: 0 !important;
            color: inherit !important;
            display: inline !important;
            font-weight: 400 !important;
            box-shadow: none !important;
        }
        .user-dropdown-item span::before,
        .user-dropdown-item span::after {
            display: none !important;
        }
        .user-pill-wrapper {
            position: relative;
        }

        /* Hero de bienvenida */
        .hero {
            position: relative;
            overflow: hidden;
            background:
                linear-gradient(135deg, rgba(25, 118, 210, .80), rgba(13, 71, 161, .85)),
                url('assets/images/bg3.jpg') center/cover no-repeat;
            color: var(--white);
        }
        .hero::after {
            content: "";
            position: absolute; inset: 0;
            background: radial-gradient(1100px 400px at 20% -10%, rgba(255,255,255,.25), transparent 60%),
                        radial-gradient(1000px 400px at 120% 110%, rgba(255,255,255,.20), transparent 60%);
            pointer-events: none;
        }
        .hero__inner {
            max-width: 1200px; margin: 0 auto;
            padding: 56px 20px 36px;
            display: grid; grid-template-columns: 1fr; gap: 12px;
            text-align: center;
        }
        .hero h1 {
            margin: 0;
            font-size: clamp(1.6rem, 2.5vw + 1rem, 2.4rem);
            font-weight: 800;
            line-height: 1.15;
            text-shadow: 0 4px 18px rgba(0,0,0,.25);
        }
        .hero h1 span { color: #e3f2fd; }
        .hero p {
            margin: 6px 0 0 0;
            font-size: clamp(.98rem, .6vw + .8rem, 1.05rem);
            opacity: .95;
        }

        /* Contenedor principal */
        .container {
            max-width: 1200px; margin: 0 auto; padding: 26px 20px 48px;
        }

        /* Tarjetas del menú */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 16px;
        }
        @media (min-width: 640px) { .menu-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 900px) { .menu-grid { grid-template-columns: repeat(3, 1fr); } }

        .card {
            background: var(--white);
            border-radius: var(--radius-lg);
            border: 1px solid rgba(13, 71, 161, .10);
            padding: 18px 18px 18px 18px;
            box-shadow: 0 6px 16px rgba(13, 71, 161, 0.10);
            display: grid; grid-template-columns: auto 1fr; align-items: center; gap: 14px;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
            position: relative; overflow: hidden;
        }
        .card::before {
            content: ""; position: absolute; inset: 0; pointer-events: none;
            background: radial-gradient(220px 120px at -10% -20%, rgba(25, 118, 210, .10), transparent 60%),
                        radial-gradient(220px 120px at 120% 120%, rgba(25, 118, 210, .08), transparent 60%);
            opacity: .9;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 30px rgba(13, 71, 161, 0.18);
            border-color: rgba(25, 118, 210, .25);
        }
        .card__icon {
            width: 46px; height: 46px; border-radius: 12px;
            display: inline-flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, rgba(25,118,210,.12), rgba(13,71,161,.12));
            border: 1px solid rgba(25,118,210,.22);
            box-shadow: 0 4px 10px rgba(13,71,161,.10) inset;
        }
        .card__icon svg { width: 22px; height: 22px; fill: var(--primary-700); opacity: .95; }
        .card__title { font-weight: 700; font-size: 1.02rem; color: var(--text-900); }
        .card__desc { margin: 4px 0 0 0; font-size: .92rem; color: var(--text-700); opacity: .9; }
        
        /* Layout fijo para mantener consistencia en pantalla grande */
        body {
            padding-top: 50px; /* Reducido para topbar más compacto */
        }
        
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            height: 50px; /* Altura fija más compacta */
            z-index: 1000;
            background: #ffffff;
            color: #28a745;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid #e9ecef;
        }
        
        /* Layout container */
        .container-fluid {
            padding: 0;
            height: calc(100vh - 50px);
        }
        
        .row {
            height: 100%;
            margin: 0;
        }
        
        /* Sidebar fijo */
        .sidebar {
            position: fixed;
            top: 50px;
            left: 0;
            width: 250px !important;
            height: calc(100vh - 50px);
            background: #f8f9fa;
            border-right: 1px solid #dee2e6;
            overflow-y: auto;
            z-index: 999;
            padding: 0;
        }
        
        /* Estilos del sidebar */
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #495057;
            text-decoration: none;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }
        
        .sidebar .nav-link:hover {
            background-color: #cfe2ff;
            color: #0d6efd;
            transform: translateX(3px);
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            color: #6c757d;
            transition: color 0.2s ease;
        }

        .sidebar .nav-link:hover i {
            color: #0d6efd;
        }
        
        .sidebar-heading {
            font-size: 1.1rem;
            font-weight: 700;
            color: #198754;
            margin: 0;
            padding: 18px 20px 14px;
            border-bottom: 3px solid var(--primary-300);
            background: linear-gradient(135deg, rgba(76,175,80,.08), rgba(46,125,50,.05));
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .sidebar-heading:hover {
            background: linear-gradient(135deg, rgba(76,175,80,.15), rgba(46,125,50,.10));
        }

        .sidebar-heading i {
            display: inline-block;
            font-size: 1.2rem;
            margin-right: 8px;
            transition: transform 0.3s ease;
        }

        .sidebar-heading:hover i {
            transform: rotate(90deg) scale(1.2);
        }

        /* Sidebar con animación de deslizamiento */
        .sidebar {
            position: fixed;
            top: 50px;
            left: 0;
            width: 250px !important;
            height: calc(100vh - 50px);
            background: #f8f9fa;
            border-right: 1px solid #dee2e6;
            overflow-y: auto;
            z-index: 999;
            padding: 0;
            transition: transform 0.3s ease;
        }

        .sidebar.hidden {
            transform: translateX(-100%);
        }

        /* Botón flotante para reabrir sidebar */
        .sidebar-toggle-btn {
            position: fixed;
            top: 70px;
            left: 20px;
            z-index: 1001;
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            border: none;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            box-shadow: 0 4px 15px rgba(25, 135, 84, 0.3);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            opacity: 0;
            visibility: hidden;
            transform: scale(0);
        }

        .sidebar-toggle-btn.show {
            opacity: 1;
            visibility: visible;
            transform: scale(1);
        }

        .sidebar-toggle-btn:hover {
            background: linear-gradient(135deg, #146c43 0%, #198754 100%);
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(20, 108, 67, 0.4);
        }

        .sidebar-toggle-btn i {
            font-size: 1.5rem;
        }
        
        /* Main content ajustado */
        main {
            margin-left: 250px !important;
            width: calc(100% - 250px) !important;
            height: calc(100vh - 50px);
            padding: 0 !important;
            overflow: hidden;
            transition: margin-left 0.3s ease, width 0.3s ease;
        }

        main.expanded {
            margin-left: 0 !important;
            width: 100% !important;
        }
        
        /* Iframe que ocupa todo el espacio */
        main iframe {
            width: 100% !important;
            height: 100% !important;
            border: none !important;
            margin: 0;
            padding: 0;
        }
        
        /* Responsivo - ocultar sidebar en pantallas muy pequeñas */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            main {
                margin-left: 0 !important;
                width: 100% !important;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
        }
        
        /* Pie de página */
        footer { 
            text-align: center; 
            color: #5b7aa7; 
            font-size: .92rem; 
            padding: 18px 0 42px;
            position: fixed;
            bottom: 0;
            left: 250px;
            right: 0;
            background: rgba(248, 249, 250, 0.95);
            border-top: 1px solid #dee2e6;
        }
        
        @media (max-width: 768px) {
            footer {
                left: 0;
            }
        }
    </style>
</head>
<body>
    <header class="topbar" role="banner">
        <div class="topbar__inner">
            <div class="brand" aria-label="Clinica Nutricional">
                <!-- Boton menu movil -->
                <button class="btn btn-link text-success d-md-none" id="sidebarToggle" style="padding: 0; border: none; margin-right: 10px;">
                    <i class="bi bi-list" style="font-size: 1.5rem; color: #198754;"></i>
                </button>
                <span class="brand__logo" aria-hidden="true">
                    <i class="fas fa-leaf"></i>
                </span>
                <span class="brand__name">Clinica Nutricional</span>
            </div>
            <div class="topbar__actions">
                <div class="user-pill-wrapper">
                    <span class="user-pill" id="userPill" title="Usuario actual - <?php echo e($userRole); ?>">
                        <span class="user-avatar" aria-hidden="true"><?php echo e(mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8'), 'UTF-8')); ?></span>
                        <span><?php echo e($userName ?: 'Usuario'); ?> (<?php echo e($userRole); ?>)</span>
                    </span>
                </div>
                <?php if (hasAccess('actualizar_perfil', $userRole, $menuItems)): ?>
                <a href="Actualizar_perfil.php" target="main-content">
                    <i class="bi bi-person-gear me-1"></i> Actualizar Perfil
                </a>
                <?php endif; ?>
                <a href="Login.php">
                    <i class="bi bi-box-arrow-right me-1"></i> Cerrar Sesion
                </a>
            </div>
        </div>
    </header>

    <!-- Boton flotante para reabrir sidebar -->
    <button class="sidebar-toggle-btn" id="sidebarFloatingBtn" onclick="toggleSidebar()">
        <i class="bi bi-grid-3x3-gap"></i>
    </button>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="sidebar">
                <div class="position-sticky">
                    <h5 class="sidebar-heading" onclick="toggleSidebar()">
                        <i class="bi bi-grid-3x3-gap"></i>
                        <span>Menu Principal</span>
                    </h5>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="inicio.php" target="main-content">
                                <i class="bi bi-house-door"></i> Inicio
                            </a>
                        </li>
                        <?php if ($userRole === 'Paciente'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="Disponibilidad_citas.php" target="main-content">
                                <i class="bi bi-clock"></i> Disponibilidad de Citas
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (hasAccess('actualizar_perfil', $userRole, $menuItems)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="Actualizar_perfil.php" target="main-content">
                                <i class="bi bi-person-circle"></i> Actualizar Perfil
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasAccess('estado_paciente', $userRole, $menuItems)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="Activar_desactivar_paciente.php" target="main-content">
                                <i class="bi bi-toggle-on"></i> Estado de Usuarios
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasAccess('panel_evolucion', $userRole, $menuItems)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="panelevolucionpaciente.php" target="main-content">
                                <i class="bi bi-graph-up"></i> Panel de Evolucion
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasAccess('busqueda_avanzada', $userRole, $menuItems)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="Busqueda_avanzada.php" target="main-content">
                                <i class="bi bi-search"></i> Busqueda Avanzada
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasAccess('citas_medicas', $userRole, $menuItems)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="citas_medico.php" target="main-content">
                                <i class="bi bi-calendar-event"></i> Citas Medicas
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasAccess('disponibilidad_citas', $userRole, $menuItems)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="Disponibilidad_citas.php" target="main-content">
                                <i class="bi bi-clock"></i> Disponibilidad de Citas
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasAccess('registro_pacientes', $userRole, $menuItems)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="Registropacientes.php" target="main-content">
                                <i class="bi bi-person-plus"></i> Registro de Pacientes
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasAccess('registro_alimentos', $userRole, $menuItems)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="Resgistro_Alimentos.php" target="main-content">
                                <i class="bi bi-apple"></i> Registro de Alimentos
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasAccess('clasificacion_alimentos', $userRole, $menuItems)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="Clasificacion_alimentos.php" target="main-content">
                                <i class="bi bi-apple"></i> Clasificacion de Alimentos
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasAccess('crear_receta', $userRole, $menuItems)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="Crear_Receta.php" target="main-content">
                                <i class="bi bi-receipt"></i> Crear Receta
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (hasAccess('gestion_receta', $userRole, $menuItems)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="Gestion_Receta.php" target="main-content">
                                <i class="bi bi-journal-text"></i> Gestión de Recetas
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (hasAccess('seguimiento_ejercicio', $userRole, $menuItems)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="Crear_Receta.php" target="main-content">
                                <i class="bi bi-receipt"></i> Crear Receta
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="Gestion_Receta.php" target="main-content">
                                <i class="bi bi-journal-text"></i> Gestión de Recetas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="Seguimiento_ejercicio.php" target="main-content">
                                <i class="bi bi-activity"></i> Seguimiento de Ejercicios
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasAccess('retroalimentacion', $userRole, $menuItems)): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="retroalimentacion1.php" target="main-content">
                                <i class="bi bi-chat-dots"></i> Retroalimentacion
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main>
                <iframe name="main-content" src="<?php echo ($userRole === 'Paciente') ? 'Disponibilidad_citas.php' : 'inicio.php'; ?>"></iframe>
            </main>
        </div>
    </div>

    <footer class="text-center text-muted py-3">
        © <span id="year"></span> Clinica Nutricional. Todos los derechos reservados.
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('year').textContent = new Date().getFullYear();
        
        // Toggle dropdown del usuario
        const userPill = document.getElementById('userPill');
        const userDropdown = document.getElementById('userDropdown');
        
        userPill.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });
        
        // Cerrar dropdown al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.user-pill-wrapper')) {
                userDropdown.classList.remove('show');
            }
        });
        
        // Cerrar dropdown al hacer clic en un enlace
        document.querySelectorAll('.user-dropdown-item').forEach(function(item) {
            item.addEventListener('click', function() {
                userDropdown.classList.remove('show');
            });
        });
        
        // Variable para controlar el estado del sidebar
        let sidebarVisible = true;
        
        // Función para alternar el sidebar
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('main');
            const floatingBtn = document.getElementById('sidebarFloatingBtn');
            
            sidebarVisible = !sidebarVisible;
            
            if (sidebarVisible) {
                // Mostrar sidebar
                sidebar.classList.remove('hidden');
                mainContent.classList.remove('expanded');
                floatingBtn.classList.remove('show');
            } else {
                // Ocultar sidebar
                sidebar.classList.add('hidden');
                mainContent.classList.add('expanded');
                // Mostrar botón flotante después de la animación
                setTimeout(() => {
                    floatingBtn.classList.add('show');
                }, 300);
            }
        }
        
        // Manejar toggle del sidebar en móviles
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('show');
        });
        
        // Cerrar sidebar al hacer clic en un enlace (móviles)
        document.querySelectorAll('.sidebar .nav-link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth < 768) {
                    document.querySelector('.sidebar').classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>
