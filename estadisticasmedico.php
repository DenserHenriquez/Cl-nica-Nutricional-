<?php
session_start();
// Conexión a la base de datos
require_once __DIR__ . '/db_connection.php';

// Control simple de acceso (opcional)
// if (!isset($_SESSION['id_usuarios'])) { header('Location: index.php'); exit; }

// Parámetros de filtro (GET)
$fecha_desde = !empty($_GET['fecha_desde']) ? $_GET['fecha_desde'] : null;
$fecha_hasta = !empty($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : null;
$medico_id = isset($_GET['medico_id']) ? intval($_GET['medico_id']) : 0;

// Si no hay rango, usar año actual
if (!$fecha_desde || !$fecha_hasta) {
    $anio = date('Y');
    $fecha_desde = "$anio-01-01";
    $fecha_hasta = date('Y-m-d');
}

// Preparar datos para gráficos usando la tabla `citas`
$meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$citasData = array_fill(0, 12, 0);
$pacientesData = array_fill(0, 12, 0);
$tiposCounts = ['pendiente'=>0, 'confirmada'=>0, 'cancelada'=>0, 'completada'=>0];

// Seguridad: usar $conexion (definido en db_connection.php)
if (!isset($conexion)) { $conexion = null; }
if ($conexion) {
    // Citas por mes
    $sql = "SELECT MONTH(fecha) AS m, COUNT(*) AS c FROM citas WHERE fecha BETWEEN ? AND ?";
    if ($medico_id > 0) $sql .= " AND medico_id = ?";
    $sql .= " GROUP BY MONTH(fecha)";
    if ($stmt = $conexion->prepare($sql)) {
        if ($medico_id > 0) {
            $stmt->bind_param('ssi', $fecha_desde, $fecha_hasta, $medico_id);
        } else {
            $stmt->bind_param('ss', $fecha_desde, $fecha_hasta);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $idx = (int)$row['m'] - 1;
            if ($idx >= 0 && $idx < 12) $citasData[$idx] = (int)$row['c'];
        }
        $stmt->close();
    }

    // Pacientes nuevos por mes: primer registro de cita por paciente
    $sql2 = "SELECT MONTH(min_dt) AS m, COUNT(*) AS c FROM (SELECT nombre_completo, MIN(fecha) AS min_dt FROM citas";
    if ($medico_id > 0) $sql2 .= " WHERE medico_id = ?";
    $sql2 .= " GROUP BY nombre_completo) t WHERE min_dt BETWEEN ? AND ? GROUP BY MONTH(min_dt)";
    if ($stmt = $conexion->prepare($sql2)) {
        if ($medico_id > 0) {
            // medico_id, fecha_desde, fecha_hasta
            $stmt->bind_param('iss', $medico_id, $fecha_desde, $fecha_hasta);
        } else {
            $stmt->bind_param('ss', $fecha_desde, $fecha_hasta);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $idx = (int)$row['m'] - 1;
            if ($idx >= 0 && $idx < 12) $pacientesData[$idx] = (int)$row['c'];
        }
        $stmt->close();
    }

    // Estados de consultas
    $sql3 = "SELECT estado, COUNT(*) AS c 
             FROM citas WHERE fecha BETWEEN ? AND ?";
    if ($medico_id > 0) $sql3 .= " AND medico_id = ?";
    $sql3 .= " GROUP BY estado";
    if ($stmt = $conexion->prepare($sql3)) {
        if ($medico_id > 0) {
            $stmt->bind_param('ssi', $fecha_desde, $fecha_hasta, $medico_id);
        } else {
            $stmt->bind_param('ss', $fecha_desde, $fecha_hasta);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $estado = $row['estado'] ?? 'pendiente';
            if (!isset($tiposCounts[$estado])) $tiposCounts[$estado] = 0;
            $tiposCounts[$estado] += (int)$row['c'];
        }
        $stmt->close();
    }
}
?>
<?php
// Obtener lista de médicos para el select: preferir tabla usuarios, fallback a ids en citas
$medicos_list = [];
if ($conexion) {
    // intentar leer desde usuarios (Rol = 'Medico')
    $q = "SELECT id_usuarios, Nombre_completo FROM usuarios WHERE Rol = 'Medico' ORDER BY Nombre_completo";
    if ($res = $conexion->query($q)) {
        while ($r = $res->fetch_assoc()) { $medicos_list[(int)$r['id_usuarios']] = $r['Nombre_completo']; }
        $res->close();
    }
    // si no hay resultados, obtener ids distintos desde citas
    if (empty($medicos_list)) {
        $qr = $conexion->query("SELECT DISTINCT medico_id FROM citas ORDER BY medico_id");
        if ($qr) {
            while ($r = $qr->fetch_assoc()) { $id = (int)$r['medico_id']; $medicos_list[$id] = 'Médico ' . $id; }
            $qr->close();
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Estadísticas Médico - NUTRIVIDA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/estilos.css">
    <style>
        :root{
            --brand-primary:#198754;
            --brand-primary-dark:#0b5ed7;
            --brand-bg:#f5f7fb;
            --brand-surface:#ffffff;
            --brand-border:#e9ecef;
            --brand-muted:#6c757d;
            --brand-purple:#6f42c1;
        }
        body{ background:var(--brand-bg); color:#274046; font-family:'Segoe UI',Roboto,Arial, sans-serif; }
        .stats-card{ background:var(--brand-surface); border-radius:12px; padding:18px; border:1px solid var(--brand-border); box-shadow:0 4px 12px rgba(0,0,0,0.04); }
        .panel-title{ color:var(--brand-primary-dark); }
        .filters-card{ background:var(--brand-surface); border:1px solid var(--brand-border); border-radius:10px; padding:16px; }
        .small-muted{ color:var(--brand-muted); font-size:0.9rem; }
        .header-section{ background: linear-gradient(135deg, #198754 0%, #146c43 100%); color:#fff; padding:0.8rem 0; margin-bottom:1rem; }
        .medical-icon{ font-size:1.6rem; color:#fff; }
    </style>
</head>
<body>

<header class="header-section mb-3">
    <div class="container">
        <div class="d-flex align-items-center justify-content-center py-2">
            <div class="d-flex align-items-center text-center" style="gap:12px">
                <span class="medical-icon"><i class="fas fa-chart-pie"></i></span>
                <div>
                    <h1 class="h4 mb-0">Estadísticas del Médico</h1>
                    <p class="small mb-0">Panel con métricas y reportes para seguimiento clínico</p>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="container mb-5">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="stats-card">
                        <h5 class="mb-3">Número de Citas por Mes</h5>
                        <canvas id="chartCitas"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="stats-card">
                        <h5 class="mb-3">Pacientes Nuevos</h5>
                        <canvas id="chartPacientes"></canvas>
                    </div>
                </div>

                <div class="col-12">
                    <div class="stats-card mt-2">
                        <h5 class="mb-3">Estados de Consultas</h5>
                        <div class="row align-items-center">
                            <div class="col-md-7">
                                <canvas id="chartTipos"></canvas>
                            </div>
                            <div class="col-md-5 small-muted">
                                <ul class="list-unstyled mb-0">
                                    <li><span class="badge bg-success me-2">&nbsp;</span>Confirmada</li>
                                    <li><span class="badge bg-primary me-2">&nbsp;</span>Pendiente</li>
                                    <li><span class="badge bg-warning me-2">&nbsp;</span>Cancelada</li>
                                    <li><span class="badge bg-info me-2">&nbsp;</span>Completada</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="filters-card">
                <h6 class="mb-3">Filtros</h6>
                <form id="filtrosForm">
                    <div class="mb-3">
                        <label class="form-label small-muted">Fecha Desde</label>
                        <input type="date" class="form-control" name="fecha_desde" id="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small-muted">Fecha Hasta</label>
                        <input type="date" class="form-control" name="fecha_hasta" id="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small-muted">Médico</label>
                        <select class="form-select" name="medico_id" id="medico_id">
                            <option value="0">Todos</option>
                            <?php foreach ($medicos_list as $id => $name): ?>
                                <option value="<?php echo (int)$id; ?>" <?php echo ($medico_id === (int)$id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="button" id="aplicarFiltros" class="btn btn-success">Aplicar filtros</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Colores coherentes con `retroalimentacion1.php`
    const primary = '#198754';
    const primaryDark = '#146c43';
    const accentBlue = '#0d6efd';
    const accentYellow = '#ffc107';

    // Datos provenientes del servidor (tabla `citas`)
    const meses = <?php echo json_encode($meses, JSON_UNESCAPED_UNICODE); ?>;
    const citasData = <?php echo json_encode($citasData, JSON_NUMERIC_CHECK); ?>;
    const pacientesData = <?php echo json_encode($pacientesData, JSON_NUMERIC_CHECK); ?>;
    const estadosLabels = <?php echo json_encode(array_keys($tiposCounts), JSON_UNESCAPED_UNICODE); ?>;
    const estadosData = <?php echo json_encode(array_values($tiposCounts), JSON_NUMERIC_CHECK); ?>;

    // Crear gráficos
    const ctxCitas = document.getElementById('chartCitas').getContext('2d');
    new Chart(ctxCitas, {
        type: 'bar',
        data: {
            labels: meses,
            datasets: [{ label: 'Citas', data: citasData, backgroundColor: primary }]
        },
        options: { responsive:true, plugins:{ legend:{ display:false } }, scales: { y: { beginAtZero:true } } }
    });

    const ctxPac = document.getElementById('chartPacientes').getContext('2d');
    new Chart(ctxPac, {
        type: 'line',
        data: {
            labels: meses,
            datasets: [{ label: 'Pacientes Nuevos', data: pacientesData, borderColor: primaryDark, backgroundColor: primary, fill:false, tension:0.3 }]
        },
        options: { responsive:true, plugins:{ legend:{ display:false } }, scales: { y: { beginAtZero:true } } }
    });

    const ctxTipos = document.getElementById('chartTipos').getContext('2d');
    new Chart(ctxTipos, {
        type: 'pie',
        data: {
            labels: estadosLabels,
            datasets: [{ data: estadosData, backgroundColor: [primary, accentBlue, accentYellow, primaryDark] }]
        },
        options: { responsive:true }
    });

    // Aplicar filtros: recargar la página con parámetros GET
    document.getElementById('aplicarFiltros').addEventListener('click', function(){
        const desde = document.getElementById('fecha_desde').value;
        const hasta = document.getElementById('fecha_hasta').value;
        const medico = document.getElementById('medico_id').value;
        const params = new URLSearchParams(window.location.search);
        if (desde) params.set('fecha_desde', desde); else params.delete('fecha_desde');
        if (hasta) params.set('fecha_hasta', hasta); else params.delete('fecha_hasta');
        if (medico) params.set('medico_id', medico); else params.delete('medico_id');
        window.location.search = params.toString();
    });
</script>
</body>
</html>
