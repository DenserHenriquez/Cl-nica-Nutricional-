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
    body {
        font-family: 'Segoe UI', Roboto, sans-serif;
        background-color: #f1f5f9;
        margin: 0;
    }
    header {
        background-color: #1e3a8a;
        color: white;
        padding: 10px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    header h1 { font-size: 1rem; margin: 0; }

    .container {
        max-width: 1100px;
        margin: 40px auto;
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        padding: 20px;
        position: relative;
    }

    /* Botón de regreso en esquina superior derecha */
    .btn-top {
        position: absolute;
        top: 20px;
        right: 20px;
        background-color: #2563eb;
        color: white;
        text-decoration: none;
        padding: 8px 14px;
        border-radius: 8px;
        font-weight: 600;
        transition: background 0.3s;
    }
    .btn-top:hover {
        background-color: #1d4ed8;
    }

    .paciente-header {
        display: flex;
        align-items: center;
        gap: 15px;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 15px;
        margin-bottom: 25px;
    }
    .avatar {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: url('https://cdn-icons-png.flaticon.com/512/3135/3135715.png') center/cover;
    }
    .paciente-info h2 {
        margin: 0;
        font-size: 1.2rem;
        color: #1e293b;
    }
    .paciente-info p {
        margin: 2px 0;
        color: #475569;
        font-size: 0.95rem;
    }

    .chart-section {
        margin-bottom: 40px;
    }
    .chart-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 15px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    h3 {
        color: #1e293b;
        font-size: 1rem;
        margin-bottom: 10px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    th, td {
        border-bottom: 1px solid #e2e8f0;
        padding: 8px 10px;
        font-size: 0.9rem;
        color: #334155;
    }
    th {
        background-color: #f1f5f9;
        text-align: left;
    }
    td {
        background-color: white;
    }
    td[colspan] {
        text-align: center;
        color: #94a3b8;
    }
</style>
</head>
<body>
<header>
    <h1>Clínica Nutricional</h1>
    <div>Usuario: <?= e($_SESSION['usuario'] ?? 'anthony') ?></div>
</header>

<div class="container">
    <a href="Menuprincipal.php" class="btn-top">← Menú Principal</a>

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
