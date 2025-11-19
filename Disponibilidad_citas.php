<?php
// Disponibilidad_citas.php
// Página para pacientes: ver disponibilidad de médicos y agendar citas.
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

// Identificador del médico (seleccionado por paciente)
$medico_id = intval(get('medico_id', 0));

// Obtener nombre del usuario logueado
$userName = $_SESSION['nombre'] ?? '';

// Obtener citas confirmadas del paciente
$citas_confirmadas = [];
if ($userName) {
    $stmt = $conn->prepare("SELECT nombre_completo, motivo, fecha, hora FROM citas WHERE nombre_completo = ? AND estado = 'confirmada' ORDER BY fecha ASC, hora ASC");
    $stmt->bind_param('s', $userName);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $citas_confirmadas[] = $row;
    }
}

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
        <title>Agendar Cita - Disponibilidad de Médicos</title>
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
        </style>
    </head>
    <body>
        <div class="header-section text-center">
            <div class="medical-icon">
                <i class="bi bi-calendar-check" style="font-size:3rem;"></i>
            </div>
            <h1>Agendar Cita - Seleccionar Médico</h1>
            <p>Haga clic en el médico para ver su calendario de disponibilidad.</p>
        </div>
        <div class="container">
            <div class="row justify-content-center">
                <?php foreach ($medicos as $id => $medico): ?>
                    <div class="col-md-3 mb-4">
                        <div class="card card-medico text-center h-100" onclick="selectMedico(<?php echo $id; ?>)" style="cursor:pointer;">
                            <div class="card-body">
                                <img src="<?php echo htmlspecialchars($medico['imagen']); ?>" alt="Foto de <?php echo htmlspecialchars($medico['nombre']); ?>" class="medico-img">
                                <h5 class="card-title mb-1"><?php echo htmlspecialchars($medico['nombre']); ?></h5>
                                <div class="text-success mb-1"><?php echo htmlspecialchars($medico['especialidad']); ?></div>
                                <div class="small text-muted mb-1"><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($medico['email']); ?></div>
                                <div class="small text-muted"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($medico['telefono']); ?></div>
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

// Acciones AJAX
$action = post('action');
$response = ['success' => false, 'message' => ''];
if ($action === 'schedule_appointment') {
    $fecha = post('fecha');
    $hora = post('hora');
    $nombre_completo = post('nombre_completo');
    $motivo = post('motivo');
    try {
        // Verificar que el slot esté libre
        $stmt = $conn->prepare("SELECT estado FROM disponibilidades WHERE medico_id=? AND fecha=? AND hora=?");
        $stmt->bind_param('iss', $medico_id, $fecha, $hora);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0 || $res->fetch_assoc()['estado'] !== 'libre') {
            throw new Exception('El horario seleccionado no está disponible.');
        }
        // Insertar cita
        $stmt = $conn->prepare("INSERT INTO citas (medico_id, nombre_completo, fecha, hora, motivo, estado) VALUES (?, ?, ?, ?, ?, 'pendiente')");
        $stmt->bind_param('issss', $medico_id, $nombre_completo, $fecha, $hora, $motivo);
        if (!$stmt->execute()) {
            throw new Exception('Error al agendar la cita: ' . $stmt->error);
        }
        // Actualizar disponibilidad a bloqueado (opcional, ya que unique key previene doble booking)
        $stmt = $conn->prepare("UPDATE disponibilidades SET estado='bloqueado' WHERE medico_id=? AND fecha=? AND hora=?");
        $stmt->bind_param('iss', $medico_id, $fecha, $hora);
        $stmt->execute();
        $response = ['success' => true, 'message' => 'Cita agendada exitosamente.'];
        $_SESSION['message'] = $response['message'];
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
        $_SESSION['error'] = $response['message'];
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Consultas para el mes
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = (new DateTime($monthStart))->modify('last day of this month')->format('Y-m-d');

// Disponibilidades del mes
$disp = [];
$res = $conn->prepare("SELECT fecha, hora, estado FROM disponibilidades WHERE medico_id=? AND fecha BETWEEN ? AND ? AND estado='libre' ORDER BY fecha, hora");
$res->bind_param('iss', $medico_id, $monthStart, $monthEnd);
$res->execute();
$r = $res->get_result();
while ($row = $r->fetch_assoc()) {
    $disp[$row['fecha']][$row['hora']] = $row['estado'];
}

// Citas del mes (para mostrar ocupados)
$citas = [];
$qr = $conn->prepare("SELECT fecha, hora FROM citas WHERE medico_id=? AND fecha BETWEEN ? AND ? ORDER BY fecha, hora");
$qr->bind_param('iss', $medico_id, $monthStart, $monthEnd);
$qr->execute();
$rc = $qr->get_result();
while ($row = $rc->fetch_assoc()) {
    $citas[$row['fecha']][$row['hora']] = true;
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
    <title>Disponibilidad de Citas</title>
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
    <style>
        body { background-color: #ffffff; background-image: none; }
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
        .fc {
            font-size: 16px;
        }
        .fc-daygrid-day {
            min-height: 100px;
        }
        .fc-daygrid-day-number {
            font-size: 16px;
            font-weight: 600;
            color: #000 !important;
        }
        .fc-col-header-cell-cushion {
            font-size: 16px;
            font-weight: 700;
            color: #000 !important;
        }
        .fc-daygrid-day-top {
            padding: 8px;
        }
        .fc-event {
            font-size: 14px;
            padding: 4px;
        }
        .badge-libre {
            background:#198754;
            color:#fff;
            font-size: 1rem;
        }
        .badge-bloq {
            background:#6c757d;
            color:#fff;
            font-size: 1rem;
        }
        .controls { margin: 10px 0; display:flex; gap:10px; flex-wrap:wrap; }
        .controls form { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .status { font-size: 1rem; color:#555; margin-bottom: 10px; }
        .legend { margin:10px 0; }
        .legend span { margin-right:10px; font-size: 1rem; }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .btn-success {
            background-color: #198754;
            border-color: #198754;
        }
        .btn-success:hover {
            background-color: #146c43;
            border-color: #13653f;
        }
        .bg-primary {
            background-color: #198754 !important;
        }
        .btn-select-medico {
            position: absolute;
            top: 20px;
            right: 30px;
            z-index: 10;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from { 
                transform: translate(-50%, -60%);
                opacity: 0;
            }
            to { 
                transform: translate(-50%, -50%);
                opacity: 1;
            }
        }
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 1.5rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease-out;
            border: 3px solid #198754;
        }
        .modal-content h2 {
            color: #198754;
            font-weight: 700;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .close {
            position: absolute;
            top: 15px;
            right: 20px;
            color: #198754;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: transparent;
        }
        .close:hover { 
            color: #fff;
            background-color: #198754;
            transform: rotate(90deg);
        }
        #slots-list { 
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        #slots-list .slot-item { 
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            background: white;
            border-radius: 12px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        #slots-list .slot-item:hover {
            border-color: #198754;
            box-shadow: 0 4px 12px rgba(25, 135, 84, 0.15);
            transform: translateX(5px);
        }
        #slots-list .slot-item span {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }
        #slots-list .slot-item button {
            background-color: #198754;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            cursor: pointer;
        }
        #slots-list .slot-item button:hover {
            background-color: #146c43;
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(25, 135, 84, 0.3);
        }
        .toast { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #333; color: #fff; padding: 15px; border-radius: 1rem; box-shadow: 0 4px 8px rgba(0,0,0,0.3); z-index: 1001; opacity: 0; transition: opacity 0.5s; }
        .toast.show { opacity: 1; }
    </style>
</head>
<body>
    <!-- Botón se moverá dentro del card de controles -->
    <div class="header-section text-center mb-4" style="background: linear-gradient(135deg, #198754 0%, #146c43 100%); color: white; padding: 2rem 0;">
        <div class="medical-icon mb-2">
            <i class="bi bi-calendar-week" style="font-size:3rem;"></i>
        </div>
        <h1 class="fw-bold">Disponibilidad de Citas</h1>
        <p class="lead">Consulta y agenda tus citas con nuestros médicos especialistas.</p>
    </div>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Error: <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <div class="card shadow-lg mb-4" style="border-radius: 24px;">
                    <div class="card-header d-flex justify-content-between align-items-center bg-white position-relative" style="border-radius: 18px 18px 0 0;">
                        <div class="d-flex align-items-center gap-3">
                            <?php if (isset($medicos[$medico_id])): ?>
                                <img src="<?php echo htmlspecialchars($medicos[$medico_id]['imagen']); ?>" alt="<?php echo htmlspecialchars($medicos[$medico_id]['nombre']); ?>" class="rounded-circle" style="width: 60px; height: 60px; object-fit: cover; border: 3px solid #198754;">
                                <div>
                                    <h4 class="mb-1" style="color: #198754; font-size: 1.8rem; font-weight: 700;"><?php echo htmlspecialchars($medicos[$medico_id]['nombre']); ?></h4>
                                    <span class="badge" style="background-color: #198754; color: white; font-size: 1.1rem; padding: 6px 12px;"><?php echo htmlspecialchars($medicos[$medico_id]['especialidad']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button class="btn" style="position: absolute; top: 18px; right: 18px; background-color:#198754; color:#ffffff; border-color:#198754;" onclick="backToSelection()">
                            <i class="bi bi-arrow-left-circle"></i> Seleccionar Otro Médico
                        </button>
                    </div>
                    <div class="card-body" style="border-radius: 0 0 18px 18px; background-color:#ffffff;">
                        <div class="text-center mb-4" style="background-color: white; padding: 15px; border-radius: 10px;">
                            <h3 style="color: #198754; font-size: 2rem; font-weight: 700; margin: 0;">Disponibilidad de Citas</h3>
                        </div>
                        <div class="controls mb-3">
                            <form method="get" class="d-flex align-items-center">
                                <input type="hidden" name="medico_id" value="<?php echo (int)$medico_id; ?>" />
                                <label class="form-label mb-0 me-2" style="font-size: 1.05rem;">Mes:</label>
                                <select name="month" class="form-select me-2" style="width:auto;display:inline-block; font-size: 1.05rem;" onchange="this.form.submit()">
                                    <?php for($m=1; $m<=12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo $m==$month?'selected':''; ?>><?php echo monthNameEs($m); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <label class="form-label mb-0 me-2" style="font-size: 1.05rem;">Año:</label>
                                <input type="number" name="year" class="form-control me-2" style="width:100px;display:inline-block; font-size: 1.05rem;" value="<?php echo (int)$year; ?>" min="1970" max="2100" onchange="this.form.submit()" />
                                <noscript><button type="submit" class="btn btn-success">Ir</button></noscript>
                            </form>
                        </div>
                        <div class="legend mb-3">
                            <span class="badge badge-libre"><i class="bi bi-check-circle"></i> Disponible</span>
                            <span class="badge badge-bloq"><i class="bi bi-x-circle"></i> Sin disponibilidad</span>
                        </div>
                        <div id="calendar"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="modal" class="modal">
        <div class="modal-content p-4">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 class="mb-3">
                <i class="bi bi-clock-fill"></i> Horarios Disponibles
            </h2>
            <p class="text-muted mb-3"><i class="bi bi-calendar3"></i> Fecha: <strong id="modal-date" style="color: #198754;"></strong></p>
            <div id="slots-list"></div>
        </div>
    </div>

    <!-- Appointment Modal -->
    <div id="appointment-modal" class="modal">
        <div class="modal-content p-4">
            <span class="close" onclick="closeAppointmentModal()">&times;</span>
            <h2 class="mb-3"><i class="bi bi-calendar-plus"></i> Agendar Cita</h2>
            <p class="mb-3">Hola, <span class="fw-bold text-success"><?php echo htmlspecialchars($userName); ?></span>!</p>
            <form id="appointment-form">
                <input type="hidden" id="appointment-fecha" name="fecha">
                <input type="hidden" id="appointment-hora" name="hora">
                <input type="hidden" name="action" value="schedule_appointment">
                <input type="hidden" name="medico_id" value="<?php echo (int)$medico_id; ?>">
                <div class="mb-3">
                    <label for="nombre_completo" class="form-label">Nombre Completo:</label>
                    <input type="text" id="nombre_completo" name="nombre_completo" class="form-control" value="<?php echo htmlspecialchars($userName); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="motivo" class="form-label">Motivo de la Consulta:</label>
                    <textarea id="motivo" name="motivo" class="form-control" rows="3" required></textarea>
                </div>
                <button type="submit" class="btn btn-success w-100"><i class="bi bi-calendar-plus"></i> Agendar Cita</button>
            </form>
        </div>
    </div>

    <script>
        // PHP data to JS
        var disp = <?php echo json_encode($disp); ?>;
        var citas = <?php echo json_encode($citas); ?>;
        var medico_id = <?php echo (int)$medico_id; ?>;

        // Compute days with available slots
        var hasAvailable = {};
        for (var date in disp) {
            if (Object.keys(disp[date]).length > 0) {
                hasAvailable[date] = true;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                initialDate: '<?php echo $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01'; ?>',
                dateClick: function(info) {
                    openModal(info.dateStr);
                },
                dayCellDidMount: function(info) {
                    var dateStr = info.date.toISOString().split('T')[0];
                    if (hasAvailable[dateStr]) {
                        info.el.style.backgroundColor = '#ffffff'; // blanco disponible
                    } else {
                        info.el.style.backgroundColor = '#e9ecef'; // gris claro sin disponibilidad
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
                    var slotDiv = document.createElement('div');
                    slotDiv.className = 'slot-item';
                    slotDiv.innerHTML = '<span><i class="bi bi-clock"></i> ' + hora.substring(0,5) + '</span>' +
                        '<button onclick="scheduleAppointment(\'' + date + '\', \'' + hora + '\')"><i class="bi bi-calendar-plus"></i> Agendar</button>';
                    slotsList.appendChild(slotDiv);
                }
            } else {
                slotsList.innerHTML = '<div style="text-align:center; padding:40px; color:#6c757d;"><i class="bi bi-info-circle" style="font-size:3rem; margin-bottom:15px;"></i><p style="font-size:1.1rem;">No hay horarios disponibles para esta fecha.</p></div>';
            }
            document.getElementById('modal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }

        function scheduleAppointment(fecha, hora) {
            document.getElementById('appointment-fecha').value = fecha;
            document.getElementById('appointment-hora').value = hora;
            document.getElementById('appointment-modal').style.display = 'block';
        }

        function closeAppointmentModal() {
            document.getElementById('appointment-modal').style.display = 'none';
        }

        $(document).ready(function() {
            $('#appointment-form').on('submit', function(e) {
                e.preventDefault();
                $.post('', $(this).serialize(), function(data) {
                    if (data.success) {
                        showToast(data.message);
                        closeAppointmentModal();
                        closeModal();
                        // Reload calendar to update availability
                        location.reload();
                    } else {
                        showToast('Error: ' + data.message);
                    }
                }, 'json');
            });
        });

        // Close modal on outside click
        window.onclick = function(event) {
            var modal = document.getElementById('modal');
            var appointmentModal = document.getElementById('appointment-modal');
            if (event.target == modal) {
                closeModal();
            }
            if (event.target == appointmentModal) {
                closeAppointmentModal();
            }
        }

        // Toast function
        function showToast(message) {
            var toast = document.createElement('div');
            toast.className = 'toast';
            toast.innerText = message;
            document.body.appendChild(toast);
            setTimeout(function() { toast.classList.add('show'); }, 100);
            setTimeout(function() {
                toast.classList.remove('show');
                setTimeout(function() { document.body.removeChild(toast); }, 500);
            }, 3000);
        }

        function backToSelection() {
            window.location.href = '?';
        }
    </script>

    <script src="assets/js/script.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
