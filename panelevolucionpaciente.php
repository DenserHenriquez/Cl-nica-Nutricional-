<?php
session_start();
if (!isset($_SESSION['id_usuarios'])) { header('Location: index.php'); exit; }
require_once __DIR__ . '/db_connection.php';
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$idPaciente = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idPaciente <= 0) {
    if ($stmtPid = $conexion->prepare('SELECT id_pacientes FROM pacientes WHERE id_usuarios = ? LIMIT 1')) {
        $uid = (int)$_SESSION['id_usuarios'];
        $stmtPid->bind_param('i', $uid);
        $stmtPid->execute();
        $resPid = $stmtPid->get_result();
        if ($rowPid = $resPid->fetch_assoc()) $idPaciente = (int)$rowPid['id_pacientes'];
        $stmtPid->close();
    }
    if ($idPaciente <= 0) { die('Paciente no encontrado.'); }
}

// Datos del paciente
$paciente = null;
if ($stmt = $conexion->prepare('SELECT nombre_completo, edad, telefono FROM pacientes WHERE id_pacientes = ? LIMIT 1')) {
    $stmt->bind_param('i', $idPaciente);
    $stmt->execute();
    $res = $stmt->get_result();
    $paciente = $res->fetch_assoc();
    $stmt->close();
}
if (!$paciente) { die('Paciente no encontrado.'); }

// Expediente
$expediente = [];
if ($stmt = $conexion->prepare('SELECT fecha_registro, peso, IMC FROM expediente WHERE id_pacientes = ? ORDER BY fecha_registro ASC')) {
    $stmt->bind_param('i', $idPaciente);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $expediente[] = $row; }
    $stmt->close();
}

// Ejercicios
$ejercicios = [];
if ($stmt = $conexion->prepare('SELECT fecha, tiempo FROM ejercicios WHERE id_pacientes = ? ORDER BY fecha ASC')) {
    $stmt->bind_param('i', $idPaciente);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $ejercicios[] = $row; }
    $stmt->close();
}

// Alimentos
$alimentos = [];
if ($stmt = $conexion->prepare('SELECT fecha, tipo_comida, descripcion FROM alimentos_registro WHERE paciente_id = ? ORDER BY fecha DESC, hora DESC LIMIT 10')) {
    $stmt->bind_param('i', $idPaciente);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $alimentos[] = $row; }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Evolución del Paciente</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    :root {
        --primary-900: #0d47a1;
        --primary-700: #1565c0;
        --primary-500: #1976d2;
        --primary-300: #42a5f5;
        --white: #ffffff;
        --text-900: #0b1b34;
        --text-700: #22426e;
        --shadow: 0 10px 25px rgba(13, 71, 161, 0.18);
        --radius-lg: 16px;
        --radius-md: 12px;
        --radius-sm: 10px;
    }
    * { box-sizing: border-box; }
    body {
        font-family: 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(180deg, #f7fbff 0%, #f3f8ff 100%);
        margin: 0;
        color: var(--text-900);
    }
    a { color: inherit; text-decoration: none; }
    /* Barra superior estilo menú */
    .topbar { position: sticky; top:0; z-index:50; background: linear-gradient(90deg, var(--primary-900), var(--primary-700)); color: var(--white); box-shadow: var(--shadow); }
    .topbar__inner { max-width: 1200px; margin:0 auto; padding:12px 20px; display:flex; align-items:center; justify-content: space-between; gap:16px; }
    .brand { display:flex; align-items:center; gap:12px; font-weight:700; letter-spacing:.3px; }
    .brand__logo { width:36px; height:36px; border-radius:50%; background: radial-gradient(120% 120% at 20% 20%, var(--primary-300), var(--primary-900)); display:inline-flex; align-items:center; justify-content:center; box-shadow: 0 6px 14px rgba(0,0,0,.15) inset, 0 2px 8px rgba(255,255,255,.25); }
    .brand__logo svg { width:22px; height:22px; fill:#fff; opacity:.95; }
    .brand__name { font-size:1.05rem; }
    .topbar__actions { display:flex; align-items:center; gap:10px; }
    .topbar__actions a { display:inline-block; padding:8px 14px; background: rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.22); border-radius:999px; color:#fff; font-size:.92rem; transition: all .2s ease; }
    .topbar__actions a:hover { background: rgba(255,255,255,.22); transform: translateY(-1px); }
    .user-pill { display:inline-flex; align-items:center; gap:10px; padding:6px 10px 6px 6px; background: rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.24); border-radius:999px; color:#fff; font-weight:600; letter-spacing:.2px; white-space:nowrap; }
    .user-avatar { width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background: linear-gradient(135deg, rgba(255,255,255,.35), rgba(255,255,255,.05)); color: var(--primary-900); font-weight:800; border:1px solid rgba(255,255,255,.45); }

    .container {
        max-width: 1100px;
        margin: 24px auto 40px;
        background: #ffffff;
        border-radius: var(--radius-lg);
        box-shadow: 0 6px 16px rgba(13, 71, 161, 0.10);
        padding: 20px;
    }

    .paciente-header { display:flex; align-items:center; gap:15px; border-bottom:1px solid #e2e8f0; padding-bottom:15px; margin-bottom:25px; }
    .avatar { width:70px; height:70px; border-radius:50%; background: url('https://cdn-icons-png.flaticon.com/512/3135/3135715.png') center/cover; }
    .paciente-info h2 { margin:0; font-size:1.2rem; color:#1e293b; }
    .paciente-info p { margin:2px 0; color:#475569; font-size:.95rem; }

    .chart-section { margin-bottom: 40px; }
    .chart-card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:15px; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
    h3 { color:#1e293b; font-size:1rem; margin-bottom:10px; }
    table { width:100%; border-collapse: collapse; margin-top:15px; }
    th, td { border-bottom:1px solid #e2e8f0; padding:8px 10px; font-size:.9rem; color:#334155; }
    th { background-color:#f1f5f9; text-align:left; }
    td { background-color:#fff; }
    td[colspan] { text-align:center; color:#94a3b8; }
</style>
</head>
<body>
<header class="topbar" role="banner">
    <div class="topbar__inner">
        <div class="brand" aria-label="Clínica Nutricional">
            <span class="brand__logo" aria-hidden="true">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" focusable="false" aria-hidden="true">
                    <path d="M10.5 3a1 1 0 0 0-1 1v5H4.5a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h5v5a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-5h5a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1h-5V4a1 1 0 0 0-1-1h-4z"/>
                </svg>
            </span>
            <span class="brand__name">Clínica Nutricional</span>
        </div>
        <div class="topbar__actions">
            <a href="Menuprincipal.php" title="Volver al menú">← Menú Principal</a>
            <span class="user-pill" title="Usuario actual">
                <span class="user-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($_SESSION['nombre'] ?? ($_SESSION['usuario'] ?? 'U'), 0, 1), 'UTF-8')) ?></span>
                <span><?= e($_SESSION['nombre'] ?? ($_SESSION['usuario'] ?? 'Usuario')) ?></span>
            </span>
            <a href="Login.php" title="Cerrar sesión">Salir</a>
        </div>
    </div>
</header>

<div class="container">

    <div class="paciente-header">
        <div class="avatar"></div>
        <div class="paciente-info">
            <h2><?= e($paciente['nombre_completo']) ?></h2>
            <p>Edad: <?= e((string)$paciente['edad']) ?> años</p>
            <p>Teléfono: <?= e($paciente['telefono']) ?></p>
        </div>
    </div>

    <div class="chart-section">
        <h3>Evolución del Peso e IMC</h3>
        <div class="chart-card">
            <canvas id="chartPesoIMC"></canvas>
        </div>
    </div>

    <div class="chart-section">
        <h3>Evolución de Ejercicios</h3>
        <div class="chart-card">
            <canvas id="chartEjercicios"></canvas>
        </div>
    </div>

    <div class="chart-section">
        <h3>Últimos Registros de Alimentos</h3>
        <div class="chart-card">
            <table>
                <thead>
                    <tr><th>Fecha</th><th>Tipo de comida</th><th>Descripción</th></tr>
                </thead>
                <tbody>
                    <?php if ($alimentos): foreach ($alimentos as $a): ?>
                    <tr>
                        <td><?= e($a['fecha']) ?></td>
                        <td><?= e(ucfirst($a['tipo_comida'])) ?></td>
                        <td><?= e($a['descripcion']) ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="3">Sin registros</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const exp = <?php echo json_encode($expediente, JSON_UNESCAPED_UNICODE); ?>;
const ej = <?php echo json_encode($ejercicios, JSON_UNESCAPED_UNICODE); ?>;

const labelsExp = exp.map(r => r.fecha_registro);
const pesos = exp.map(r => parseFloat(r.peso) || null);
const imcs = exp.map(r => parseFloat(r.IMC) || null);

new Chart(document.getElementById('chartPesoIMC'), {
    type: 'line',
    data: {
        labels: labelsExp,
        datasets: [
            { label: 'Peso (kg)', data: pesos, borderColor: '#2563eb', backgroundColor:'rgba(37,99,235,.1)', tension:.4 },
            { label: 'IMC', data: imcs, borderColor: '#16a34a', backgroundColor:'rgba(22,163,74,.1)', tension:.4 }
        ]
    },
    options: { responsive:true, plugins:{ legend:{ position:'bottom' } }, scales:{ y:{ beginAtZero:false } } }
});

const labelsEj = ej.map(r => r.fecha);
const tiempos = ej.map(r => parseInt(r.tiempo,10) || 0);

new Chart(document.getElementById('chartEjercicios'), {
    type: 'bar',
    data: { labels: labelsEj, datasets: [{ label: 'Minutos de ejercicio', data: tiempos, backgroundColor:'rgba(244,114,182,.7)' }] },
    options: { responsive:true, plugins:{ legend:{ position:'bottom' } }, scales:{ y:{ beginAtZero:true } } }
});
</script>
</body>
</html>
