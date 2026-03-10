<?php
session_start();
// Conexion a la base de datos
require_once __DIR__ . '/db_connection.php';

// Parametros de filtro (GET)
$fecha_desde = !empty($_GET['fecha_desde']) ? $_GET['fecha_desde'] : null;
$fecha_hasta = !empty($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : null;
$medico_id = isset($_GET['medico_id']) ? intval($_GET['medico_id']) : 0;

// Si no hay rango, usar anio actual
if (!$fecha_desde || !$fecha_hasta) {
    $anio = date('Y');
    $fecha_desde = "$anio-01-01";
    $fecha_hasta = date('Y-m-d');
}

// Preparar datos para graficos usando la tabla `citas`
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

    // Pacientes nuevos por mes
    $sql2 = "SELECT MONTH(min_dt) AS m, COUNT(*) AS c FROM (SELECT nombre_completo, MIN(fecha) AS min_dt FROM citas";
    if ($medico_id > 0) $sql2 .= " WHERE medico_id = ?";
    $sql2 .= " GROUP BY nombre_completo) t WHERE min_dt BETWEEN ? AND ? GROUP BY MONTH(min_dt)";
    if ($stmt = $conexion->prepare($sql2)) {
        if ($medico_id > 0) {
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
    $sql3 = "SELECT estado, COUNT(*) AS c FROM citas WHERE fecha BETWEEN ? AND ?";
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

// KPIs
$totalCitas           = array_sum($citasData);
$totalPacientesNuevos = array_sum($pacientesData);
$totalConfirmadas     = $tiposCounts['confirmada']  ?? 0;
$totalPendientes      = $tiposCounts['pendiente']   ?? 0;
$totalCanceladas      = $tiposCounts['cancelada']   ?? 0;
$totalCompletadas     = $tiposCounts['completada']  ?? 0;

// Lista de medicos
$medicos_list = [];
if ($conexion) {
    $q = "SELECT id_usuarios, Nombre_completo FROM usuarios WHERE Rol = 'Medico' ORDER BY Nombre_completo";
    if ($res = $conexion->query($q)) {
        while ($r = $res->fetch_assoc()) { $medicos_list[(int)$r['id_usuarios']] = $r['Nombre_completo']; }
        $res->close();
    }
    if (empty($medicos_list)) {
        $qr = $conexion->query("SELECT DISTINCT medico_id FROM citas ORDER BY medico_id");
        if ($qr) {
            while ($r = $qr->fetch_assoc()) { $id = (int)$r['medico_id']; $medicos_list[$id] = 'Medico ' . $id; }
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
    <title>Estadisticas Medico - NUTRIVIDA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/estilos.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        body { background:#f8fafc; font-family:'Segoe UI',system-ui,sans-serif; padding:20px; }

        /* HEADER */
        .header-section { background:linear-gradient(135deg,#198754 0%,#146c43 100%); color:#fff; border-radius:18px; padding:20px 28px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; }
        .header-section h1 { font-size:1.5rem; font-weight:700; margin:0; }
        .header-section p  { font-size:.88rem; margin:4px 0 0; opacity:.88; }
        .medical-icon { font-size:1.8rem; color:#fff; margin-right:12px; }

        /* METRIC CARDS — igual que inicio.php */
        .metric-icon { width:48px; height:48px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; margin-bottom:10px; }
        .metric-icon.green  { background:#e8f5e9; color:#198754; }
        .metric-icon.blue   { background:#e3f2fd; color:#0d6efd; }
        .metric-icon.orange { background:#fff3e0; color:#fd7e14; }
        .metric-icon.purple { background:#f3e5f5; color:#9c27b0; }
        .metric-icon.teal   { background:#e0f7fa; color:#0097a7; }
        .metric-icon.red    { background:#ffebee; color:#e53935; }
        .metric-card { background:#fff; border:1px solid #e9ecef; border-radius:18px; padding:24px 22px; box-shadow:0 4px 12px rgba(0,0,0,0.05); transition:.25s; height:100%; }
        .metric-card:hover { transform:translateY(-4px); box-shadow:0 10px 28px rgba(0,0,0,0.08); }
        .metric-value { font-size:2.3rem; font-weight:700; margin:0; color:#0d5132; }
        .metric-label { font-size:0.95rem; font-weight:600; color:#495057; text-transform:uppercase; letter-spacing:.5px; }
        .metric-trend { font-size:0.78rem; color:#6c757d; margin-top:4px; }

        /* CHART / PANEL CARDS */
        .chart-card { background:#fff; border:1px solid #e9ecef; border-radius:18px; padding:22px; box-shadow:0 4px 12px rgba(0,0,0,0.05); height:100%; }
        .chart-card h6 { font-weight:700; color:#0d5132; font-size:1rem; margin-bottom:4px; }
        .chart-card .card-sub { font-size:.78rem; color:#6c757d; margin-bottom:16px; }

        /* FILTERS BAR */
        .filters-bar { background:#fff; border:1px solid #e9ecef; border-radius:18px; padding:18px 22px; box-shadow:0 4px 12px rgba(0,0,0,0.05); margin-bottom:24px; }
        .filters-bar .form-label { font-size:.8rem; font-weight:600; color:#495057; text-transform:uppercase; letter-spacing:.4px; margin-bottom:4px; }

        /* PROGRESS BARS */
        .estado-row { margin-bottom:12px; }
        .estado-row .lrow { display:flex; justify-content:space-between; font-size:.82rem; margin-bottom:4px; }
        .estado-row .lrow .lname  { font-weight:600; color:#333; }
        .estado-row .lrow .lcount { color:#6c757d; }
        .progress { height:8px; border-radius:6px; }

        /* PDF BUTTON */
        .btn-pdf { background:#dc3545; color:#fff; border:none; border-radius:10px; padding:10px 20px; font-size:.9rem; font-weight:600; display:inline-flex; align-items:center; gap:8px; cursor:pointer; transition:all .2s; box-shadow:0 3px 10px rgba(220,53,69,0.3); }
        .btn-pdf:hover { background:#b02a37; transform:translateY(-1px); color:#fff; }

        /* Neutralize the blue overlay from estilos.css */
        #pdfContent.container-fluid::before { display:none !important; }

        /* PDF Loader overlay */
        #pdfLoader { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; flex-direction:column; gap:14px; color:#fff; font-size:1rem; }
        #pdfLoader.show { display:flex; }
    </style>
</head>
<body>
<div id="pdfLoader">
    <div class="spinner-border text-light" style="width:3rem;height:3rem;"></div>
    <span>Generando PDF...</span>
</div>

<div class="container-fluid" id="pdfContent">

    <!-- HEADER -->
    <div class="header-section">
        <div class="d-flex align-items-center">
            <span class="medical-icon"><i class="fas fa-chart-pie"></i></span>
            <div>
                <h1>Estadisticas del Medico</h1>
                <p>Panel con metricas y reportes para seguimiento clinico</p>
            </div>
        </div>
        <button class="btn-pdf no-print" id="btnPDF">
            <i class="fas fa-file-pdf"></i> Exportar PDF
        </button>
    </div>

    <!-- FILTERS BAR (horizontal) -->
    <div class="filters-bar no-print">
        <div class="row g-3 align-items-end">
            <div class="col-md-3 col-sm-6">
                <label class="form-label">Fecha Desde</label>
                <input type="date" class="form-control" id="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde); ?>">
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label">Fecha Hasta</label>
                <input type="date" class="form-control" id="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label">Medico</label>
                <select class="form-select" id="medico_id">
                    <option value="0">Todos</option>
                    <?php foreach ($medicos_list as $id => $name): ?>
                        <option value="<?php echo (int)$id; ?>" <?php echo ($medico_id === (int)$id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-sm-6">
                <button type="button" id="aplicarFiltros" class="btn btn-success w-100">
                    <i class="fas fa-search me-1"></i> Aplicar filtros
                </button>
            </div>
        </div>
    </div>

    <!-- KPI CARDS -->
    <div class="row g-4 mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="metric-card">
                <div class="metric-icon green"><i class="fas fa-calendar-check"></i></div>
                <div class="metric-label">Total Citas</div>
                <p class="metric-value mb-0"><?= $totalCitas; ?></p>
                <div class="metric-trend"><i class="fas fa-chart-bar"></i> En el periodo</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="metric-card">
                <div class="metric-icon teal"><i class="fas fa-user-plus"></i></div>
                <div class="metric-label">Pac. Nuevos</div>
                <p class="metric-value mb-0"><?= $totalPacientesNuevos; ?></p>
                <div class="metric-trend"><i class="fas fa-users"></i> Primera cita</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="metric-card">
                <div class="metric-icon blue"><i class="fas fa-check-circle"></i></div>
                <div class="metric-label">Confirmadas</div>
                <p class="metric-value mb-0"><?= $totalConfirmadas; ?></p>
                <div class="metric-trend"><i class="fas fa-circle-check"></i> Citas activas</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="metric-card">
                <div class="metric-icon orange"><i class="fas fa-hourglass-half"></i></div>
                <div class="metric-label">Pendientes</div>
                <p class="metric-value mb-0"><?= $totalPendientes; ?></p>
                <div class="metric-trend"><i class="fas fa-clock"></i> Por confirmar</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="metric-card">
                <div class="metric-icon red"><i class="fas fa-times-circle"></i></div>
                <div class="metric-label">Canceladas</div>
                <p class="metric-value mb-0"><?= $totalCanceladas; ?></p>
                <div class="metric-trend"><i class="fas fa-ban"></i> En el periodo</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="metric-card">
                <div class="metric-icon purple"><i class="fas fa-clipboard-check"></i></div>
                <div class="metric-label">Completadas</div>
                <p class="metric-value mb-0"><?= $totalCompletadas; ?></p>
                <div class="metric-trend"><i class="fas fa-flag-checkered"></i> Finalizadas</div>
            </div>
        </div>
    </div>

    <!-- GRAFICO LINEA — ancho completo -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="chart-card">
                <h6>Evolucion Mensual de Citas y Pacientes Nuevos</h6>
                <p class="card-sub">Seguimiento mes a mes del periodo seleccionado</p>
                <canvas id="chartLinea" height="80"></canvas>
            </div>
        </div>
    </div>

    <!-- GRAFICOS INFERIORES — 3 columnas simétricas -->
    <div class="row g-4 mb-4">
        <!-- Barras -->
        <div class="col-lg-4 col-md-6">
            <div class="chart-card">
                <h6>Numero de Citas por Mes</h6>
                <p class="card-sub">Total de consultas registradas</p>
                <canvas id="chartCitas" height="200"></canvas>
            </div>
        </div>
        <!-- Dona -->
        <div class="col-lg-4 col-md-6">
            <div class="chart-card">
                <h6>Estados de Consultas</h6>
                <p class="card-sub">Distribucion por estado</p>
                <div class="d-flex align-items-center justify-content-center gap-3" style="height:200px;">
                    <canvas id="chartTipos" style="max-height:200px;"></canvas>
                </div>
                <div class="d-flex flex-wrap justify-content-center gap-2 mt-2" style="font-size:.8rem;">
                    <span><span class="badge bg-success me-1">&nbsp;</span>Confirmada</span>
                    <span><span class="badge bg-primary me-1">&nbsp;</span>Pendiente</span>
                    <span><span class="badge bg-warning me-1">&nbsp;</span>Cancelada</span>
                    <span><span class="badge bg-info me-1">&nbsp;</span>Completada</span>
                </div>
            </div>
        </div>
        <!-- Barras de progreso -->
        <div class="col-lg-4 col-md-12" id="barrasEstados">
            <div class="chart-card">
                <h6>Desglose de Estados</h6>
                <p class="card-sub">Proporcion de cada estado en el periodo</p>
                <?php
                $estadosDef = [
                    'confirmada' => ['label'=>'Confirmadas', 'color'=>'#198754', 'class'=>'bg-success'],
                    'pendiente'  => ['label'=>'Pendientes',  'color'=>'#fd7e14', 'class'=>'bg-warning'],
                    'completada' => ['label'=>'Completadas', 'color'=>'#0d6efd', 'class'=>'bg-primary'],
                    'cancelada'  => ['label'=>'Canceladas',  'color'=>'#dc3545', 'class'=>'bg-danger'],
                ];
                $totalEst = max(1, array_sum($tiposCounts));
                foreach ($estadosDef as $k => $def):
                    $val = $tiposCounts[$k] ?? 0;
                    $pct = round($val / $totalEst * 100, 1);
                ?>
                <div class="estado-row">
                    <div class="lrow">
                        <span class="lname"><?= $def['label']; ?></span>
                        <span class="lcount"><?= $val; ?> &nbsp;<strong><?= $pct; ?>%</strong></span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar <?= $def['class']; ?>" role="progressbar" style="width:<?= $pct; ?>%;border-radius:6px;"></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <hr style="border-color:#e9ecef; margin:18px 0 14px;">
                <div style="font-size:.85rem;">
                    <div class="d-flex justify-content-between mb-1"><span class="text-muted">Total citas</span><strong><?= $totalCitas; ?></strong></div>
                    <div class="d-flex justify-content-between mb-1"><span class="text-muted">Pac. nuevos</span><strong><?= $totalPacientesNuevos; ?></strong></div>
                    <div class="d-flex justify-content-between mb-1"><span class="text-muted">Periodo desde</span><strong><?= htmlspecialchars($fecha_desde); ?></strong></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">Periodo hasta</span><strong><?= htmlspecialchars($fecha_hasta); ?></strong></div>
                </div>
            </div>
        </div>
    </div>

</div><!-- /container-fluid -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    const primary      = '#198754';
    const primaryDark  = '#146c43';
    const accentBlue   = '#0d6efd';
    const accentYellow = '#ffc107';
    const accentTeal   = '#0f9690';

    const meses         = <?php echo json_encode($meses, JSON_UNESCAPED_UNICODE); ?>;
    const citasData     = <?php echo json_encode(array_values($citasData), JSON_NUMERIC_CHECK); ?>;
    const pacientesData = <?php echo json_encode(array_values($pacientesData), JSON_NUMERIC_CHECK); ?>;
    const estadosLabels = <?php echo json_encode(array_keys($tiposCounts), JSON_UNESCAPED_UNICODE); ?>;
    const estadosData   = <?php echo json_encode(array_values($tiposCounts), JSON_NUMERIC_CHECK); ?>;

    const gridColor = 'rgba(0,0,0,0.04)';

    // Linea combinada
    new Chart(document.getElementById('chartLinea'), {
        type:'line',
        data:{ labels:meses, datasets:[
            { label:'Citas', data:citasData, borderColor:primary, backgroundColor:'rgba(25,135,84,0.10)', borderWidth:2.5, pointRadius:4, pointBackgroundColor:primary, fill:true, tension:0.4 },
            { label:'Pacientes Nuevos', data:pacientesData, borderColor:accentBlue, backgroundColor:'rgba(13,110,253,0.08)', borderWidth:2.5, pointRadius:4, pointBackgroundColor:accentBlue, fill:true, tension:0.4 }
        ]},
        options:{ responsive:true, interaction:{mode:'index',intersect:false},
            plugins:{ legend:{ position:'bottom', labels:{ usePointStyle:true, padding:20, font:{size:12} } } },
            scales:{ x:{grid:{color:gridColor}}, y:{beginAtZero:true, grid:{color:gridColor}, ticks:{precision:0}} }
        }
    });

    // Barras
    new Chart(document.getElementById('chartCitas'), {
        type:'bar',
        data:{ labels:meses, datasets:[{ label:'Citas', data:citasData, backgroundColor:primary, borderRadius:5, borderSkipped:false }]},
        options:{ responsive:true, plugins:{legend:{display:false}},
            scales:{ y:{beginAtZero:true, grid:{color:gridColor}, ticks:{precision:0}}, x:{grid:{display:false}} }
        }
    });

    // Dona
    new Chart(document.getElementById('chartTipos'), {
        type:'doughnut',
        data:{ labels:estadosLabels, datasets:[{ data:estadosData, backgroundColor:[primary, accentBlue, accentYellow, accentTeal], borderWidth:2, borderColor:'#fff' }]},
        options:{ responsive:true, cutout:'55%', plugins:{legend:{display:false}} }
    });

    // Filtros
    document.getElementById('aplicarFiltros').addEventListener('click', function(){
        const params = new URLSearchParams(window.location.search);
        const d = document.getElementById('fecha_desde').value;
        const h = document.getElementById('fecha_hasta').value;
        const m = document.getElementById('medico_id').value;
        d ? params.set('fecha_desde',d) : params.delete('fecha_desde');
        h ? params.set('fecha_hasta',h) : params.delete('fecha_hasta');
        m ? params.set('medico_id',m)   : params.delete('medico_id');
        window.location.search = params.toString();
    });

    // Exportar PDF detallado
    document.getElementById('btnPDF').addEventListener('click', async function(){
        const loader = document.getElementById('pdfLoader');
        loader.classList.add('show');
        try {
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF({ orientation:'portrait', unit:'mm', format:'a4' });
            const W = pdf.internal.pageSize.getWidth();
            const H = pdf.internal.pageSize.getHeight();
            const m = 14;

            // Encabezado verde
            pdf.setFillColor(25,135,84);
            pdf.rect(0,0,W,32,'F');
            pdf.setTextColor(255,255,255);
            pdf.setFontSize(16); pdf.setFont('helvetica','bold');
            pdf.text('Estadisticas del Medico — Clinica Nutricional', m, 12);
            pdf.setFontSize(9); pdf.setFont('helvetica','normal');
            pdf.text('Periodo: <?= htmlspecialchars($fecha_desde); ?> al <?= htmlspecialchars($fecha_hasta); ?>', m, 20);
            pdf.text('Generado: ' + new Date().toLocaleString('es'), m, 27);

            let y = 40;

            // Titulo seccion KPIs
            pdf.setTextColor(13,81,50); pdf.setFontSize(12); pdf.setFont('helvetica','bold');
            pdf.text('Indicadores Clave del Periodo', m, y); y += 6;

            const kpis = [
                ['Total Citas','<?= $totalCitas; ?>','En el periodo'],
                ['Pac. Nuevos','<?= $totalPacientesNuevos; ?>','Primera cita'],
                ['Confirmadas','<?= $totalConfirmadas; ?>','Citas activas'],
                ['Pendientes', '<?= $totalPendientes; ?>', 'Por confirmar'],
                ['Canceladas', '<?= $totalCanceladas; ?>','En el periodo'],
                ['Completadas','<?= $totalCompletadas; ?>','Finalizadas'],
            ];
            const cW = (W - m*2) / 3;
            pdf.setFontSize(9);
            kpis.forEach((k, i) => {
                const col = i % 3;
                const row = Math.floor(i / 3);
                const cx = m + col * cW;
                const cy = y + row * 18;
                pdf.setFillColor(248,250,252);
                pdf.roundedRect(cx, cy, cW-3, 15, 3, 3, 'F');
                pdf.setDrawColor(233,236,239);
                pdf.roundedRect(cx, cy, cW-3, 15, 3, 3, 'S');
                pdf.setFont('helvetica','bold'); pdf.setTextColor(13,81,50);
                pdf.setFontSize(13); pdf.text(k[1], cx+4, cy+9);
                pdf.setFont('helvetica','normal'); pdf.setTextColor(73,80,87);
                pdf.setFontSize(7.5); pdf.text(k[0], cx+4, cy+13.5);
            });
            y += Math.ceil(kpis.length/3)*18 + 8;

            // Desglose de estados
            pdf.setTextColor(13,81,50); pdf.setFontSize(12); pdf.setFont('helvetica','bold');
            pdf.text('Desglose de Estados', m, y); y += 5;
            const estadosDef = [
                { label:'Confirmadas', val:<?= $totalConfirmadas; ?>, color:[25,135,84] },
                { label:'Pendientes',  val:<?= $totalPendientes; ?>,  color:[253,126,20] },
                { label:'Completadas', val:<?= $totalCompletadas; ?>, color:[13,110,253] },
                { label:'Canceladas',  val:<?= $totalCanceladas; ?>,  color:[220,53,69] },
            ];
            const totalEst = Math.max(1, <?= array_sum($tiposCounts); ?>);
            estadosDef.forEach(e => {
                const pct = (e.val / totalEst * 100).toFixed(1);
                const barW = (W - m*2) * e.val / totalEst;
                pdf.setTextColor(73,80,87); pdf.setFontSize(9); pdf.setFont('helvetica','normal');
                pdf.text(`${e.label}: ${e.val} (${pct}%)`, m, y); y += 4;
                pdf.setFillColor(233,236,239); pdf.roundedRect(m, y, W-m*2, 5, 2, 2, 'F');
                pdf.setFillColor(...e.color); pdf.roundedRect(m, y, Math.max(barW,1), 5, 2, 2, 'F');
                y += 9;
            });
            y += 4;

            // Graficos
            const charts = [
                { id:'chartLinea',    label:'Evolucion Mensual de Citas y Pacientes Nuevos' },
                { id:'chartCitas',    label:'Numero de Citas por Mes' },
                { id:'chartTipos',    label:'Estados de Consultas' },
            ];
            for (const ch of charts) {
                const cvs = document.getElementById(ch.id);
                const img = cvs.toDataURL('image/png',1.0);
                const iW  = W - m*2;
                const iH  = iW * (cvs.height / cvs.width);
                if (y + iH + 16 > H) { pdf.addPage(); y = 14; }
                pdf.setTextColor(13,81,50); pdf.setFontSize(11); pdf.setFont('helvetica','bold');
                pdf.text(ch.label, m, y); y += 4;
                pdf.addImage(img,'PNG',m,y,iW,iH);
                y += iH + 8;
            }

            // Captura barras de progreso
            const barEl = document.getElementById('barrasEstados');
            if (barEl) {
                const bc = await html2canvas(barEl, { scale:2, backgroundColor:'#fff', useCORS:true });
                const bI  = bc.toDataURL('image/png');
                const bH  = (W-m*2) * (bc.height/bc.width);
                if (y + bH + 16 > H) { pdf.addPage(); y = 14; }
                pdf.setTextColor(13,81,50); pdf.setFontSize(11); pdf.setFont('helvetica','bold');
                pdf.text('Desglose Visual de Estados', m, y); y += 4;
                pdf.addImage(bI,'PNG',m,y,W-m*2,bH);
            }

            // Pie de pagina en todas las paginas
            const tot = pdf.getNumberOfPages();
            for (let p=1; p<=tot; p++) {
                pdf.setPage(p);
                pdf.setFontSize(7); pdf.setFont('helvetica','normal'); pdf.setTextColor(160,160,160);
                pdf.text('Clinica Nutricional — Estadisticas Medico', m, H-5);
                pdf.text(`Pagina ${p} / ${tot}`, W-m, H-5, {align:'right'});
            }

            pdf.save(`estadisticas_medico_<?= date('Ymd'); ?>.pdf`);
        } catch(e) {
            alert('Error al generar PDF: ' + e.message);
            console.error(e);
        } finally {
            loader.classList.remove('show');
        }
    });
})();
</script>
</body>
</html>
