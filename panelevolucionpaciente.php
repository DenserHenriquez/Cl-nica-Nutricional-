<?php 
session_start();
if (!isset($_SESSION['id_usuarios'])) { header('Location: index.php'); exit; }
require_once __DIR__ . '/db_connection.php';

$userRole = $_SESSION['rol'] ?? 'Paciente';

// ========== NUEVA LÓGICA DE BÚSQUEDA (solo Admin/Medico) ==========
$searchResults = [];
$searchQuery = '';
$isSearchMode = false;

if (($userRole === 'Administrador' || $userRole === 'Medico') && isset($_GET['q']) && !empty(trim($_GET['q']))) {
    $searchQuery = trim($_GET['q']);
    $isSearchMode = true;
    
    $stmt = $conexion->prepare('SELECT id_pacientes, nombre_completo, DNI FROM pacientes WHERE nombre_completo LIKE ? OR DNI LIKE ? ORDER BY nombre_completo ASC LIMIT 10');
    $likeTerm = '%' . $searchQuery . '%';
    $stmt->bind_param('ss', $likeTerm, $likeTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $searchResults[] = $row;
    }
    $stmt->close();
    
    // Si solo 1 resultado, redirect directo
    if (count($searchResults) === 1) {
        header('Location: panelevolucionpaciente.php?id=' . $searchResults[0]['id_pacientes']);
        exit;
    }
}

class EvaluadorNutricional {
    
    public static function clasificarIMC($imc) {
        if ($imc < 18.5) return ['label' => 'Delgadez I', 'color' => '#orange'];
        if ($imc < 25.0) return ['label' => 'Normal', 'color' => '#28a745']; // Verde
        if ($imc < 30.0) return ['label' => 'Sobrepeso', 'color' => '#ffc107']; // Amarillo
        if ($imc < 35.0) return ['label' => 'Obesidad I', 'color' => '#dc3545']; // Rojo claro
        if ($imc < 40.0) return ['label' => 'Obesidad II', 'color' => '#c82333']; // Rojo
        return ['label' => 'Obesidad III', 'color' => '#721c24']; // Rojo oscuro
    }

    public static function clasificarGrasaVisceral($valor) {
        if ($valor <= 9) return ['label' => 'En forma', 'color' => '#28a745', 'nivel' => 1];
        if ($valor <= 14) return ['label' => 'Alerta', 'color' => '#ffc107', 'nivel' => 2];
        return ['label' => 'Peligro', 'color' => '#dc3545', 'nivel' => 3];
    }

    public static function clasificarPorcentajeGrasa($sexo, $edad, $grasa) {
        // Lógica simplificada basada en la tabla de la imagen
        // $sexo: 'M' o 'H'
        if ($sexo == 'M') {
            if ($grasa < 21) return 'Bajo';
            if ($grasa <= 33) return 'Recomendado';
            if ($grasa <= 38) return 'Alto';
            return 'Muy Alto';
        } else {
            if ($grasa < 8) return 'Bajo';
            if ($grasa <= 20) return 'Recomendado';
            if ($grasa <= 25) return 'Alto';
            return 'Muy Alto';
        }
    }
}

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Identificar paciente según parámetro o sesión (solo si no search mode con multiple)
$idPaciente = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$noRegistrado = false;
if (!$isSearchMode || count($searchResults) === 0) {
  if ($idPaciente <= 0) {
    if ($stmtPid = $conexion->prepare('SELECT id_pacientes FROM pacientes WHERE id_usuarios = ? LIMIT 1')) {
      $stmtPid->bind_param('i', $_SESSION['id_usuarios']);
      $stmtPid->execute();
      $resPid = $stmtPid->get_result();
      if ($rowPid = $resPid->fetch_assoc()) { $idPaciente = (int)$rowPid['id_pacientes']; }
      $stmtPid->close();
    }
    if ($idPaciente <= 0) {
      if ($userRole === 'Paciente') {
        $noRegistrado = true; // Paciente logueado pero aún sin registro en tabla pacientes
      } else {
        $idPaciente = 0; // Para admin/medico sin ID, mostrar search vacío
      }
    }
  }
}

// Datos del paciente (solo si registrado y no multiple search)
$paciente = null;
if (!$isSearchMode || count($searchResults) === 0) {
  if (!$noRegistrado && $idPaciente > 0) {
    if ($stmt = $conexion->prepare('SELECT nombre_completo, edad, telefono, DNI, referencia_medica FROM pacientes WHERE id_pacientes = ? LIMIT 1')) {
      $stmt->bind_param('i', $idPaciente);
      $stmt->execute();
      $res = $stmt->get_result();
      $paciente = $res->fetch_assoc();
      $stmt->close();
    }
    if (!$paciente) { 
      if ($userRole === 'Paciente') $noRegistrado = true;
      else $paciente = ['nombre_completo' => 'Paciente no encontrado'];
    }
  } else if ($userRole === 'Paciente') {
    // Construir datos básicos para mostrar nombre desde sesión sin provocar errores
    $paciente = [
      'nombre_completo' => $_SESSION['nombre'] ?? 'Paciente',
      'edad' => '--',
      'telefono' => '--',
      'DNI' => '--'
    ];
  }
}

// Resto de datos solo si paciente específico cargado
$expediente = []; $ejercicios = []; $alimentos = [];
if (!$isSearchMode && !$noRegistrado && $idPaciente > 0) {
  // Expediente
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

  // Alimentos
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

  // Grasa Visceral: table consultas_medicas not found in schema - using defaults
  $grasaVisceralActual = 0;
  $fechaGrasaVisceral = '';
  $grasaResultado = ['label' => 'Sin datos (tabla faltante)', 'color' => '#6c757d', 'nivel' => 0];
  }
}

// Estadísticas
$pesoActual = count($expediente) > 0 ? end($expediente)['peso'] : 0;
$pesoInicial = count($expediente) > 0 ? $expediente[0]['peso'] : 0;
$imcActual = count($expediente) > 0 ? end($expediente)['IMC'] : 0;
$totalEjercicios = count($ejercicios);
$tiempoTotalEjercicio = array_sum(array_column($ejercicios, 'tiempo'));
$cambio = $pesoActual - $pesoInicial;

$resultado = $imcActual > 0 ? EvaluadorNutricional::clasificarIMC($imcActual) : ['label' => 'Sin datos', 'color' => '#6c757d'];
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Panel de Evolución - <?= $isSearchMode ? 'Buscar Paciente' : e($paciente['nombre_completo'] ?? '') ?></title>

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
      padding: 1.1rem 1.6rem;
      margin-bottom: 1rem;
      border-radius: 12px;
    }
    .header-section h1 {
      font-size: 2.2rem;
      font-weight: 700;
      margin: 0;
      line-height: 1.3;
    }
    .header-section p {
      font-size: 1.05rem;
      opacity: 0.92;
      margin: 0;
    }
    .medical-icon {
      font-size: 1.9rem;
      color: #ffffff;
    }
    .header-card{
      background:#fff; border:1px solid var(--brand-border); border-left:4px solid #198754;
      color:#333; border-radius:14px; padding:1.1rem 1.4rem; margin-bottom:1.2rem;
      box-shadow:0 3px 10px rgba(0,0,0,0.04);
    }
    .header-card h3 { color:#0d5132; font-size:1.15rem; }
    .header-card small { color:var(--brand-muted); font-size:.85rem; }
    .btn-green {
      background: #198754 !important;
      color: #fff !important;
      border: 1px solid #198754 !important;
      border-radius: 8px;
      padding: .5rem 1.2rem;
      font-weight: 600;
      transition: .2s;
      text-decoration: none;
    }
    .btn-green:hover {
      background: #146c43 !important;
      color: #fff !important;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(25,135,84,0.3);
    }
    
    /* ========== NUEVOS ESTILOS PARA BUSCADOR ========= */
.search-section {
      background: var(--brand-surface);
      border: 1px solid var(--brand-border);
      border-radius: 12px;
      padding: 1.5rem 1.2rem;
      margin-bottom: 1rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .search-section h5 {
      color: #0d5132;
      font-weight: 700;
      margin-bottom: 1rem;
    }
    .search-results {
      max-height: 400px;
      overflow-y: auto;
    }
    .search-result-item {
      padding: 1rem;
      border: 1px solid #e9ecef;
      border-radius: 10px;
      margin-bottom: 0.75rem;
      transition: all 0.2s;
      background: #fff;
    }
    .search-result-item:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(25,135,84,0.15);
      border-color: #198754;
    }
    .search-result-name {
      font-weight: 700;
      color: #0d5132;
      font-size: 1.1rem;
    }
    .search-result-dni {
      color: #6c757d;
      font-size: 0.95rem;
    }
    .no-results {
      text-align: center;
      padding: 2rem;
      color: var(--brand-muted);
    }
    .no-results i {
      font-size: 3rem;
      opacity: 0.5;
      display: block;
      margin-bottom: 1rem;
    }
    /* Mobile */
    @media (max-width: 576px) {
      .search-section { padding: 1rem; }
      .search-result-item { padding: 0.75rem; }
    }
    
    .stat-card{
      background:var(--brand-surface); border-radius:14px; padding:1.1rem 1.2rem;
      box-shadow:0 3px 10px rgba(0,0,0,.04); border:1px solid var(--brand-border);
      margin-bottom:1rem; transition:transform 0.25s;
    }
    .stat-card:hover{ transform:translateY(-3px); box-shadow:0 6px 18px rgba(0,0,0,.08); }
    .stat-icon{
      width:42px; height:42px; border-radius:10px; 
      display:flex; align-items:center; justify-content:center;
      font-size:1.25rem;
    }
    .stat-icon.red{ background:linear-gradient(135deg,#ff6b6b 0%,#ee5a6f 100%); color:white; }
    .stat-icon.blue{ background:linear-gradient(135deg,#4facfe 0%,#00f2fe 100%); color:white; }
    .stat-icon.green{ background:linear-gradient(135deg,#43e97b 0%,#38f9d7 100%); color:white; }
    .stat-icon.purple{ background:linear-gradient(135deg,#a29bfe 0%,#6c5ce7 100%); color:white; }
    .stat-value{ font-size:1.5rem; font-weight:700; margin:0.3rem 0; }
    .stat-label{ color:var(--brand-muted); font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px; }
    .recent-date{ font-size:0.78rem; color:var(--brand-muted); }
    
    .chart-container{ 
      background:var(--brand-surface); border-radius:14px; padding:1.1rem 1.2rem;
      box-shadow:0 3px 10px rgba(0,0,0,.04); border:1px solid var(--brand-border); 
      height:340px; margin-bottom:1rem;
    }
    .chart-container h5 { font-size:1rem; font-weight:700; color:#0d5132; }
    .chart-wrapper{ position:relative; height:260px; }
    .no-data{ 
      display:flex; align-items:center; justify-content:center; 
      height:260px; color:var(--brand-muted); font-size:1rem;
    }
    
    .alimentos-section{
      background:var(--brand-surface); border-radius:14px; padding:1.1rem 1.2rem;
      box-shadow:0 3px 10px rgba(0,0,0,.04); border:1px solid var(--brand-border);
      margin-top:1.2rem;
    }
    .alimentos-section h5 { font-size:1rem; font-weight:700; color:#0d5132; }
    .alimento-card{
      background:#fff; border:1px solid var(--brand-border); border-radius:8px;
      padding:0.8rem; margin-bottom:0.7rem; transition:transform 0.2s;
    }
    .alimento-card:hover{ transform:translateX(3px); box-shadow:0 3px 8px rgba(0,0,0,.06); }
    .tipo-badge{
      padding:0.2rem 0.6rem; border-radius:20px; font-size:0.78rem; font-weight:600;
    }
    .tipo-desayuno{ background:#fff3cd; color:#856404; }
    .tipo-almuerzo{ background:#d1ecf1; color:#0c5460; }
    .tipo-cena{ background:#f8d7da; color:#721c24; }
    .tipo-snack{ background:#d4edda; color:#155724; }
    .alimento-img{
      width:60px; height:60px; object-fit:cover; border-radius:8px; border:1.5px solid var(--brand-border);
    }
    /* Mobile responsive */
    @media (max-width:576px) {
      .stat-value { font-size:1.2rem; }
      .stat-icon { width:34px; height:34px; border-radius:10px; }
      .stat-icon i { font-size:1rem; }
      .chart-container { height:auto; min-height:280px; padding:0.8rem; }
      .chart-wrapper { height:200px; }
      .no-data { height:200px; }
      .header-section h1 { font-size:1.3rem !important; }
      .header-section p { font-size:.82rem !important; }
      .header-card { flex-direction:column !important; text-align:center; gap:8px; }
      .header-card .d-flex { flex-direction:column; align-items:center !important; }
      .alimento-img { width:45px; height:45px; }
      .alimento-card { padding:0.6rem; }
    }
  </style>
</head>
<body>

<div class="container-fluid py-3 px-3">

<!-- Header Section -->
<div class="header-section d-flex align-items-center gap-3">
    <div class="medical-icon"><i class="bi bi-bar-chart-line"></i></div>
    <div>
        <h1>Panel de Evolución <?= $isSearchMode ? 'del Paciente' : '' ?></h1>
        <p><?= $isSearchMode ? 'Busca pacientes por nombre o DNI' : 'Nutricionista \| Monitorea el progreso y evolución de tus pacientes.' ?></p>
    </div>
</div>

<?php if ($userRole === 'Administrador' || $userRole === 'Medico'): ?>
<!-- ========== FORMULARIO DE BÚSQUEDA ========= -->
<div class="search-section">
    <h5><i class="bi bi-search text-primary me-2"></i>Buscar Paciente</h5>
    <form method="GET" class="row g-2">
        <div class="col-md-10">
            <input type="text" class="form-control form-control-lg" name="q" value="<?= e($searchQuery) ?>" placeholder="Ingrese nombre completo o DNI del paciente..." autofocus>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-green btn-lg w-100">
                <i class="bi bi-search me-2"></i>Buscar
            </button>
        </div>
        <?php if (isset($_GET['id'])): ?>
            <input type="hidden" name="id" value="<?= e($_GET['id']) ?>">
        <?php endif; ?>
    </form>
</div>
<?php endif; ?>

<?php if ($isSearchMode): ?>
<!-- ========== RESULTADOS DE BÚSQUEDA ========= -->
<?php if (count($searchResults) > 0): ?>
    <div class="search-section">
        <h5><i class="bi bi-list-ul me-2"></i>Resultados encontrados (<?= count($searchResults) ?>)</h5>
        <div class="search-results">
            <?php foreach ($searchResults as $result): ?>
                <a href="panelevolucionpaciente.php?id=<?= $result['id_pacientes'] ?>" class="search-result-item text-decoration-none">
                    <div class="search-result-name"><?= e($result['nombre_completo']) ?></div>
                    <div class="search-result-dni"><?= e($result['DNI']) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php else: ?>
    <div class="search-section">
        <div class="no-results">
            <i class="bi bi-search-x"></i>
            <h5>No se encontraron pacientes</h5>
            <p>Intenta con otro nombre o DNI. La búsqueda no es sensible a mayúsculas.</p>
            <a href="panelevolucionpaciente.php" class="btn btn-green btn-outline">Nueva Búsqueda</a>
        </div>
    </div>
<?php endif; ?>

<?php if (count($searchResults) === 0 && $idPaciente > 0): ?>
    <!-- Continuar con paciente específico -->
<?php else: ?>
    <?php return; // No mostrar resto si search multiple o vacío ?>
<?php endif; ?>

<?php endif; // Fin search mode ?>

<!-- Patient info (solo si paciente específico) -->
<?php if (!empty($paciente) && !$isSearchMode): ?>
<div class="header-card d-flex justify-content-between align-items-center flex-wrap">
    <div>
      <h3 class="mb-1"><?= e($paciente['nombre_completo']) ?></h3>
      <small>
        Edad: <?= e($paciente['edad']) ?> años • DNI: <?= e($paciente['DNI']) ?> • Tel: <?= e($paciente['telefono']) ?>
        <?php if (!empty($paciente['referencia_medica'])): ?>
          • Referencia: <?= e($paciente['referencia_medica']) ?>
        <?php endif; ?>
      </small>
    </div>
    <?php if ($userRole !== 'Paciente'): ?>
      <a class="btn-green" href="Activar_desactivar_paciente.php">
        <i class="bi bi-arrow-left me-2"></i>Volver a Pacientes
      </a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (isset($noRegistrado) && $noRegistrado): ?>
  <div class="alert alert-warning border-2" role="alert">
    <strong>Paciente nuevo:</strong> todavía no tienes un registro completo en la clínica. Para comenzar a ver tu evolución debes completar tu registro con un profesional. Una vez registrado aparecerán tus datos, gráficas y registros de alimentos y ejercicios.
  </div>
<?php endif; ?>

<?php if (!$isSearchMode && !empty($paciente) && !$noRegistrado): ?>
<!-- Estadísticas principales -->
<div class="row g-3 mb-3">
  <!-- [ESTADÍSTICAS EXISTENTES INTACTAS - PESO, IMC, TERMÓMETRO, PROGRESO, EJERCICIOS] -->
  <div class="col-md-3">
    <div class="stat-card">
      <div class="d-flex align-items-center gap-3">
        <div class="stat-icon red">
          <i class="bi bi-heart-pulse"></i>
        </div>
        <div>
          <div class="stat-label">Peso Actual</div>
          <div class="stat-value"><?= $pesoActual > 0 ? number_format($pesoActual, 1) : '--' ?> <small style="font-size:.85rem;">kg</small></div>
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
          <div class="stat-value" style="color: <?= $resultado['color'] ?>; font-weight: bold;"><?= $imcActual > 0 ? number_format($imcActual, 1) : '--' ?></div>
          <?php if ($imcActual > 0): ?>
            <div class="recent-date" style="color: <?= $resultado['color'] ?>;">
              <?= $resultado['label'] ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Grasa Visceral Actual -->
  <div class="col-md-3">
    <div class="stat-card">
      <div class="d-flex align-items-center gap-3">
        <div class="stat-icon" style="background: linear-gradient(135deg, <?= $grasaResultado['color'] ?> 0%, <?= $grasaResultado['nivel'] == 3 ? '#c82333' : ( $grasaResultado['nivel'] == 2 ? '#ff9500' : $grasaResultado['color'] ) ?> 100%); color: white;">
          <i class="bi bi-fire"></i>
        </div>
        <div>
          <div class="stat-label">Grasa Visceral</div>
          <div class="stat-value"><?= $grasaVisceralActual > 0 ? number_format($grasaVisceralActual, 1) : '--' ?></div>
          <?php if ($grasaVisceralActual > 0 && $fechaGrasaVisceral): ?>
            <div class="recent-date" style="color: <?= $grasaResultado['color'] ?>;">
              <?= $grasaResultado['label'] ?> • <?= date('d/m/Y', strtotime($fechaGrasaVisceral)) ?>
            </div>
          <?php elseif ($grasaVisceralActual > 0): ?>
            <div class="recent-date" style="color: <?= $grasaResultado['color'] ?>;">
              <?= $grasaResultado['label'] ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="historial-item" style="display: flex; align-items: center; gap: 20px; border-bottom: 1px solid #ccc; padding: 10px;">
      <div>
        <strong>Fecha:</strong> <?php echo count($expediente) > 0 ? date('d/m/Y', strtotime(end($expediente)['fecha_registro'])) : 'Sin datos'; ?><br>
        <strong>IMC:</strong> <?= $imcActual > 0 ? number_format($imcActual, 1) : 'Sin datos' ?> (<?= $resultado['label'] ?>)
      </div>

      <div class="termometro-container" style="width: 200px; background: #eee; border-radius: 10px; height: 20px; overflow: hidden;">
        <?php 
          $porcentaje = $imcActual > 0 ? min(max(($imcActual - 15) * 3, 5), 100) : 0; 
        ?>
        <div style="width: <?php echo $porcentaje; ?>%; background-color: <?= $resultado['color'] ?>; height: 100%; transition: width 0.5s ease;"></div>
      </div>
      
      <div class="decision-box" style="background: <?= $imcActual >= 30 ? '#fff3cd' : '#d4edda' ?>; padding: 8px 12px; border-radius: 6px; border-left: 4px solid <?= $imcActual >= 30 ? '#ffc107' : '#28a745' ?>;">
        <small>Sugerencia:</small>
        <strong><?php echo ($imcActual >= 30) ? "Plan Hipocalórico" : "Mantenimiento"; ?></strong>
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
                echo ' <small style="font-size:.85rem;">kg</small>';
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
<div class="row g-3">
  <div class="col-lg-6">
    <div class="chart-container">
      <h5 class="mb-2"><i class="bi bi-graph-up text-primary me-2"></i>Evolución de Peso e IMC</h5>
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

  <div class="col-lg-6">
    <div class="chart-container">
      <h5 class="mb-2"><i class="bi bi-activity text-success me-2"></i>Tiempo de Ejercicio (Últimos 7 registros)</h5>
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
    <div class="text-center py-3 text-muted">
      <i class="bi bi-inbox" style="font-size:2.2rem;"></i>
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
<?php endif; // Fin datos paciente ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const exp = <?= json_encode($expediente, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE) ?>;
const eje = <?= json_encode($ejercicios, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE) ?>;

// [SCRIPTS DE GRÁFICAS EXISTENTES INTACTOS]
const cPrimary = '#198754';
const cPrimaryFill = 'rgba(25,135,84,.12)';
const cPurple = '#6f42c1';
const cPurpleFill = 'rgba(111,66,193,.12)';
const cSuccess = '#20c997';
const gridColor = '#e9ecef';
const tickColor = '#6c757d';

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
