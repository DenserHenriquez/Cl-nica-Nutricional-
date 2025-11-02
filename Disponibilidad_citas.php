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
        <link rel="stylesheet" href="assets/css/estilos.css">
        <style>
            body { background-color: #87CEEB; background-image: none; font-family: Arial, sans-serif; }
            .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
            .medicos-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
            .medico-card {
                background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                text-align: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;
                border: 2px solid #ddd;
            }
            .medico-card:hover { transform: translateY(-5px); box-shadow: 0 8px 16px rgba(0,0,0,0.2); border-color: #1976d2; }
            .medico-icon { width: 80px; height: 80px; margin: 0 auto 10px; border-radius: 50%; overflow: hidden; border: 2px solid #ddd; }
            .medico-icon img { width: 100%; height: 100%; object-fit: cover; }
            .medico-nombre { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
            .medico-especialidad { color: #666; margin-bottom: 5px; }
            .medico-email { font-size: 14px; color: #555; margin-bottom: 2px; }
            .medico-telefono { font-size: 14px; color: #555; }
            .back-btn { position: absolute; top: 10px; right: 10px; padding: 8px 16px; background: #1976d2; color: #fff; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
        </style>
    </head>
    <body>
        <a href="Menuprincipal.php" class="back-btn">Menú Principal</a>
        <div class="container">
            <h1>Agendar Cita - Seleccionar Médico</h1>
            <p>Haga clic en el médico para ver su calendario de disponibilidad.</p>
            <div class="medicos-grid">
                <?php foreach ($medicos as $id => $medico): ?>
                    <div class="medico-card" onclick="selectMedico(<?php echo $id; ?>)">
                        <div class="medico-icon">
                            <img src="<?php echo htmlspecialchars($medico['imagen']); ?>" alt="Foto de <?php echo htmlspecialchars($medico['nombre']); ?>">
                        </div>
                        <div class="medico-nombre"><?php echo htmlspecialchars($medico['nombre']); ?></div>
                        <div class="medico-especialidad"><?php echo htmlspecialchars($medico['especialidad']); ?></div>
                        <div class="medico-email"><?php echo htmlspecialchars($medico['email']); ?></div>
                        <div class="medico-telefono"><?php echo htmlspecialchars($medico['telefono']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <script>
            function selectMedico(id) {
                window.location.href = '?medico_id=' + id;
            }
        </script>
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
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
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
    <link rel="stylesheet" href="assets/css/estilos.css">
    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.min.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- FullCalendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <style>
        #calendar { max-width: 400px; margin: 0 auto; height: 300px; font-size: 12px; }
        .fc { font-size: 12px; }
        .fc-daygrid-day { min-height: 60px; }
        .fc-daygrid-day-number { font-size: 10px; }
        .fc-event { font-size: 10px; padding: 2px; }
        .status { font-size: 12px; color:#555; }
        .controls { margin: 10px 0; display:flex; gap:10px; flex-wrap:wrap; }
        .controls form { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .legend { margin:10px 0; }
        .legend span { margin-right:10px; }
        .btn { padding:6px 10px; border:1px solid #999; background:#f0f0f0; border-radius:4px; cursor:pointer; }
        .btn.primary { background:#1976d2; color:#fff; border-color:#0d47a1; }
        .btn.warn { background:#e53935; color:#fff; border-color:#b71c1c; }
        .btn.success { background:#2e7d32; color:#fff; border-color:#1b5e20; }
        body { background-color: #87CEEB; background-image: none; }
        /* Modal styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        #slots-list { margin-top: 20px; }
        #slots-list .slot-item { display: flex; justify-content: space-between; align-items: center; padding: 8px; border-bottom: 1px solid #ddd; }
        #slots-list .slot-item:last-child { border-bottom: none; }
        /* Toast styles */
        .toast { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #333; color: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.3); z-index: 1001; opacity: 0; transition: opacity 0.5s; }
        .toast.show { opacity: 1; }
    </style>
</head>
<body>
    <div style="position: absolute; top: 10px; right: 10px;">
        <a href="Menuprincipal.php" class="btn">Menu Principal</a>
        <button class="btn" onclick="backToSelection()">Seleccionar Otro Médico</button>
    </div>
    <div class="container">
        <h1>Disponibilidad de Citas</h1>
        <div class="status">
            Médico: <?php echo htmlspecialchars($medicos[$medico_id]['nombre'] ?? 'Desconocido'); ?> | Mes: <?php echo monthNameEs($month) . ' ' . $year; ?>
        </div>

        <div class="controls">
            <form method="get">
                <input type="hidden" name="medico_id" value="<?php echo (int)$medico_id; ?>" />
                <label>Mes:
                    <select name="month" onchange="this.form.submit()">
                        <?php for($m=1; $m<=12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m==$month?'selected':''; ?>><?php echo monthNameEs($m); ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
                <label>Año:
                    <input type="number" name="year" value="<?php echo (int)$year; ?>" min="1970" max="2100" onchange="this.form.submit()" />
                </label>
                <noscript><button type="submit" class="btn">Ir</button></noscript>
            </form>
        </div>

        <div class="legend">
            <span style="color:green;">Verde: Días con disponibilidad</span>
            <span style="color:blue;">Azul: Sin disponibilidad</span>
        </div>

        <div id="calendar"></div>
    </div>

    <!-- Modal -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Horarios Disponibles para <span id="modal-date"></span></h2>
            <div id="slots-list"></div>
        </div>
    </div>

    <!-- Appointment Modal -->
    <div id="appointment-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAppointmentModal()">&times;</span>
            <h2>Agendar Cita</h2>
            <p>Hola, <?php echo htmlspecialchars($userName); ?>!</p>
            <form id="appointment-form">
                <input type="hidden" id="appointment-fecha" name="fecha">
                <input type="hidden" id="appointment-hora" name="hora">
                <input type="hidden" name="action" value="schedule_appointment">
                <input type="hidden" name="medico_id" value="<?php echo (int)$medico_id; ?>">
                <label for="nombre_completo">Nombre Completo:</label><br>
                <input type="text" id="nombre_completo" name="nombre_completo" value="<?php echo htmlspecialchars($userName); ?>" required><br><br>
                <label for="motivo">Motivo de la Consulta:</label><br>
                <textarea id="motivo" name="motivo" rows="4" cols="50" required></textarea><br><br>
                <button type="submit" class="btn primary">Agendar Cita</button>
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
                        info.el.style.backgroundColor = 'green';
                    } else {
                        info.el.style.backgroundColor = 'blue';
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
                    slotDiv.innerHTML = '<span>' + hora.substring(0,5) + '</span>' +
                        '<button class="btn primary" onclick="scheduleAppointment(\'' + date + '\', \'' + hora + '\')">Agendar</button>';
                    slotsList.appendChild(slotDiv);
                }
            } else {
                slotsList.innerHTML = '<p>No hay horarios disponibles para esta fecha.</p>';
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
</body>
</html>
