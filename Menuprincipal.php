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

// Consultar el nombre actualizado desde la BD para asegurar consistencia
if ($userId > 0) {
    if ($stmt = $conexion->prepare('SELECT Nombre_completo FROM usuarios WHERE id_usuarios = ? LIMIT 1')) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($dbName);
        if ($stmt->fetch() && $dbName) {
            $userName = $dbName;
            $_SESSION['nombre'] = $dbName; // refrescar sesión con el nombre vigente
        }
        $stmt->close();
    }
}

function e($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Menú Principal | Clínica Nutricional</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-900: #0d47a1; /* azul profundo */
            --primary-700: #1565c0; /* azul medio */
            --primary-500: #1976d2; /* azul */
            --primary-300: #42a5f5; /* azul claro */
            --white: #ffffff;
            --text-900: #0b1b34;
            --text-700: #22426e;
            --shadow: 0 10px 25px rgba(13, 71, 161, 0.18);
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
        }
        a { color: inherit; text-decoration: none; }

        /* Barra superior */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: linear-gradient(90deg, var(--primary-900), var(--primary-700));
            color: var(--white);
            box-shadow: var(--shadow);
        }
        .topbar__inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            letter-spacing: .3px;
        }
        .brand__logo {
            width: 36px; height: 36px; border-radius: 50%;
            background: radial-gradient(120% 120% at 20% 20%, var(--primary-300), var(--primary-900));
            display: inline-flex; align-items: center; justify-content: center;
            box-shadow: 0 6px 14px rgba(0,0,0,.15) inset, 0 2px 8px rgba(255,255,255,.25);
        }
        .brand__logo svg { width: 22px; height: 22px; fill: #fff; opacity: .95; }
        .brand__name { font-size: 1.05rem; }

        .topbar__actions { display: flex; align-items: center; gap: 10px; }
        .topbar__actions a {
            display: inline-block;
            padding: 8px 14px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.22);
            border-radius: 999px;
            color: #fff;
            font-size: .92rem;
            transition: all .2s ease;
        }
        .topbar__actions a:hover { background: rgba(255,255,255,.22); transform: translateY(-1px); }

        .user-pill {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 6px 10px 6px 6px;
            background: rgba(255,255,255,.16);
            border: 1px solid rgba(255,255,255,.24);
            border-radius: 999px; color: #fff; font-weight: 600; letter-spacing: .2px;
            white-space: nowrap;
        }
        .user-avatar {
            width: 28px; height: 28px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, rgba(255,255,255,.35), rgba(255,255,255,.05));
            color: var(--primary-900); font-weight: 800;
            border: 1px solid rgba(255,255,255,.45);
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

        /* Pie de página */
        footer { text-align: center; color: #5b7aa7; font-size: .92rem; padding: 18px 0 42px; }
    </style>
</head>
<body>
    <header class="topbar" role="banner">
        <div class="topbar__inner">
            <div class="brand" aria-label="Clínica Nutricional">
                <span class="brand__logo" aria-hidden="true">
                    <!-- Ícono cruz salud -->
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" focusable="false" aria-hidden="true">
                        <path d="M10.5 3a1 1 0 0 0-1 1v5H4.5a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h5v5a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-5h5a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1h-5V4a1 1 0 0 0-1-1h-4z"/>
                    </svg>
                </span>
                <span class="brand__name">Clínica Nutricional</span>
            </div>
            <div class="topbar__actions">
                <span class="user-pill" title="Usuario actual">
                    <span class="user-avatar" aria-hidden="true"><?php echo e(mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8'), 'UTF-8')); ?></span>
                    <span><?php echo e($userName ?: 'Usuario'); ?></span>
                </span>
                <a href="Login.php" title="Volver a iniciar sesión">Salir</a>
            </div>
        </div>
    </header>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky">
                    <h5 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                        <span>Menú Principal</span>
                    </h5>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="Actualizar_perfil.php" target="main-content">
                                <i class="bi bi-person-circle"></i> Actualizar Perfil
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="Activar_desactivar_paciente.php" target="main-content">
                                <i class="bi bi-toggle-on"></i> Estado del Paciente
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="Busqueda_avanzada.php" target="main-content">
                                <i class="bi bi-search"></i> Búsqueda Avanzada
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="Carga_fotografias.php" target="main-content">
                                <i class="bi bi-camera"></i> Carga Fotográfica
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="citas_medico.php" target="main-content">
                                <i class="bi bi-calendar-event"></i> Citas Médicas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="Disponibilidad_citas.php" target="main-content">
                                <i class="bi bi-clock"></i> Disponibilidad de Citas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="Registropacientes.php" target="main-content">
                                <i class="bi bi-person-plus"></i> Registro de Pacientes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="Resgistro_Alimentos.php" target="main-content">
                                <i class="bi bi-apple"></i> Registro de Alimentos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="Seguimiento_ejercicio.php" target="main-content">
                                <i class="bi bi-activity"></i> Seguimiento de Ejercicios
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <iframe name="main-content" src="" style="width: 100%; height: 80vh; border: none;"></iframe>
            </main>
        </div>
    </div>

    <footer class="text-center text-muted py-3">
        © <span id="year"></span> Clínica Nutricional. Todos los derechos reservados.
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('year').textContent = new Date().getFullYear();
    </script>
</body>
</html>
