<?php
// Registropacientes.php
// Formulario para registrar pacientes con campos específicos

require_once __DIR__ . '/db_connection.php';
session_start();

// Verificar sesión de usuario
if (!isset($_SESSION['id_usuarios'])) {
    header('Location: index.php');
    exit;
}

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$user_id = (int)($_SESSION['id_usuarios'] ?? 0);
$user_name = $_SESSION['nombre'] ?? ($_SESSION['usuario'] ?? 'Usuario');

// Obtener nombre completo del usuario logueado
if ($user_id > 0 && empty($_SESSION['nombre'])) {
    if ($stmt = $conexion->prepare('SELECT Nombre_completo FROM usuarios WHERE id_usuarios = ? LIMIT 1')) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($dbName);
        if ($stmt->fetch() && $dbName) {
            $user_name = $dbName;
            $_SESSION['nombre'] = $dbName;
        }
        $stmt->close();
    }
}

$errores = [];
$exito = '';

// Manejo de POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF simple
    if (!isset($_POST['csrf']) || !isset($_SESSION['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $errores[] = 'Token inválido. Recargue la página.';
    }

    $dni = isset($_POST['dni']) ? trim($_POST['dni']) : '';
    $fecha_nacimiento = isset($_POST['fecha_nacimiento']) ? trim($_POST['fecha_nacimiento']) : '';
    $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
    $talla = isset($_POST['talla']) ? trim($_POST['talla']) : '';
    $peso = isset($_POST['peso']) ? trim($_POST['peso']) : '';
    $estatura = isset($_POST['estatura']) ? trim($_POST['estatura']) : '';
    $masa_muscular = isset($_POST['masa_muscular']) ? trim($_POST['masa_muscular']) : '';
    $enfermedades_base = isset($_POST['enfermedades_base']) ? trim($_POST['enfermedades_base']) : '';
    $medicamentos = isset($_POST['medicamentos']) ? trim($_POST['medicamentos']) : '';

    // Validaciones
    if (!preg_match('/^\d{13}$/', $dni)) {
        $errores[] = 'DNI debe contener exactamente 13 dígitos numéricos.';
    }

    if (empty($fecha_nacimiento)) {
        $errores[] = 'Fecha de nacimiento es obligatoria.';
    } else {
        $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
        if (!$fecha_obj) {
            $errores[] = 'Fecha de nacimiento inválida.';
        } else {
            $hoy = new DateTime();
            $edad = $hoy->diff($fecha_obj)->y;
            if ($edad < 0 || $edad > 150) {
                $errores[] = 'Fecha de nacimiento inválida.';
            }
        }
    }

    if (!preg_match('/^\d{8}$/', $telefono)) {
        $errores[] = 'Teléfono debe contener exactamente 8 dígitos numéricos.';
    }

    // Validaciones para nuevos campos
    if (!empty($talla) && !is_numeric($talla)) {
        $errores[] = 'Talla debe ser un número válido.';
    }

    if (!empty($peso) && !is_numeric($peso)) {
        $errores[] = 'Peso debe ser un número válido.';
    }

    if (!empty($estatura) && !is_numeric($estatura)) {
        $errores[] = 'Estatura debe ser un número válido.';
    }

    if (!empty($masa_muscular) && !is_numeric($masa_muscular)) {
        $errores[] = 'Masa muscular debe ser un número válido.';
    }

    if (empty($errores)) {
        // Calcular edad
        $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
        $hoy = new DateTime();
        $edad = $hoy->diff($fecha_obj)->y;

        // Calcular IMC si peso y estatura están presentes
        $imc = null;
        if (!empty($peso) && !empty($estatura) && $estatura > 0) {
            $imc = $peso / (($estatura / 100) ** 2);
        }

        // Insertar paciente en tabla 'pacientes' (campos existentes)
        $sql = "INSERT INTO pacientes (id_usuarios, nombre_completo, DNI, fecha_nacimiento, edad, telefono)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('isssis', $user_id, $user_name, $dni, $fecha_nacimiento, $edad, $telefono);
            if ($stmt->execute()) {
                $nuevoIdPaciente = $stmt->insert_id;
                $exito = 'Paciente registrado correctamente.';
            } else {
                $errores[] = 'Error al guardar en BD (pacientes): ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $errores[] = 'Error preparando consulta (pacientes): ' . $conexion->error;
        }

        // Si el INSERT en pacientes fue exitoso y hay métricas, guardar en 'expediente'
        if (empty($errores) && !empty($nuevoIdPaciente)) {
            // Preparar valores opcionales como cadenas, vacías si no hay dato
            $talla_str = $talla !== '' ? (string)$talla : '';
            $peso_str = $peso !== '' ? (string)$peso : '';
            $estatura_str = $estatura !== '' ? (string)$estatura : '';
            $imc_val = ($imc !== null) ? (string)number_format($imc, 2, '.', '') : '';
            $masa_str = $masa_muscular !== '' ? (string)$masa_muscular : '';
            $enf_str = $enfermedades_base !== '' ? (string)$enfermedades_base : '';
            $med_str = $medicamentos !== '' ? (string)$medicamentos : '';

            $sqlExp = "INSERT INTO expediente (id_pacientes, talla, peso, estatura, IMC, masa_muscular, enfermedades_base, medicamentos)
                       VALUES (?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''))";
            $stmtExp = $conexion->prepare($sqlExp);
            if ($stmtExp) {
                $stmtExp->bind_param('isssssss', $nuevoIdPaciente, $talla_str, $peso_str, $estatura_str, $imc_val, $masa_str, $enf_str, $med_str);
                if (!$stmtExp->execute()) {
                    $errores[] = 'Paciente creado, pero error al guardar expediente: ' . $stmtExp->error;
                }
                $stmtExp->close();
            } else {
                $errores[] = 'Paciente creado, pero error preparando expediente: ' . $conexion->error;
            }
        }
    }
}

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registro de Pacientes · Clínica Nutricional</title>
<link rel="stylesheet" href="assets/css/estilos.css">
<style>
    :root {
        --primary-900: #0d47a1;
        --primary-700: #1565c0;
        --primary-500: #1976d2;
        --primary-300: #42a5f5;
        --white: #ffffff;
        --text-900: #0b1b34;
        --muted: #475569;
        --shadow: 0 10px 25px rgba(13,71,161,0.18);
        --radius-lg: 16px;
    }
    *{box-sizing:border-box}
    body {
        font-family: 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(180deg, #f7fbff 0%, #f3f8ff 100%);
        margin:0; color:var(--text-900);
    }

    /* Topbar (copiado estilo panelevolucion) */
    .topbar { position: sticky; top:0; z-index:50; background: linear-gradient(90deg,var(--primary-900),var(--primary-700)); color: var(--white); box-shadow: var(--shadow); }
    .topbar__inner { max-width:1200px; margin:0 auto; padding:12px 20px; display:flex; align-items:center; justify-content:space-between; gap:16px; }
    .brand { display:flex; align-items:center; gap:12px; font-weight:700; }
    .brand__logo { width:36px; height:36px; border-radius:50%; background: radial-gradient(120% 120% at 20% 20%, var(--primary-300), var(--primary-900)); display:inline-flex; align-items:center; justify-content:center; box-shadow: 0 6px 14px rgba(0,0,0,.15) inset; }
    .brand__logo svg{ width:18px; height:18px; fill:#fff; opacity:.95; }
    .brand__name{ font-size:1.05rem; }
    .topbar__actions { display:flex; align-items:center; gap:10px; }
    .topbar__actions a { display:inline-block; padding:8px 14px; background: rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.22); border-radius:999px; color:#fff; font-size:.92rem; }
    .user-pill { display:inline-flex; align-items:center; gap:10px; padding:6px 10px; background: rgba(255,255,255,.16); border-radius:999px; color:#fff; font-weight:600; }
    .user-avatar { width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background: linear-gradient(135deg, rgba(255,255,255,.35), rgba(255,255,255,.05)); color: var(--primary-900); font-weight:800; border:1px solid rgba(255,255,255,.45); }

    /* container card */
    .container { max-width:1100px; margin:24px auto; background:#fff; border-radius:var(--radius-lg); box-shadow:0 6px 16px rgba(13,71,161,0.10); padding:20px; }

    .header-row{ display:flex; gap:16px; align-items:center; margin-bottom:18px; }
    .avatar-large{ width:72px; height:72px; border-radius:12px; background:url('https://cdn-icons-png.flaticon.com/512/3135/3135715.png') center/cover; }
    .title-block h1{ margin:0; font-size:1.2rem; color:#0f1724; }
    .title-block p{ margin:4px 0 0; color:var(--muted); }

    .card { background:#fbfdff; border:1px solid #e6eefb; border-radius:12px; padding:18px; box-shadow:0 2px 8px rgba(2,6,23,0.04); }
    .row{ display:flex; gap:16px; flex-wrap:wrap; margin-bottom:12px; }
    .row > *{ flex:1 1 220px; }
    label{ display:block; font-weight:600; color:#0f1724; margin-bottom:6px; }
    input, textarea, select{ width:100%; padding:10px 12px; border:1px solid #e6eefb; border-radius:8px; background:#fff; font-size:1rem; color:#0f1724; }
    input:focus, textarea:focus{ outline:none; box-shadow:0 4px 18px rgba(25,118,210,0.08); border-color:var(--primary-500); }

    .actions{ display:flex; gap:12px; margin-top:14px; justify-content:flex-end; }
    .btn{ background:var(--primary-500); color:var(--white); padding:10px 14px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
    .btn.secondary{ background:#f1f5f9; color:#0f1724; border:1px solid #e6eefb; padding:10px 12px; text-decoration:none; }

    .errores{ background:#fff5f5; color:#b91c1c; padding:12px; border-radius:8px; border:1px solid #fecaca; margin-bottom:12px;}
    .exito{ background:#f0fdf4; color:#065f46; padding:12px; border-radius:8px; border:1px solid #bbf7d0; margin-bottom:12px;}

    @media (max-width:900px){ .container{ margin:12px } .row{ flex-direction:column } }
</style>
</head>
<body>
<header class="topbar" role="banner">
    <div class="topbar__inner">
        <div class="brand" aria-label="Clínica Nutricional">
            <span class="brand__logo" aria-hidden="true">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" focusable="false" aria-hidden="true"><path d="M10.5 3a1 1 0 0 0-1 1v5H4.5a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h5v5a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-5h5a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1h-5V4a1 1 0 0 0-1-1h-4z"/></svg>
            </span>
            <span class="brand__name">Clínica Nutricional</span>
        </div>
        <div class="topbar__actions">
            <a href="Menuprincipal.php" title="Volver al menú">← Menú Principal</a>
            <span class="user-pill" title="<?= e($user_name) ?>">
                <span class="user-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user_name,0,1,'UTF-8'))) ?></span>
                <span><?= e($user_name) ?></span>
            </span>
            <a href="Login.php" title="Cerrar sesión">Salir</a>
        </div>
    </div>
</header>

<div class="container">
    <div class="header-row">
        <div class="avatar-large" aria-hidden="true"></div>
        <div class="title-block">
            <h1>Registro de Pacientes</h1>
            <p>Complete los datos para registrar un nuevo paciente.</p>
        </div>
    </div>

    <?php if (!empty($errores)): ?>
        <div class="errores">
            <?php foreach ($errores as $e): ?><div>- <?= e($e) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($exito): ?><div class="exito"><?= e($exito) ?></div><?php endif; ?>

    <div class="card">
        <form method="post" novalidate>
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

            <div class="row">
                <div>
                    <label>Nombre completo</label>
                    <input type="text" value="<?= e($user_name) ?>" readonly>
                </div>
                <div>
                    <label>DNI (13 dígitos)</label>
                    <input type="text" name="dni" pattern="\d{13}" maxlength="13" placeholder="0823200610125" required>
                </div>
            </div>

            <div class="row">
                <div>
                    <label>Fecha de nacimiento</label>
                    <input type="date" name="fecha_nacimiento" required onchange="calcularEdad()">
                </div>
                <div>
                    <label>Edad</label>
                    <input type="text" id="edad" readonly>
                </div>
            </div>

            <div class="row">
                <div>
                    <label>Teléfono (8 dígitos)</label>
                    <input type="text" name="telefono" pattern="\d{8}" maxlength="8" placeholder="99553364" required>
                </div>
                <div>
                    <label>Talla (cm)</label>
                    <input type="number" step="0.01" name="talla" placeholder="170.5">
                </div>
            </div>

            <div class="row">
                <div>
                    <label>Peso (kg)</label>
                    <input type="number" step="0.01" name="peso" placeholder="70.5">
                </div>
                <div>
                    <label>Estatura (cm)</label>
                    <input type="number" step="0.01" name="estatura" placeholder="170.5">
                </div>
            </div>

            <div class="row">
                <div>
                    <label>Masa muscular (kg)</label>
                    <input type="number" step="0.01" name="masa_muscular" placeholder="50.0">
                </div>
                <div>
                    <label>Enfermedades de base</label>
                    <textarea name="enfermedades_base" rows="2" placeholder="Describa las enfermedades de base"></textarea>
                </div>
            </div>

            <div class="row">
                <div style="flex:1 1 100%">
                    <label>Medicamentos</label>
                    <textarea name="medicamentos" rows="2" placeholder="Liste los medicamentos"></textarea>
                </div>
            </div>

            <div class="actions">
                <a href="Menuprincipal.php" class="btn secondary" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;">Volver</a>
                <button type="submit" class="btn">Guardar</button>
            </div>
        </form