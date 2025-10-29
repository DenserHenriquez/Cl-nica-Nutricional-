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

    <section class="hero">
        <div class="hero__inner">
            <h1>Bienvenidos a <span>Clínica Nutricional</span></h1>
            <p>Seleccione una opción del menú para continuar</p>
        </div>
    </section>

    <main class="container" role="main">
        <section class="menu-grid" aria-label="Menú principal">
            <a class="card" href="Actualizar_perfil.php">
                <span class="card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 12a5 5 0 1 0-5-5 5.006 5.006 0 0 0 5 5zm-7 9a7 7 0 0 1 14 0 1 1 0 0 1-1 1H6a1 1 0 0 1-1-1z"/></svg>
                </span>
                <span>
                    <div class="card__title">Actualizar Perfil</div>
                    <div class="card__desc">Gestione su información personal y credenciales</div>
                </span>
            </a>

            <a class="card" href="Activar_desactivar_paciente.php">
                <span class="card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20 6h-2.586l-2-2H8.586l-2 2H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2zm-8 12H6v-2h6zm6-4H6v-2h12z"/></svg>
                </span>
                <span>
                    <div class="card__title">Estado del Paciente</div>
                    <div class="card__desc">Activar o desactivar historial clínico</div>
                </span>
            </a>

            <a class="card" href="Busqueda_avanzada.php">
                <span class="card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M21.707 20.293l-4.387-4.387A7.939 7.939 0 0 0 20 10a8 8 0 1 0-8 8 7.939 7.939 0 0 0 5.906-2.68l4.387 4.387a1 1 0 0 0 1.414-1.414zM4 10a6 6 0 1 1 6 6 6.006 6.006 0 0 1-6-6z"/></svg>
                </span>
                <span>
                    <div class="card__title">Búsqueda Avanzada</div>
                    <div class="card__desc">Encuentre pacientes, citas y registros rápidamente</div>
                </span>
            </a>

            <a class="card" href="Carga_fotografias.php">
                <span class="card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M21 6h-3.586l-1.707-1.707A.996.996 0 0 0 15 4H9a.996.996 0 0 0-.707.293L6.586 6H3a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h18a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2zM12 18a4 4 0 1 1 4-4 4.005 4.005 0 0 1-4 4z"/></svg>
                </span>
                <span>
                    <div class="card__title">Carga Fotográfica</div>
                    <div class="card__desc">Suba y gestione fotografías clínicas</div>
                </span>
            </a>

            <a class="card" href="citas_medico.php">
                <span class="card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 4h-1V2h-2v2H8V2H6v2H5a3 3 0 0 0-3 3v11a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3zm1 14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V10h16zm-4-7h-4v4h4z"/></svg>
                </span>
                <span>
                    <div class="card__title">Citas Médicas</div>
                    <div class="card__desc">Programe y gestione citas con pacientes</div>
                </span>
            </a>

            <a class="card" href="Disponibilidad_citas.php">
                <span class="card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 3a1 1 0 0 1 1 1v1h1a1 1 0 0 1 0 2h-1v2h1a1 1 0 0 1 0 2h-1v2h1a1 1 0 0 1 0 2h-1v1a1 1 0 0 1-1 1h-1v1a1 1 0 0 1-2 0v-1h-2v1a1 1 0 0 1-2 0v-1H9v1a1 1 0 0 1-2 0v-1H6a1 1 0 0 1-1-1v-1H4a1 1 0 0 1 0-2h1v-2H4a1 1 0 0 1 0-2h1V7H4a1 1 0 0 1 0-2h1V4a1 1 0 0 1 1-1h1V2a1 1 0 0 1 2 0v1h2V2a1 1 0 0 1 2 0v1h1zM7 9v6h10V9z"/></svg>
                </span>
                <span>
                    <div class="card__title">Disponibilidad de Citas</div>
                    <div class="card__desc">Defina horarios y franjas disponibles</div>
                </span>
            </a>

            <a class="card" href="Registropacientes.php">
                <span class="card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 2H8a2 2 0 0 0-2 2v2H5a3 3 0 0 0-3 3v9a3 3 0 0 0 3 3h11a3 3 0 0 0 3-3v-2h1a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM5 20a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1h1v10a1 1 0 0 1-1 1zm13-3a1 1 0 0 1-1 1H8V4h11zM10 7h7v2h-7zm0 4h7v2h-7zm0 4h5v2h-5z"/></svg>
                </span>
                <span>
                    <div class="card__title">Registro de Pacientes</div>
                    <div class="card__desc">Cree y administre nuevos pacientes</div>
                </span>
            </a>

            <a class="card" href="Resgistro_Alimentos.php">
                <span class="card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20 3h-7a1 1 0 0 0-1 1v7H4a1 1 0 0 0-1 1v7a1 1 0 0 0 1 1h7v-7h7a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1z"/></svg>
                </span>
                <span>
                    <div class="card__title">Registro de Alimentos</div>
                    <div class="card__desc">Gestione planes y registros alimentarios</div>
                </span>
            </a>

            <a class="card" href="Seguimiento_ejercicio.php">
                <span class="card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M21 11h-2V9a1 1 0 0 0-2 0v2h-4V9a1 1 0 0 0-2 0v2H7V9a1 1 0 0 0-2 0v2H3a1 1 0 0 0 0 2h2v2a1 1 0 0 0 2 0v-2h4v2a1 1 0 0 0 2 0v-2h4v2a1 1 0 0 0 2 0v-2h2a1 1 0 0 0 0-2z"/></svg>
                </span>
                <span>
                    <div class="card__title">Seguimiento de Ejercicios</div>
                    <div class="card__desc">Controle rutinas y progreso físico</div>
                </span>
            </a>

            <a class="card" href="panelevolucionpaciente.php">
                <span class="card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3 17a1 1 0 0 1 1-1h2.586l2.707-2.707a1 1 0 0 1 1.414 0L13 15.586l3.293-3.293a1 1 0 0 1 1.414 0L21 15.586V13a1 1 0 0 1 2 0v6a1 1 0 0 1-1 1h-6a1 1 0 0 1 0-2h2.586l-3.293-3.293-3.293 3.293A1 1 0 0 1 11 18H4a1 1 0 0 1-1-1zM7 4h10a2 2 0 0 1 2 2v4a1 1 0 1 1-2 0V6H7v12h4a1 1 0 1 1 0 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/></svg>
                </span>
                <span>
                    <div class="card__title">Evolución del Paciente</div>
                    <div class="card__desc">Histórico de peso/IMC y notas clínicas</div>
                </span>
            </a>
        </section>
    </main>

    <footer>
        © <span id="year"></span> Clínica Nutricional. Todos los derechos reservados.
    </footer>

    <script>
        document.getElementById('year').textContent = new Date().getFullYear();
    </script>
</body>
</html>
