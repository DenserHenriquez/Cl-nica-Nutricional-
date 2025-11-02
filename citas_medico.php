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

// Datos estáticos de médicos (en producción, de BD)
$medicos = [
    1 => ['nombre' => 'Dr. Juan Pérez', 'especialidad' => 'Nutrición General'],
    2 => ['nombre' => 'Dra. María García', 'especialidad' => 'Nutrición Deportiva'],
    3 => ['nombre' => 'Dr. Carlos López', 'especialidad' => 'Nutrición Clínica'],
    4 => ['nombre' => 'Dra. Ana Rodríguez', 'especialidad' => 'Nutrición Pediátrica']
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
            .medico-icon { width: 60px; height: 60px; margin: 0 auto 10px; background: #1976d2; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
            .medico-icon svg { width: 30px; height: 30px; fill: #fff; }
            .medico-nombre { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
            .medico-especialidad { color: #666; }
            .back-btn { position: absolute; top: 10px; right: 10px; padding: 8px 16px; background: #1976d2; color: #fff; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
        </style>
    </head>
    <body>
        <a href="Menuprincipal.php" class="back-btn">Menú Principal</a>
        <div class="container">
            <h1>Seleccionar Médico para Gestión de Citas</h1>
            <p>Haga clic en el médico para ver su calendario de citas.</p>
            <div class="medicos-grid">
                <?php foreach ($medicos as $id => $medico): ?>
                    <a href="?medico_id=<?php echo $id; ?>" class="medico-card">
                        <div class="medico-icon">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 12a5 5 0 1 0-5-5 5.006 5.006 0 0 0 5 5zm-7 9a7 7 0 0 1 14 0 1 1 0 0 1-1 1H6a1 1 0 0 1-1-1z"/>
                            </svg>
                        </div>
                        <div class="medico-nombre"><?php echo htmlspecialchars($medico['nombre']); ?></div>
                        <div class="medico-especialidad"><?php echo htmlspecialchars($medico['especialidad']); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
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
        .badge { display:inline-block; padding:2px 6px; border-radius:4px; font-size:11px; margin-right:4px; }
        .b-libre { background:#e8f5e9; color:#2e7d32; border:1px solid #a5d6a7; }
        .b-bloq { background:#ffebee; color:#c62828; border:1px solid #ef9a9a; }
        .b-cita { background:#e3f2fd; color:#1565c0; border:1px solid #90caf9; display:block; margin:2px 0; }
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
<<<<<<< Updated upstream
    </style>
</head>
<body>
=======
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
    </div>
    <!-- Div lateral para detalles de cita -->
    <div id="cita-details" style="position: fixed; right: 10px; top: 50%; transform: translateY(-50%); width: 250px; background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 15px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); display: none;">
        <h3>Detalles de la Cita</h3>
        <p><strong>Nombre:</strong> <span id="cita-nombre"></span></p>
        <p><strong>Estado:</strong> <span id="cita-estado"></span></p>
        <p><strong>Motivo:</strong> <span id="cita-motivo"></span></p>
        <button onclick="closeCitaDetails()" class="btn">Cerrar</button>
    </div>
>>>>>>> Stashed changes
    <div class="container">
        <h1>Gestión de citas del médico</h1>
        <div class="status">
            Mes: <?php echo monthNameEs($month) . ' ' . $year; ?> | Médico ID: <?php echo htmlspecialchars((string)$medico_id); ?>
        </div>

        <?php if ($response_msg): ?>
            <div class="alerta" style="background:#e8f5e9;border-color:#a5d6a7;color:#1b5e20;"><?php echo htmlspecialchars($response_msg); ?></div>
        <?php endif; ?>
        <?php if ($response_err): ?>
            <div class="alerta" style="background:#ffebee;border-color:#ef9a9a;color:#b71c1c;">Error: <?php echo htmlspecialchars($response_err); ?></div>
        <?php endif; ?>

        <?php if (!empty($alertas)): ?>
            <div class="alerta">
                Alerta: Tiene paciente(s) en espera próximo(s) a su hora confirmada:
                <strong><?php echo htmlspecialchars(implode(', ', $alertas)); ?></strong>
            </div>
            <script>
                showToast('Tiene paciente(s) en espera próxim@ a la hora confirmada: <?php echo addslashes(implode(', ', $alertas)); ?>');
            </script>
        <?php endif; ?>

        <div class="controls sticky-top">
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

            <form method="post">
                <input type="hidden" name="action" value="add_availability">
                <input type="hidden" name="medico_id" value="<?php echo (int)$medico_id; ?>">
                <label>Fecha: <input type="date" name="fecha" required></label>
                <label>Desde: <input type="time" name="desde" required></label>
                <label>Hasta: <input type="time" name="hasta" required></label>
                <label>Intervalo (min): <input type="number" min="5" step="5" name="intervalo" value="30"></label>
                <button class="btn primary" type="submit">Agregar disponibilidad</button>
            </form>
        </div>

        <div class="legend">
            <span class="badge b-libre">Libre</span>
            <span class="badge b-bloq">Bloqueado</span>
            <span class="badge b-cita">Cita programada</span>
        </div>

        <div id="calendar"></div>

        <h2>Listado de citas del mes</h2>
        <table class="table">
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
                    echo '<select name="nuevo_estado">';
                    $estados = ['pendiente'=>'Pendiente','confirmada'=>'Confirmada','cancelada'=>'Cancelada','completada'=>'Completada'];
                    foreach ($estados as $k=>$v) {
                        $sel = $c['estado']===$k ? 'selected' : '';
                        echo '<option value="'.$k.'" '.$sel.'>'.$v.'</option>';
                    }
                    echo '</select> ';
                    echo '<button class="btn success" type="submit">Guardar</button>';
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

    <!-- Modal -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Slots para <span id="modal-date"></span></h2>
            <div id="slots-list"></div>
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
            toast.innerText = message;
            document.body.appendChild(toast);
            setTimeout(function() { toast.classList.add('show'); }, 100);
            setTimeout(function() {
                toast.classList.remove('show');
                setTimeout(function() { document.body.removeChild(toast); }, 500);
            }, 3000);
        }

        function closeCitaDetails() {
            document.getElementById('cita-details').style.display = 'none';
        }
    </script>

    <script src="assets/js/script.js"></script>
</body>
</html>
