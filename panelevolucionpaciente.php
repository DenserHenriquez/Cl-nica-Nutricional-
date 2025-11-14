<?php 
session_start();
if (!isset($_SESSION['id_usuarios'])) { header('Location: index.php'); exit; }
require_once __DIR__ . '/db_connection.php';
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$idPaciente = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idPaciente <= 0) {
    if ($stmtPid = $conexion->prepare('SELECT id_pacientes FROM pacientes WHERE id_usuarios = ? LIMIT 1')) {
        $stmtPid->bind_param('i', $_SESSION['id_usuarios']);
        $stmtPid->execute();
        $resPid = $stmtPid->get_result();
        if ($rowPid = $resPid->fetch_assoc()) { $idPaciente = (int)$rowPid['id_pacientes']; }
        $stmtPid->close();
    }
    if ($idPaciente <= 0) { die('Paciente no encontrado.'); }
}

// Datos del paciente
$paciente = null;
if ($stmt = $conexion->prepare('SELECT nombre_completo, edad, telefono, DNI FROM pacientes WHERE id_pacientes = ? LIMIT 1')) {
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
    while ($row = $res->fetch_assoc()) {
        $expediente[] = [
            'fecha_registro' => $row['fecha_registro'] ?? '',
            'peso' => floatval($row['peso'] ?? 0),
            'IMC' => floatval($row['IMC'] ?? 0)
        ];
    }
    $stmt->close();
}

// Ejercicios
$ejercicios = [];
$sqlEje = 'SELECT fecha, tiempo FROM ejercicios WHERE paciente_id = ? ORDER BY fecha ASC';
if (!($stmt = @$conexion->prepare($sqlEje))) {
    $sqlEje = 'SELECT fecha, tiempo FROM ejercicios WHERE id_pacientes = ? ORDER BY fecha ASC';
    $stmt = $conexion->prepare($sqlEje);
}
if ($stmt) {
    $stmt->bind_param('i', $idPaciente);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { 
        $ejercicios[] = [
            'fecha' => $row['fecha'] ?? '',
            'tiempo' => intval($row['tiempo'] ?? 0)
        ];
    }
    $stmt->close();
}

// Alimentos registrados (últimos 15 registros)
$alimentos = [];
$sqlAli = 'SELECT fecha, tipo_comida, descripcion, hora, foto_path FROM alimentos_registro WHERE id_pacientes = ? ORDER BY fecha DESC, hora DESC LIMIT 15';
$stmtAli = $conexion->prepare($sqlAli);
if ($stmtAli) {
    $stmtAli->bind_param('i', $idPaciente);
    $stmtAli->execute();
    $resAli = $stmtAli->get_result();
    while ($rowAli = $resAli->fetch_assoc()) {
        $alimentos[] = $rowAli;
    }
    $stmtAli->close();
}

// Estadísticas
$pesoActual = count($expediente) > 0 ? end($expediente)['peso'] : 0;
$pesoInicial = count($expediente) > 0 ? $expediente[0]['peso'] : 0;
$imcActual = count($expediente) > 0 ? end($expediente)['IMC'] : 0;
$totalEjercicios = count($ejercicios);
$tiempoTotalEjercicio = array_sum(array_column($ejercicios, 'tiempo'));
$cambio = $pesoActual - $pesoInicial;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Panel de Evolución - <?= e($paciente['nombre_completo']) ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

  <style>
    :root{
      --brand-primary:#198754;
      --brand-primary-dark:#0b5ed7;
      --brand-bg:#f5f7fb;
      --brand-surface:#ffffff;
      --brand-border:#e9ecef;
      --brand-muted:#6c757d;
      --brand-success:#20c997;
      --brand-purple:#6f42c1;
    }
    body{ background:var(--brand-bg); font-family:'Segoe UI',sans-serif; }
    .header-section {
      background: linear-gradient(135deg, #198754 0%, #146c43 100%);
      color: white;
      padding: 2rem 0;
      margin-bottom: 2rem;
    }
    .header-section h1 {
      font-size: 2.5rem;
      font-weight: 700;
    }
    .header-section p {
      font-size: 1.1rem;
      opacity: 0.9;
    }
    .medical-icon {
      font-size: 3rem;
      margin-bottom: 1rem;
      color: #ffffff;
    }
    .header-card{
      background: linear-gradient(135deg, #198754 0%, #146c43 100%);
      color:#fff; border-radius:15px; padding:2rem; margin-bottom:2rem;
    }
    .btn-back{ 
      background:#fff; color:#0d6efd; border:2px solid #fff;
      border-radius:10px; padding:.5rem 1.25rem; font-weight:600; transition:.2s;
      text-decoration:none; display:inline-block;
    }
    .btn-back:hover{ background:#0d6efd; color:#fff; border-color:#0d6efd; transform:translateX(-4px); box-shadow: 0 0 15px rgba(13, 110, 253, 0.6); }
    
    .stat-card{
      background:var(--brand-surface); border-radius:15px; padding:1.5rem;
      box-shadow:0 2px 8px rgba(0,0,0,.06); border:1px solid var(--brand-border);
      margin-bottom:1.5rem; transition:transform 0.3s;
    }
    .stat-card:hover{ transform:translateY(-5px); box-shadow:0 5px 20px rgba(0,0,0,.12); }
    .stat-icon{
      width:50px; height:50px; border-radius:12px; 
      display:flex; align-items:center; justify-content:center;
      font-size:1.5rem;
    }
    .stat-icon.red{ background:linear-gradient(135deg,#ff6b6b 0%,#ee5a6f 100%); color:white; }
    .stat-icon.blue{ background:linear-gradient(135deg,#4facfe 0%,#00f2fe 100%); color:white; }
    .stat-icon.green{ background:linear-gradient(135deg,#43e97b 0%,#38f9d7 100%); color:white; }
    .stat-icon.purple{ background:linear-gradient(135deg,#a29bfe 0%,#6c5ce7 100%); color:white; }
    .stat-value{ font-size:1.8rem; font-weight:700; margin:0.5rem 0; }
    .stat-label{ color:var(--brand-muted); font-size:0.9rem; text-transform:uppercase; letter-spacing:0.5px; }
    .recent-date{ font-size:0.85rem; color:var(--brand-muted); }
    
    .chart-container{ 
      background:var(--brand-surface); border-radius:15px; padding:1.5rem;
      box-shadow:0 2px 8px rgba(0,0,0,.06); border:1px solid var(--brand-border); 
      height:420px; margin-bottom:1.5rem;
    }
    .chart-wrapper{ position:relative; height:330px; }
    .no-data{ 
      display:flex; align-items:center; justify-content:center; 
      height:330px; color:var(--brand-muted); font-size:1.1rem;
    }
    
    .alimentos-section{
      background:var(--brand-surface); border-radius:15px; padding:1.5rem;
      box-shadow:0 2px 8px rgba(0,0,0,.06); border:1px solid var(--brand-border);
      margin-top:2rem;
    }
    .alimento-card{
      background:#fff; border:1px solid var(--brand-border); border-radius:10px;
      padding:1rem; margin-bottom:1rem; transition:transform 0.2s;
    }
    .alimento-card:hover{ transform:translateX(5px); box-shadow:0 3px 10px rgba(0,0,0,.08); }
    .tipo-badge{
      padding:0.25rem 0.75rem; border-radius:20px; font-size:0.85rem; font-weight:600;
    }
    .tipo-desayuno{ background:#fff3cd; color:#856404; }
    .tipo-almuerzo{ background:#d1ecf1; color:#0c5460; }
    .tipo-cena{ background:#f8d7da; color:#721c24; }
    .tipo-snack{ background:#d4edda; color:#155724; }
    .alimento-img{
      width:80px; height:80px; object-fit:cover; border-radius:8px; border:2px solid var(--brand-border);
    }
  </style>
</head>
<body>

<!-- Header Section -->
<div class="header-section">
  <div class="container text-center">
    <div class="medical-icon">
      <i class="bi bi-bar-chart-line"></i>
    </div>
    <h1>Panel de Evolución del Paciente</h1>
    <p>Nutricionista | Monitorea el progreso y evolución de tus pacientes.</p>
  </div>
</div>

<div class="container-fluid py-4">
  <!-- Header -->
  <div class="header-card d-flex justify-content-between align-items-center flex-wrap">
    <div>
      <h3 class="mb-1"><?= e($paciente['nombre_completo']) ?></h3>
      <small>Edad: <?= e($paciente['edad']) ?> años • DNI: <?= e($paciente['DNI']) ?> • Tel: <?= e($paciente['telefono']) ?></small>
    </div>
    <a class="btn-back" href="Activar_desactivar_paciente.php">
      <i class="bi bi-arrow-left me-2"></i>Volver a Pacientes
    </a>
  </div>

  <!-- Estadísticas principales -->
  <div class="row g-4 mb-4">
    <div class="col-md-3">
      <div class="stat-card">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon red">
            <i class="bi bi-heart-pulse"></i>
          </div>
          <div>
            <div class="stat-label">Peso Actual</div>
            <div class="stat-value"><?= $pesoActual > 0 ? number_format($pesoActual, 1) : '--' ?> <small style="font-size:1rem;">kg</small></div>
            <?php if (count($expediente) > 0): ?>
              <div class="recent-date">Último registro: <?= date('d/m/Y', strtotime(end($expediente)['fecha_registro'])) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="stat-card">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon blue">
            <i class="bi bi-clipboard-data"></i>
          </div>
          <div>
            <div class="stat-label">IMC Actual</div>
            <div class="stat-value"><?= $imcActual > 0 ? number_format($imcActual, 1) : '--' ?></div>
            <?php if ($imcActual > 0): ?>
              <div class="recent-date">
                <?php 
                  $categoria = 'Normal';
                  if ($imcActual < 18.5) $categoria = 'Bajo peso';
                  elseif ($imcActual >= 25 && $imcActual < 30) $categoria = 'Sobrepeso';
                  elseif ($imcActual >= 30) $categoria = 'Obesidad';
                  echo $categoria;
                ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="stat-card">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon green">
            <i class="bi bi-graph-up-arrow"></i>
          </div>
          <div>
            <div class="stat-label">Progreso de Peso</div>
            <div class="stat-value">
              <?php 
                if ($cambio != 0) {
                  echo ($cambio > 0 ? '+' : '') . number_format($cambio, 1);
                  echo ' <small style="font-size:1rem;">kg</small>';
                } else {
                  echo '--';
                }
              ?>
            </div>
            <div class="recent-date">Desde inicio del tratamiento</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="stat-card">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon purple">
            <i class="bi bi-trophy"></i>
          </div>
          <div>
            <div class="stat-label">Total Ejercicios</div>
            <div class="stat-value"><?= $totalEjercicios ?></div>
            <div class="recent-date"><?= $tiempoTotalEjercicio ?> min acumulados</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Gráficas -->
  <div class="row g-4">
    <!-- Gráfica Peso/IMC -->
    <div class="col-lg-6">
      <div class="chart-container">
        <h5 class="mb-3"><i class="bi bi-graph-up text-primary me-2"></i>Evolución de Peso e IMC</h5>
        <?php if (count($expediente) > 0): ?>
          <div class="chart-wrapper">
            <canvas id="chartPesoIMC"></canvas>
          </div>
        <?php else: ?>
          <div class="no-data">
            <i class="bi bi-inbox me-2"></i>Sin datos de expediente
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Gráfica Ejercicios -->
    <div class="col-lg-6">
      <div class="chart-container">
        <h5 class="mb-3"><i class="bi bi-activity text-success me-2"></i>Tiempo de Ejercicio (Últimos 7 registros)</h5>
        <?php if (count($ejercicios) > 0): ?>
          <div class="chart-wrapper">
            <canvas id="chartEjercicios"></canvas>
          </div>
        <?php else: ?>
          <div class="no-data">
            <i class="bi bi-inbox me-2"></i>Sin registros de ejercicio
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Registro de Alimentos -->
  <div class="alimentos-section">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0"><i class="bi bi-egg-fried text-warning me-2"></i>Registro de Alimentos (Últimos 15)</h5>
      <a href="Resgistro_Alimentos.php" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-plus-circle me-1"></i>Ir a Registro Completo
      </a>
    </div>

    <?php if (empty($alimentos)): ?>
      <div class="text-center py-4 text-muted">
        <i class="bi bi-inbox" style="font-size:3rem;"></i>
        <p class="mt-2">No hay registros de alimentos aún</p>
      </div>
    <?php else: ?>
      <?php foreach ($alimentos as $ali): ?>
        <div class="alimento-card">
          <div class="row align-items-center">
            <div class="col-auto">
              <?php if (!empty($ali['foto_path']) && file_exists($ali['foto_path'])): ?>
                <img src="<?= e($ali['foto_path']) ?>" alt="Foto alimento" class="alimento-img">
              <?php else: ?>
                <div class="alimento-img d-flex align-items-center justify-content-center" style="background:#f8f9fa;">
                  <i class="bi bi-image text-muted" style="font-size:2rem;"></i>
                </div>
              <?php endif; ?>
            </div>
            <div class="col">
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="tipo-badge tipo-<?= e($ali['tipo_comida']) ?>">
                  <?= e(ucfirst($ali['tipo_comida'])) ?>
                </span>
                <small class="text-muted">
                  <i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($ali['fecha'])) ?>
                  <i class="bi bi-clock ms-2 me-1"></i><?= date('H:i', strtotime($ali['hora'])) ?>
                </small>
              </div>
              <p class="mb-0" style="color:#495057;"><?= e($ali['descripcion']) ?></p>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const exp = <?= json_encode($expediente, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE) ?>;
const eje = <?= json_encode($ejercicios, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE) ?>;

// Paleta
const cPrimary = '#198754';
const cPrimaryFill = 'rgba(25,135,84,.12)';
const cPurple = '#6f42c1';
const cPurpleFill = 'rgba(111,66,193,.12)';
const cSuccess = '#20c997';
const gridColor = '#e9ecef';
const tickColor = '#6c757d';

// Gráfica Peso/IMC
if (exp.length > 0) {
  const ctxPeso = document.getElementById('chartPesoIMC');
  if (ctxPeso) {
    new Chart(ctxPeso, {
      type: 'line',
      data: {
        labels: exp.map(e => {
          const raw = e.fecha_registro || '';
          const safe = typeof raw === 'string' ? raw.replace(' ', 'T') : raw;
          const d = new Date(safe);
          return isNaN(d.getTime()) ? (e.fecha_registro || '') : d.toLocaleDateString('es-ES');
        }),
        datasets: [
          {
            label: 'Peso (kg)',
            data: exp.map(e => parseFloat(e.peso) || 0),
            borderColor: cPrimary,
            backgroundColor: cPrimaryFill,
            pointRadius: 4,
            pointHoverRadius: 6,
            tension: .35,
            fill: true,
            borderWidth: 2
          },
          {
            label: 'IMC',
            data: exp.map(e => parseFloat(e.IMC) || 0),
            borderColor: cPurple,
            backgroundColor: cPurpleFill,
            pointRadius: 4,
            pointHoverRadius: 6,
            tension: .35,
            fill: true,
            borderWidth: 2
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: { mode: 'index', intersect: false }
        },
        scales: {
          x: { grid: { color: gridColor }, ticks: { color: tickColor } },
          y: { grid: { color: gridColor }, ticks: { color: tickColor }, beginAtZero: false }
        }
      }
    });
  }
}

// Gráfica Ejercicios
if (eje.length > 0) {
  const ultimos7 = eje.slice(-7);
  const ctxEje = document.getElementById('chartEjercicios');
  if (ctxEje) {
    new Chart(ctxEje, {
      type: 'bar',
      data: {
        labels: ultimos7.map(e => {
          try {
            return new Date(e.fecha).toLocaleDateString('es-ES');
          } catch {
            return e.fecha || '';
          }
        }),
        datasets: [{
          label: 'Minutos',
          data: ultimos7.map(e => parseInt(e.tiempo) || 0),
          backgroundColor: 'rgba(32,201,151,.85)',
          borderColor: cSuccess,
          borderWidth: 1.5,
          borderRadius: 8,
          maxBarThickness: 40
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { mode: 'index', intersect: false }
        },
        scales: {
          x: { grid: { display: false }, ticks: { color: tickColor } },
          y: { grid: { color: gridColor }, ticks: { color: tickColor }, beginAtZero: true }
        }
      }
    });
  }
}
</script>
</body>
</html>
<?php $conexion->close(); ?>
