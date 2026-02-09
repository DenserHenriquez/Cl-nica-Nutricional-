<?php
// citas_medico.php
// Gestión de citas para el médico: calendario, horarios disponibles, estados y alertas.
// Requiere: db_connection.php con $conn (mysqli) configurado a la BD "clinica".

session_start();

// Cargar conexión
require_once __DIR__ . '/db_connection.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) {
    die('Error de conexión a la base de datos. Verifique db_connection.php');
}
$conn = $conexion; // Asignar a $conn para compatibilidad

// Helper: sanitización básica
function post($key, $default = null) { return isset($_POST[$key]) ? trim($_POST[$key]) : $default; }
function get($key, $default = null) { return isset($_GET[$key]) ? trim($_GET[$key]) : $default; }

// Determinar fecha actual y mes/año a mostrar
$hoy = new DateTime('now', new DateTimeZone('America/Mexico_City'));
$year = intval(get('year', $hoy->format('Y')));
$month = intval(get('month', $hoy->format('n')));
if ($month < 1 || $month > 12) { $month = intval($hoy->format('n')); }
if ($year < 1970 || $year > 2100) { $year = intval($hoy->format('Y')); }
$firstDay = new DateTime("$year-$month-01", new DateTimeZone('America/Mexico_City'));
$startWeekday = intval($firstDay->format('N')); // 1 (Mon) - 7 (Sun)
$daysInMonth = intval($firstDay->format('t'));

// Identificador del médico (en un sistema real provendría del login). Se permite GET/POST o default 1
$medico_id = intval(get('medico_id', post('medico_id', 0)));

// Datos estáticos de médicos (igual que en citas_medico.php)
$medicos = [
    1 => ['nombre' => 'Dr. Denser Henriquez', 'especialidad' => 'Nutrición General', 'email' => 'denser.henriquez@nutri.hn', 'telefono' => '9880-8080', 'imagen' => 'https://www.emagister.com/blog/wp-content/uploads/2017/08/nutricion-3.jpg'],
    2 => ['nombre' => 'Dra. Genesis Bonilla', 'especialidad' => 'Nutrición Deportiva', 'email' => 'genesis.bonilla@nutri.hn', 'telefono' => '9858-8569', 'imagen' => 'https://blob.medicinaysaludpublica.com/images/2023/06/05/formato-sacs-22-7b57bcca-focus-min0.07-0.49-688-364.png'],
    3 => ['nombre' => 'Dr. Anthony Rodriguez', 'especialidad' => 'Nutrición Clínica', 'email' => 'Anthony.rodriguez@nutri.hn', 'telefono' => '9632-7895', 'imagen' => 'https://img.freepik.com/fotos-premium/doctor-hombre-manzana-retrato-sonrisa-salud-nutricionista-aislado-fondo-estudio-profesional-medico-feliz-medico-masculino-cuidado-salud-promueven-dieta-nutricion-saludables_590464-179119.jpg'],
    4 => ['nombre' => 'Dra. Ana Rodríguez', 'especialidad' => 'Nutrición Pediátrica', 'email' => 'ana.rodriguez@nutri.hn', 'telefono' => '9785-2503', 'imagen' => 'https://s3-sa-east-1.amazonaws.com/doctoralia.co/doctor/756cc1/756cc10699684fa2c30c96388a1d0258_large.jpg']
];

// Si no hay medico_id, mostrar selección de médicos
if ($medico_id === 0) {
    // Mostrar página de selección
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Seleccionar Médico - Gestión de Citas</title>
        <!-- Bootstrap 5 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Bootstrap Icons -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .card-medico {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            border-radius: 1rem;
            transition: box-shadow 0.3s;
        }
        .card-medico:hover {
            box-shadow: 0 0.5rem 1rem rgba(25,135,84,0.15);
        }
        .medico-img {
            width: 80px; height: 80px; object-fit: cover; border-radius: 50%; margin-bottom: 10px;
        }
        .header-section {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            color: white;
            /* Reduced height: ~60% */
            padding: 0.8rem 0;
            margin-bottom: 1rem;
        }
        .header-section h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0.15rem 0 0.25rem;
        }
        .header-section p {
            font-size: 1.05rem;
            opacity: 0.95;
            margin: 0;
        }
        .medical-icon {
            font-size: 1.9rem;
            margin-bottom: 0.35rem;
            color: #ffffff;
        }
    </style>
    </head>
    <body>
        <div class="header-section text-center">
            <div class="medical-icon">
                <i class="bi bi-calendar-check"></i>
            </div>
            <h1>Seleccionar Médico para Gestión de Citas</h1>
            <p>Haga clic en el médico para ver su calendario de citas.</p>
        </div>
        <div class="container">
            <div class="row justify-content-center">
                <?php foreach ($medicos as $id => $medico): ?>
                    <div class="col-md-3 mb-4">
                        <div class="card card-medico text-center h-100" onclick="selectMedico(<?php echo $id; ?>)" style="cursor:pointer;">
                            <div class="card-body">
                                <img src="<?php echo htmlspecialchars($medico['imagen']); ?>" alt="<?php echo htmlspecialchars($medico['nombre']); ?>" class="medico-img">
                                <h5 class="card-title text-success fw-bold"><?php echo htmlspecialchars($medico['nombre']); ?></h5>
                                <p class="card-text text-muted"><?php echo htmlspecialchars($medico['especialidad']); ?></p>
                                <p class="card-text small"><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($medico['email']); ?></p>
                                <p class="card-text small"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($medico['telefono']); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <script>
            function selectMedico(id) {
                window.location.href = '?medico_id=' + id;
            }
        </script>
        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit; // Salir para no mostrar el resto
}

// Asegurar tabla "citas" y "disponibilidades" si no existen (defensivo)
$conn->query("CREATE TABLE IF NOT EXISTS disponibilidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medico_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    estado ENUM('libre','bloqueado') NOT NULL DEFAULT 'libre',
    UNIQUE KEY unique_slot (medico_id, fecha, hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS citas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medico_id INT NOT NULL,
    paciente_id INT NULL,
    nombre_completo VARCHAR(255) NULL,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    motivo TEXT NULL,
    estado ENUM('pendiente','confirmada','cancelada','completada') NOT NULL DEFAULT 'pendiente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cita (medico_id, fecha, hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Acciones
$action = post('action');
$response_msg = null; $response_err = null;
if ($action === 'add_availability') {
    $fecha = post('fecha');
    $desde = post('desde');
    $hasta = post('hasta');
    $intervalo = max(5, intval(post('intervalo', 30))); // minutos
    try {
        $d = DateTime::createFromFormat('Y-m-d', $fecha, new DateTimeZone('America/Mexico_City'));
        $t1 = DateTime::createFromFormat('H:i', $desde, new DateTimeZone('America/Mexico_City'));
        $t2 = DateTime::createFromFormat('H:i', $hasta, new DateTimeZone('America/Mexico_City'));
        if (!$d || !$t1 || !$t2) throw new Exception('Formato de fecha u hora inválido');
        if ($t2 <= $t1) throw new Exception('Rango de horas inválido');
        $slots = [];
        $cursor = clone $t1;
        while ($cursor < $t2) {
            $slots[] = $cursor->format('H:i:00');
            $cursor->modify("+{$intervalo} minutes");
        }
        $stmt = $conn->prepare("INSERT INTO disponibilidades (medico_id, fecha, hora, estado) VALUES (?,?,?,'libre') ON DUPLICATE KEY UPDATE estado=VALUES(estado)");
        foreach ($slots as $hh) {
            $stmt->bind_param('iss', $medico_id, $fecha, $hh);
            $stmt->execute();
        }
        $response_msg = 'Disponibilidad registrada/actualizada correctamente.';
    } catch (Exception $e) {
        $response_err = $e->getMessage();
    }
} elseif ($action === 'toggle_slot') {
    $fecha = post('fecha');
    $hora = post('hora');
    $estado = post('estado'); // libre|bloqueado
    if (in_array($estado, ['libre','bloqueado'], true)) {
        $stmt = $conn->prepare("INSERT INTO disponibilidades (medico_id, fecha, hora, estado) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE estado=VALUES(estado)");
        $stmt->bind_param('isss', $medico_id, $fecha, $hora, $estado);
        $ok = $stmt->execute();
        $response_msg = $ok ? 'Estado del horario actualizado.' : 'No fue posible actualizar.';
    }
} elseif ($action === 'update_estado_cita') {
    $cita_id = intval(post('cita_id'));
    $nuevo_estado = post('nuevo_estado');
    if (in_array($nuevo_estado, ['pendiente','confirmada','cancelada','completada'], true)) {
        $stmt = $conn->prepare("UPDATE citas SET estado=? WHERE id=? AND medico_id=?");
        $stmt->bind_param('sii', $nuevo_estado, $cita_id, $medico_id);
        $stmt->execute();
        $response_msg = 'Estado de la cita actualizado.';
    }
}

// Consultas para el mes
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = (new DateTime($monthStart))->modify('last day of this month')->format('Y-m-d');

// Disponibilidades del mes
$disp = [];
$res = $conn->prepare("SELECT fecha, hora, estado FROM disponibilidades WHERE medico_id=? AND fecha BETWEEN ? AND ? ORDER BY fecha, hora");
$res->bind_param('iss', $medico_id, $monthStart, $monthEnd);
$res->execute();
$r = $res->get_result();
while ($row = $r->fetch_assoc()) {
    $disp[$row['fecha']][$row['hora']] = $row['estado'];
}

// Citas del mes
$citas = [];
$qr = $conn->prepare("SELECT id, nombre_completo, fecha, hora, motivo, estado FROM citas WHERE medico_id=? AND fecha BETWEEN ? AND ? ORDER BY fecha, hora");
$qr->bind_param('iss', $medico_id, $monthStart, $monthEnd);
$qr->execute();
$rc = $qr->get_result();
while ($row = $rc->fetch_assoc()) {
    $date = $row['fecha'];
    if (!isset($citas[$date])) $citas[$date] = [];
    $citas[$date][] = $row;
}

// Alertas: paciente en espera (cita confirmada que esté a 0-10 min de la hora actual)
$alertas = [];
$now = new DateTime('now', new DateTimeZone('America/Mexico_City'));
$qa = $conn->prepare("SELECT nombre_completo, fecha, hora, motivo FROM citas WHERE medico_id=? AND estado='confirmada' AND fecha=? ORDER BY hora");
$todayStr = $now->format('Y-m-d');
$qa->bind_param('is', $medico_id, $todayStr);
$qa->execute();
$ra = $qa->get_result();
while ($row = $ra->fetch_assoc()) {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $row['fecha'] . ' ' . $row['hora'], new DateTimeZone('America/Mexico_City'));
    if ($dt) {
        $diff = ($dt->getTimestamp() - $now->getTimestamp()) / 60.0; // minutos
        if ($diff >= 0 && $diff <= 10) {
            $alertas[] = $row['nombre_completo'] . ' (' . substr($row['hora'],0,5) . ')';
        }
    }
}

function monthNameEs($m) {
    $names = [1=>'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    return $names[intval($m)] ?? '';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de citas del médico</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.min.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- FullCalendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        #calendar { 
            max-width: 1100px; 
            width: 100%;
            margin: 0 auto; 
            height: 700px;
            min-height: 600px;
            font-size: 18px;
            border: 3px solid #1976d2;
            border-radius: 12px;
            padding: 20px;
            background: white;
            box-shadow: 0 4px 12px rgba(25, 118, 210, 0.15);
        }
        .fc { font-size: 16px; }
        .fc-daygrid-day { min-height: 100px; }
        .fc-daygrid-day-number { font-size: 16px; font-weight: 600; color: #000 !important; }
        .fc-event { font-size: 14px; padding: 4px; }
        .fc-col-header-cell-cushion { font-size: 16px; font-weight: 700; color: #000 !important; }
        .fc-daygrid-day-top { padding: 8px; }
        .badge { display:inline-block; padding:2px 6px; border-radius:4px; font-size:11px; margin-right:4px; }
        .b-libre { background:#e8f5e9; color:#2e7d32; border:1px solid #a5d6a7; }
        .b-bloq { background:#ffebee; color:#c62828; border:1px solid #ef9a9a; }
        .b-cita { background:#e3f2fd; color:#ffffff; border:1px solid #90caf9; display:block; margin:2px 0; }
        .controls { margin: 10px 0; display:flex; gap:10px; flex-wrap:wrap; }
        .controls form { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .status { font-size: 12px; color:#555; }
        .legend { margin:10px 0; }
        .legend span { margin-right:10px; }
        .weekdays { display:grid; grid-template-columns:repeat(7,1fr); font-weight:bold; text-align:center; margin-bottom:6px; }
        .alerta { background: linear-gradient(135deg, #ffecb3, #ffe082); border: 2px solid #ffb300; color: #bf360c; padding: 15px; border-radius: 10px; margin-bottom: 15px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); font-weight: bold; text-align: center; }
        .sticky-top { position: sticky; top: 0; background: #f9f9f9; padding: 8px 0; z-index: 10; }
        .small { font-size: 12px; }
        .btn { padding:6px 10px; border:1px solid #999; background:#f0f0f0; border-radius:4px; cursor:pointer; }
        .btn.primary { background:#1976d2; color:#fff; border-color:#0d47a1; }
        .btn.warn { background:#e53935; color:#fff; border-color:#b71c1c; }
        .btn.success { background:#2e7d32; color:#fff; border-color:#1b5e20; }
        .slot { display:flex; justify-content:space-between; align-items:center; gap:6px; }
        .slot-actions form { display:inline; }
        .table { width:100%; border-collapse: collapse; }
        .table th, .table td { border:1px solid #ddd; padding:6px; }
        .table th { background:#f3f3f3; }
        body { background-color: #ffffff; background-image: none; }
        /* Modal styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        #slots-list { margin-top: 20px; }
        #slots-list .slot-item { display: flex; justify-content: space-between; align-items: center; padding: 8px; border-bottom: 1px solid #ddd; }
        #slots-list .slot-item:last-child { border-bottom: none; }
        /* Toast styles */
        .toast { 
            position: fixed; 
            bottom: 20px; 
            right: 20px; 
            background: linear-gradient(135deg, #198754 0%, #146c43 100%); 
            color: #fff; 
            padding: 20px 25px; 
            border-radius: 12px; 
            box-shadow: 0 6px 20px rgba(25,135,84,0.3); 
            z-index: 1001; 
            opacity: 0; 
            transition: opacity 0.5s, transform 0.5s;
            transform: translateY(100px);
            max-width: 350px;
            border: 2px solid #fff;
        }
        .toast.show { 
            opacity: 1; 
            transform: translateY(0);
        }
        .toast i {
            font-size: 1.5rem;
            margin-right: 10px;
            vertical-align: middle;
        }
        .toast-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .toast-close {
            position: absolute;
            top: 8px;
            right: 8px;
            background: none;
            border: none;
            color: #fff;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.3s;
        }
        .toast-close:hover {
            opacity: 1;
        }
        .bg-gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .btn-primary {
            background-color: #198754;
            border-color: #198754;
        }
        .btn-primary:hover {
            background-color: #146c43;
            border-color: #13653f;
        }
        .bg-primary {
            background-color: #198754 !important;
        }
        .alert {
            border-radius: 0.375rem;
        }
    </style>
</head>
<body>
        <div class="container py-4">
            <div class="card shadow-lg mb-4" style="border-radius: 24px;">
                <div class="card-header d-flex justify-content-between align-items-center bg-white position-relative" style="border-radius: 18px 18px 0 0;">
                    <div class="d-flex align-items-center gap-3">
                        <img src="<?php echo htmlspecialchars($medicos[$medico_id]['imagen'] ?? ''); ?>" alt="Foto Médico" class="rounded-circle" style="width: 60px; height: 60px; object-fit: cover; border: 2px solid #198754;">
                        <div>
                            <h3 class="mb-0" style="font-weight:700; color:#198754; font-size:1.8rem;"> <?php echo htmlspecialchars($medicos[$medico_id]['nombre'] ?? ''); ?> </h3>
                            <span class="badge" style="font-size:1.1rem; background-color:#198754; color:#ffffff;"> <?php echo htmlspecialchars($medicos[$medico_id]['especialidad'] ?? ''); ?> </span>
                        </div>
                    </div>
                    <button class="btn" style="position: absolute; top: 18px; right: 18px; background-color:#198754; color:#ffffff; border-color:#198754;" onclick="backToSelection()">
                        <i class="bi bi-person-lines-fill"></i> Seleccionar Otro Médico
                    </button>
                </div>
                <div class="card-body" style="border-radius: 0 0 18px 18px; background-color:#ffffff;">
                    <div class="mb-3" style="font-size:1.1rem;">
                        <span class="me-3"><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($medicos[$medico_id]['email'] ?? ''); ?></span>
                        <span><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($medicos[$medico_id]['telefono'] ?? ''); ?></span>
                    </div>
                    <h2 class="mb-3" style="font-weight:600; color:#198754; background-color:#ffffff; padding:15px; border-radius:10px; font-size:2rem;">Gestión de citas del médico</h2>
                    <div class="status mb-2" style="font-size:1.1rem;">
                        Mes: <?php echo monthNameEs($month) . ' ' . $year; ?> | Médico ID: <?php echo htmlspecialchars((string)$medico_id); ?>
                    </div>
                    <?php if ($response_msg): ?>
                        <div class="alerta" style="background:#d1e7dd;border-color:#a3cfbb;color:#0d5132;"> <?php echo htmlspecialchars($response_msg); ?> </div>
                    <?php endif; ?>
                    <?php if ($response_err): ?>
                        <div class="alerta" style="background:#ffebee;border-color:#ef9a9a;color:#b71c1c;">Error: <?php echo htmlspecialchars($response_err); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($alertas)): ?>
                        <script>
                                showToast('Tiene paciente(s) en espera próxim@ a la hora confirmada: <?php echo addslashes(implode(', ', $alertas)); ?>');
                        </script>
                    <?php endif; ?>
                    <div class="controls sticky-top mb-3" style="font-size:1.05rem;">
                        <form method="get" class="mb-2">
                                <input type="hidden" name="medico_id" value="<?php echo (int)$medico_id; ?>" />
                                <label class="me-2">Mes:
                                        <select name="month" onchange="this.form.submit()" class="form-select d-inline-block w-auto">
                                                <?php for($m=1; $m<=12; $m++): ?>
                                                        <option value="<?php echo $m; ?>" <?php echo $m==$month?'selected':''; ?>><?php echo monthNameEs($m); ?></option>
                                                <?php endfor; ?>
                                        </select>
                                </label>
                                <label class="me-2">Año:
                                        <input type="number" name="year" value="<?php echo (int)$year; ?>" min="1970" max="2100" onchange="this.form.submit()" class="form-control d-inline-block w-auto" />
                                </label>
                                <noscript><button type="submit" class="btn btn-primary">Ir</button></noscript>
                        </form>
                        <form method="post" class="mb-2">
                                <input type="hidden" name="action" value="add_availability">
                                <input type="hidden" name="medico_id" value="<?php echo (int)$medico_id; ?>">
                                <label class="me-2">Fecha: <input type="date" name="fecha" required class="form-control d-inline-block w-auto"></label>
                                <label class="me-2">Desde: <input type="time" name="desde" required class="form-control d-inline-block w-auto"></label>
                                <label class="me-2">Hasta: <input type="time" name="hasta" required class="form-control d-inline-block w-auto"></label>
                                <label class="me-2">Intervalo (min): <input type="number" min="5" step="5" name="intervalo" value="30" class="form-control d-inline-block w-auto"></label>
                                <button class="btn" style="background-color:#198754; color:#ffffff; border-color:#198754;" type="submit">Agregar disponibilidad</button>
                        </form>
                    </div>
                    <div class="legend mb-3">
                        <span class="badge b-libre">Libre</span>
                        <span class="badge b-bloq">Bloqueado</span>
                        <span class="badge b-cita">Cita programada</span>
                    </div>
                    <div class="d-flex justify-content-center mb-4">
                        <div id="calendar"></div>
                    </div>
                    <h4 class="mb-3" style="font-weight:600; color:#198754; font-size:1.6rem;">Listado de citas del mes</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                        <th>Fecha</th>
                                        <th>Hora</th>
                                        <th>Nombre completo</th>
                                        <th>Motivo</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            foreach ($citas as $fecha => $list) {
                                    foreach ($list as $c) {
                                            echo '<tr>';
                                            echo '<td>'.htmlspecialchars($fecha).'</td>';
                                            echo '<td>'.htmlspecialchars(substr($c['hora'],0,5)).'</td>';
                                            echo '<td>'.htmlspecialchars($c['nombre_completo'] ?? '').'</td>';
                                            echo '<td>'.htmlspecialchars($c['motivo'] ?? '').'</td>';
                                            echo '<td>'.htmlspecialchars($c['estado']).'</td>';
                                            echo '<td>';
                                            echo '<form method="post" style="display:inline-block">';
                                            echo '<input type="hidden" name="action" value="update_estado_cita">';
                                            echo '<input type="hidden" name="cita_id" value="' . (int)$c['id'] . '">';
                                            echo '<input type="hidden" name="medico_id" value="' . (int)$medico_id . '">';
                                            echo '<select name="nuevo_estado" class="form-select form-select-sm d-inline-block w-auto">';
                                            $estados = ['pendiente'=>'Pendiente','confirmada'=>'Confirmada','cancelada'=>'Cancelada','completada'=>'Completada'];
                                            foreach ($estados as $k=>$v) {
                                                    $sel = $c['estado']===$k ? 'selected' : '';
                                                    echo '<option value="'.$k.'" '.$sel.'>'.$v.'</option>';
                                            }
                                            echo '</select> ';
                                            echo '<button class="btn btn-sm ms-1" style="background-color:#198754; color:#ffffff; border-color:#198754;" type="submit">Guardar</button>';
                                            echo '</form>';
                                            echo '</td>';
                                            echo '</tr>';
                                    }
                            }
                            if (empty($citas)) {
                                    echo '<tr><td colspan="6" style="text-align:center;">No hay citas registradas este mes.</td></tr>';
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- Modal Detalles de Cita -->
            <div id="cita-details" class="card shadow" style="position: fixed; right: 10px; top: 50%; transform: translateY(-50%); width: 300px; background: #fff; border-radius: 18px; padding: 18px; box-shadow: 0 4px 16px rgba(25,118,210,0.12); display: none; z-index: 1050;">
                <h5 class="mb-3" style="color:#1976d2; font-weight:700;">Detalles de la Cita</h5>
                <p><strong>Nombre:</strong> <span id="cita-nombre"></span></p>
                <p><strong>Estado:</strong> <span id="cita-estado"></span></p>
                <p><strong>Motivo:</strong> <span id="cita-motivo"></span></p>
                <button onclick="closeCitaDetails()" class="btn btn-outline-primary w-100 mt-2">Cerrar</button>
            </div>
            <!-- Modal -->
            <div id="modal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal()">&times;</span>
                    <h2>Slots para <span id="modal-date"></span></h2>
                    <div id="slots-list"></div>
                </div>
            </div>
        </div>

    <script>
        // PHP data to JS
        var citas = <?php echo json_encode($citas); ?>;
        var disp = <?php echo json_encode($disp); ?>;
        var medico_id = <?php echo (int)$medico_id; ?>;

        // Compute days with available slots
        var hasAvailable = {};
        for (var date in disp) {
            for (var hora in disp[date]) {
                if (disp[date][hora] === 'libre') {
                    hasAvailable[date] = true;
                    break;
                }
            }
        }

        // Prepare events for FullCalendar
        var events = [];
        for (var date in citas) {
            citas[date].forEach(function(c) {
                events.push({
                    title: c.nombre_completo || 'Sin nombre',
                    start: date + 'T' + c.hora,
                    allDay: false,
                    extendedProps: { estado: c.estado, motivo: c.motivo }
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                initialDate: '<?php echo $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01'; ?>',
                events: events,
                dateClick: function(info) {
                    openModal(info.dateStr);
                },
                eventClick: function(info) {
                    // Show cita details in lateral div
                    document.getElementById('cita-nombre').innerText = info.event.title;
                    document.getElementById('cita-estado').innerText = info.event.extendedProps.estado;
                    document.getElementById('cita-motivo').innerText = info.event.extendedProps.motivo || 'N/A';
                    document.getElementById('cita-details').style.display = 'block';
                },
                dayCellDidMount: function(info) {
                    var dateStr = info.date.toISOString().split('T')[0];
                    if (hasAvailable[dateStr]) {
                        info.el.style.backgroundColor = '#e9ecef'; // gris claro disponible
                    } else {
                        info.el.style.backgroundColor = '#ffffff'; // blanco sin disponibilidad
                    }
                    // Forzar color negro para números
                    var dayNumber = info.el.querySelector('.fc-daygrid-day-number');
                    if (dayNumber) {
                        dayNumber.style.color = '#000';
                    }
                }
            });
            calendar.render();
        });

        function openModal(date) {
            document.getElementById('modal-date').innerText = date;
            var slotsList = document.getElementById('slots-list');
            slotsList.innerHTML = '';
            if (disp[date]) {
                for (var hora in disp[date]) {
                    var estado = disp[date][hora];
                    var slotDiv = document.createElement('div');
                    slotDiv.className = 'slot-item';
                    slotDiv.innerHTML = '<span>' + hora.substring(0,5) + ' - ' + estado + '</span>' +
                        '<button class="btn ' + (estado === 'libre' ? 'warn' : 'success') + '" onclick="toggleSlot(\'' + date + '\', \'' + hora + '\', \'' + (estado === 'libre' ? 'bloqueado' : 'libre') + '\')">' + (estado === 'libre' ? 'Bloquear' : 'Liberar') + '</button>';
                    slotsList.appendChild(slotDiv);
                }
            } else {
                slotsList.innerHTML = '<p>No hay slots disponibles para esta fecha.</p>';
            }
            document.getElementById('modal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }

        function toggleSlot(fecha, hora, nuevoEstado) {
            $.post('', {
                action: 'toggle_slot',
                medico_id: medico_id,
                fecha: fecha,
                hora: hora,
                estado: nuevoEstado
            }, function(data) {
                // Reload page or update disp
                location.reload();
            });
        }

        // Close modal on outside click
        window.onclick = function(event) {
            var modal = document.getElementById('modal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Toast function
        function showToast(message) {
            var toast = document.createElement('div');
            toast.className = 'toast';
            
            var closeBtn = document.createElement('button');
            closeBtn.className = 'toast-close';
            closeBtn.innerHTML = '&times;';
            closeBtn.onclick = function() {
                closeToast(toast);
            };
            
            var content = document.createElement('div');
            content.className = 'toast-content';
            content.innerHTML = '<i class="bi bi-bell-fill"></i><span>' + message + '</span>';
            
            toast.appendChild(closeBtn);
            toast.appendChild(content);
            document.body.appendChild(toast);
            
            setTimeout(function() { toast.classList.add('show'); }, 100);
            setTimeout(function() {
                closeToast(toast);
            }, 8000);
        }
        
        function closeToast(toast) {
            toast.classList.remove('show');
            setTimeout(function() { 
                if(toast.parentNode) {
                    document.body.removeChild(toast); 
                }
            }, 500);
        }

        function closeCitaDetails() {
            document.getElementById('cita-details').style.display = 'none';
        }

        function backToSelection() {
            window.location.href = '?';
        }
    </script>

    <script src="assets/js/script.js"></script>
</body>
</html>
