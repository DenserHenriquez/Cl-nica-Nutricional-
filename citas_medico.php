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
$medico_id = intval(get('medico_id', post('medico_id', 1)));

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
    <style>
        .calendar { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; }
        .day { border: 1px solid #ccc; min-height: 140px; padding: 6px; background: #fff; position: relative; }
        .day .date { font-weight: bold; }
        .day .items { margin-top: 6px; max-height: 100px; overflow-y: auto; }
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
        .alerta { background:#fff3cd; border:1px solid #ffeeba; color:#856404; padding:10px; border-radius:4px; margin-bottom:10px; }
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
        body { background-color: #87CEEB; background-image: none; }
    </style>
</head>
<body>
    <div style="position: absolute; top: 10px; right: 10px;">
        <a href="Menuprincipal.php" class="btn">Menu Principal</a>
    </div>
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
                alert('Tiene paciente(s) en espera próxim@ a la hora confirmada: <?php echo addslashes(implode(', ', $alertas)); ?>');
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

        <div class="weekdays">
            <div>Lun</div><div>Mar</div><div>Mié</div><div>Jue</div><div>Vie</div><div>Sáb</div><div>Dom</div>
        </div>
        <div class="calendar">
            <?php
            // Relleno de días vacíos antes del 1er día
            for ($i=1; $i<$startWeekday; $i++) echo '<div></div>';
            for ($day=1; $day<=$daysInMonth; $day++) {
                $fecha = sprintf('%04d-%02d-%02d', $year, $month, $day);
                echo '<div class="day">';
                echo '<div class="date">' . $day . '</div>';
                echo '<div class="items">';
                // Citas del día
                if (isset($citas[$fecha])) {
                    foreach ($citas[$fecha] as $c) {
                        $label = htmlspecialchars($c['nombre_completo'] ?: 'Sin nombre') . ' - ' . substr($c['hora'],0,5);
                        $motivo = htmlspecialchars($c['motivo'] ?? '');
                        echo '<div class="b-cita">' . $label . '<br><span class="small">Motivo: ' . $motivo . '</span><br>';
                        echo '<form method="post" class="small" style="margin-top:4px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">';
                        echo '<input type="hidden" name="action" value="update_estado_cita">';
                        echo '<input type="hidden" name="cita_id" value="' . (int)$c['id'] . '">';
                        echo '<input type="hidden" name="medico_id" value="' . (int)$medico_id . '">';
                        echo '<label>Estado: <select name="nuevo_estado">';
                        $estados = ['pendiente'=>'Pendiente','confirmada'=>'Confirmada','cancelada'=>'Cancelada','completada'=>'Completada'];
                        foreach ($estados as $k=>$v) {
                            $sel = $c['estado']===$k ? 'selected' : '';
                            echo '<option value="'.$k.'" '.$sel.'>'.$v.'</option>';
                        }
                        echo '</select></label>';
                        echo '<button class="btn success" type="submit">Actualizar</button>';
                        echo '</form>';
                        echo '</div>';
                    }
                }
                // Disponibilidades del día
                if (isset($disp[$fecha])) {
                    foreach ($disp[$fecha] as $hora => $estadoSlot) {
                        $badge = $estadoSlot==='libre' ? 'b-libre' : 'b-bloq';
                        echo '<div class="slot"><span class="badge '.$badge.'">' . substr($hora,0,5) . ' - ' . $estadoSlot . '</span>';
                        echo '<span class="slot-actions">';
                        $nuevo = $estadoSlot==='libre' ? 'bloqueado' : 'libre';
                        echo '<form method="post" onsubmit="return confirm(\'¿Seguro?\')">';
                        echo '<input type="hidden" name="action" value="toggle_slot">';
                        echo '<input type="hidden" name="medico_id" value="' . (int)$medico_id . '">';
                        echo '<input type="hidden" name="fecha" value="' . $fecha . '">';
                        echo '<input type="hidden" name="hora" value="' . $hora . '">';
                        echo '<input type="hidden" name="estado" value="' . $nuevo . '">';
                        $btnClass = $estadoSlot==='libre' ? 'warn' : 'success';
                        $btnText = $estadoSlot==='libre' ? 'Bloquear' : 'Liberar';
                        echo '<button class="btn '.$btnClass.' small" type="submit">'.$btnText.'</button>';
                        echo '</form>';
                        echo '</span></div>';
                    }
                }
                echo '</div>';
                echo '</div>';
            }
            ?>
        </div>

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

    <script src="assets/js/script.js"></script>
</body>
</html>