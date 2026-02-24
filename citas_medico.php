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

// Identificador del médico seleccionado
$medico_id = intval(get('medico_id', post('medico_id', 0)));

// Asegurar columnas opcionales en usuarios (imagen, especialidad, telefono)
foreach (['imagen VARCHAR(500)', 'especialidad VARCHAR(255)', 'telefono VARCHAR(30)'] as $colDef) {
    $colName = explode(' ', $colDef)[0];
    $chk = $conn->query("SHOW COLUMNS FROM usuarios LIKE '$colName'");
    if ($chk && $chk->num_rows === 0) {
        @$conn->query("ALTER TABLE usuarios ADD COLUMN $colDef DEFAULT NULL");
    }
}

// Obtener lista de médicos desde la tabla usuarios con rol='Medico'
$medicos = [];
// Seleccionar columnas adicionales `especialidad` e `imagen` si están presentes
$stmt = $conn->prepare("SELECT id_usuarios, Nombre_completo, Correo_electronico, COALESCE(especialidad, '') AS especialidad, COALESCE(imagen, '') AS imagen, COALESCE(telefono, '') AS telefono FROM usuarios WHERE Rol = 'Medico' ORDER BY Nombre_completo ASC");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $img = '';
            if (!empty($row['imagen'])) {
                // usar tal cual (puede ser URL absoluta o ruta relativa como uploads/perfiles/..)
                $img = $row['imagen'];
            }
            $medicos[(int)$row['id_usuarios']] = [
                'nombre' => $row['Nombre_completo'],
                'email' => $row['Correo_electronico'],
                'especialidad' => $row['especialidad'] ?: 'Médico de la Clínica',
                'telefono' => $row['telefono'] ?? '',
                'imagen' => $img
            ];
        }
    $stmt->close();
}

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
        .selection-edit-btn {
            position: absolute;
            top: 14px;
            right: 110px;
            background: linear-gradient(135deg,#198754 0%,#146c43 100%);
            color: #fff;
            border: none;
            padding: 6px 10px;
            border-radius: 6px;
            box-shadow: 0 6px 18px rgba(25,135,84,0.25);
            z-index: 50;
            cursor: pointer;
        }
        @media (max-width: 900px) {
            .selection-edit-btn { right: 16px; top: 8px; padding:6px 8px; }
        }
        .cardx {
            background: #fff;
            border: 1px solid #e9eef6;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(13,110,253,.06);
            cursor: pointer;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .cardx:hover { box-shadow: 0 8px 24px rgba(25,135,84,0.15); transform: translateY(-2px); }
        .cardx img { width: 100%; height: 161px; object-fit: cover; display: block; }
        .cardx .info { padding: 10px; }
        .cardx .name { font-weight: 700; color: #212529; }
        .cardx .meta { color: #6c757d; font-size: .85rem; }
        .header-avatar { position: relative; display:inline-block; }
        .header-edit-btn {
            position: absolute;
            bottom: -6px;
            right: -6px;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            color: #fff;
            border: 3px solid #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 18px rgba(25,135,84,0.35);
            cursor: pointer;
            z-index: 6;
        }
        .edit-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            z-index: 5;
            padding: 0.25rem 0.5rem;
            font-size: 0.78rem;
        }
        /* FAB + Drawer Médicos Disponibles */
        .smd-fab {
            position: fixed;
            bottom: 32px;
            right: 32px;
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: linear-gradient(135deg,#198754 0%,#146c43 100%);
            color: #fff;
            border: none;
            box-shadow: 0 6px 24px rgba(25,135,84,0.40);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            cursor: pointer;
            z-index: 1100;
            transition: transform 0.25s, box-shadow 0.25s;
        }
        .smd-fab:hover { transform: scale(1.10); box-shadow: 0 10px 30px rgba(25,135,84,0.50); }
        .smd-fab:focus { outline: none; }
        /* Overlay */
        .smd-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.35);
            z-index: 1150;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .smd-overlay.open { display: block; opacity: 1; }
        /* Drawer */
        .smd-drawer {
            position: fixed;
            top: 0;
            right: 0;
            width: 320px;
            max-width: 92vw;
            height: 100vh;
            background: #fff;
            box-shadow: -6px 0 32px rgba(31,38,135,0.18);
            z-index: 1200;
            display: flex;
            flex-direction: column;
            transform: translateX(110%);
            transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
            border-radius: 20px 0 0 20px;
        }
        .smd-drawer.open { transform: translateX(0); }
        .smd-drawer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 18px 14px;
            border-bottom: 1px solid rgba(25,135,84,0.12);
            flex-shrink: 0;
        }
        .smd-drawer-header h5 {
            color: #198754;
            font-weight: 700;
            font-size: 1.05rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .smd-drawer-close {
            background: rgba(25,135,84,0.08);
            border: none;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            color: #198754;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .smd-drawer-close:hover { background: rgba(25,135,84,0.18); }
        .smd-drawer-close:focus { outline: none; }
        .smd-drawer-body {
            flex: 1;
            overflow-y: auto;
            padding: 14px 12px;
        }
        .smd-drawer-body::-webkit-scrollbar { width: 5px; }
        .smd-drawer-body::-webkit-scrollbar-track { background: rgba(25,135,84,0.07); border-radius:10px; }
        .smd-drawer-body::-webkit-scrollbar-thumb { background: rgba(25,135,84,0.3); border-radius:10px; }
        .smd-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 10px;
            background: rgba(25,135,84,0.05);
            border: 1px solid rgba(25,135,84,0.15);
            border-radius: 14px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: box-shadow 0.2s, background 0.2s;
        }
        .smd-item:hover { background: rgba(25,135,84,0.10); box-shadow: 0 4px 12px rgba(25,135,84,0.12); }
        .smd-avatar { position: relative; flex-shrink: 0; width:52px; height:52px; }
        .smd-avatar img { width:52px; height:52px; border-radius:50%; object-fit:cover; }
        .smd-edit-btn {
            position: absolute;
            bottom: -5px;
            right: -5px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg,#198754 0%,#146c43 100%);
            color: #fff;
            border: 2px solid #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 3px 8px rgba(25,135,84,0.3);
            cursor: pointer;
            z-index: 6;
            font-size: 11px;
            padding: 0;
        }
        .smd-info { flex: 1; min-width: 0; }
        .smd-info .smd-name { font-weight: 600; color: #198754; font-size: 0.92rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .smd-info .smd-meta { font-size: 0.78rem; color: #888; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        /* Toggle de visibilidad en drawer */
        .smd-item { cursor: default !important; }
        .smd-item-clickable { cursor: pointer; display:flex; align-items:center; gap:12px; flex:1; min-width:0; }
        .smd-item-clickable:hover .smd-name { text-decoration: underline; }
        .smd-vis-switch {
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }
        .smd-vis-switch .form-check-input {
            width: 2.2em;
            height: 1.2em;
            cursor: pointer;
            border-color: #ccc;
            background-color: #e9ecef;
        }
        .smd-vis-switch .form-check-input:checked {
            background-color: #198754;
            border-color: #198754;
        }
        .smd-vis-switch small { font-size: 0.68rem; color: #aaa; }
        /* Card oculta */
        .medico-card-wrap.hidden-medico { display: none !important; }
        /* Subheader del drawer */
        .smd-drawer-subheader {
            padding: 8px 16px 10px;
            border-bottom: 1px solid rgba(25,135,84,0.10);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .smd-drawer-subheader small { color: #888; font-size: 0.8rem; }
        .smd-select-all-btn {
            background: none;
            border: 1px solid rgba(25,135,84,0.3);
            border-radius: 20px;
            color: #198754;
            font-size: 0.75rem;
            padding: 2px 10px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .smd-select-all-btn:hover { background: rgba(25,135,84,0.08); }

    </style>
    </head>
    <body>
        <div class="header-section text-center" style="position:relative;">
            <div class="medical-icon">
                <i class="bi bi-calendar-check"></i>
            </div>
            <h1>Seleccionar Médico para Gestión de Citas</h1>
            <p>Haga clic en el médico para ver su calendario de citas.</p>
        </div>
        <div class="container py-4">
            <?php
                $firstMedicoId = !empty($medicos) ? array_key_first($medicos) : 0;
            ?>
                    <?php if (empty($medicos)): ?>
                        <div class="alert alert-warning text-center mt-4" style="padding:40px;">
                            <h4><i class="bi bi-exclamation-triangle"></i> No hay médicos disponibles</h4>
                            <p>Por favor, contacte al administrador para registrar médicos en el sistema.</p>
                        </div>
                    <?php else: ?>
                        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;">
                        <?php foreach ($medicos as $id => $medico): ?>
                            <?php
                                $cardCover = $medico['imagen'] ? $medico['imagen'] : ('https://ui-avatars.com/api/?name=' . urlencode($medico['nombre']) . '&background=e8f5e9&color=198754&bold=true&size=300&font-size=0.4');
                            ?>
                            <div class="medico-card-wrap" data-medico-id="<?php echo (int)$id; ?>">
                                <div class="cardx" onclick="selectMedico(<?php echo (int)$id; ?>)">
                                    <img src="<?php echo htmlspecialchars($cardCover); ?>" alt="">
                                    <div class="info">
                                        <div class="name"><?php echo htmlspecialchars($medico['nombre']); ?></div>
                                        <div class="meta" style="color:#198754;font-weight:600;"><?php echo htmlspecialchars($medico['especialidad']); ?></div>
                                        <?php if (!empty($medico['telefono'])): ?>
                                        <div class="meta"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($medico['telefono']); ?></div>
                                        <?php endif; ?>
                                        <div class="meta"><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($medico['email']); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
        </div>

        <!-- FAB Médicos Disponibles -->
        <button class="smd-fab" id="smdFab" onclick="openDrawer()" title="Médicos Disponibles">
            <i class="bi bi-person-lines-fill"></i>
        </button>

        <!-- Overlay -->
        <div class="smd-overlay" id="smdOverlay" onclick="closeDrawer()"></div>

        <!-- Drawer -->
        <div class="smd-drawer" id="smdDrawer">
            <div class="smd-drawer-header">
                <h5><i class="bi bi-person-lines-fill"></i> Médicos Disponibles</h5>
                <button class="smd-drawer-close" onclick="closeDrawer()" title="Cerrar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="smd-drawer-subheader">
                <small id="smdVisCount"></small>
                <div style="display:flex;gap:6px;">
                    <button class="smd-select-all-btn" onclick="setAllVisibility(true)">Todos</button>
                    <button class="smd-select-all-btn" onclick="setAllVisibility(false)">Ninguno</button>
                </div>
            </div>
            <div class="smd-drawer-body">
                <?php foreach ($medicos as $id => $medico): ?>
                    <?php
                        $sAvatar = $medico['imagen'] ? $medico['imagen'] : ('https://ui-avatars.com/api/?name=' . urlencode($medico['nombre']) . '&background=ffffff&color=198754&bold=true&size=80');
                    ?>
                    <div class="smd-item">
                        <div class="smd-item-clickable" onclick="closeDrawer(); selectMedico(<?php echo (int)$id; ?>)">
                            <div class="smd-avatar">
                                <img src="<?php echo htmlspecialchars($sAvatar); ?>" alt="">
                                <button class="smd-edit-btn" onclick="event.stopPropagation(); window.location.href='Editar_medico.php?id=<?php echo (int)$id; ?>'" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </div>
                            <div class="smd-info">
                                <div class="smd-name"><?php echo htmlspecialchars($medico['nombre']); ?></div>
                                <div class="smd-meta"><?php echo htmlspecialchars($medico['email']); ?></div>
                                <div class="smd-meta"><?php echo htmlspecialchars($medico['especialidad']); ?></div>
                            </div>
                        </div>
                        <div class="smd-vis-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                id="visSwitch_<?php echo (int)$id; ?>"
                                data-mid="<?php echo (int)$id; ?>"
                                onchange="toggleVis(<?php echo (int)$id; ?>, this)"
                                checked>
                            <small>Visible</small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <script>
            var firstMedicoId = <?php echo json_encode($firstMedicoId ?? 0); ?>;
            function selectMedico(id) {
                window.location.href = '?medico_id=' + id;
            }
            var MEDICOS_IDS = <?php echo json_encode(array_keys($medicos)); ?>;
            var VIS_KEY = 'medicos_visible_v2';

            function getVisibility() {
                try { return JSON.parse(localStorage.getItem(VIS_KEY)) || {}; } catch(e) { return {}; }
            }
            function saveVisibility(obj) {
                localStorage.setItem(VIS_KEY, JSON.stringify(obj));
            }
            function applyVisibility() {
                var vis = getVisibility();
                var cards = document.querySelectorAll('.medico-card-wrap');
                var visible = 0;
                cards.forEach(function(card) {
                    var mid = parseInt(card.getAttribute('data-medico-id'));
                    // default visible unless explicitly set to false
                    var show = vis[mid] === false ? false : true;
                    card.classList.toggle('hidden-medico', !show);
                    if (show) visible++;
                    var sw = document.getElementById('visSwitch_' + mid);
                    if (sw) {
                        sw.checked = show;
                        sw.closest('.smd-vis-switch').querySelector('small').textContent = show ? 'Visible' : 'Oculto';
                    }
                });
                var countEl = document.getElementById('smdVisCount');
                if (countEl) countEl.textContent = visible + ' de ' + MEDICOS_IDS.length + ' visibles';
            }
            function toggleVis(id, checkbox) {
                var vis = getVisibility();
                vis[id] = checkbox.checked;
                saveVisibility(vis);
                applyVisibility();
            }
            function setAllVisibility(show) {
                var vis = getVisibility();
                MEDICOS_IDS.forEach(function(id){ vis[id] = show; });
                saveVisibility(vis);
                applyVisibility();
            }
            document.addEventListener('DOMContentLoaded', function() { applyVisibility(); });

            function openDrawer() {
                document.getElementById('smdDrawer').classList.add('open');
                var ov = document.getElementById('smdOverlay');
                ov.style.display = 'block';
                requestAnimationFrame(function(){ ov.classList.add('open'); });
            }
            function closeDrawer() {
                document.getElementById('smdDrawer').classList.remove('open');
                var ov = document.getElementById('smdOverlay');
                ov.classList.remove('open');
                setTimeout(function(){ ov.style.display = 'none'; }, 300);
            }
            document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeDrawer(); });
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
        * {
            backdrop-filter: blur(10px);
        }

        body { background-color: #f5f7fa; background-image: none; }
        
        /* Glassmorphism effect */
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px) saturate(200%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px) saturate(180%);
            -webkit-backdrop-filter: blur(10px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 16px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.12);
        }

        /* Floating button */
        .fab-edit {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            border: none;
            border-radius: 50%;
            color: white;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(25, 135, 84, 0.4);
            transition: all 0.3s ease;
            z-index: 100;
        }

        .fab-edit:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 30px rgba(25, 135, 84, 0.6);
        }

        .fab-edit:active {
            transform: scale(0.95);
        }

        /* Edit panel */
        .edit-panel {
            position: fixed;
            bottom: 100px;
            right: 30px;
            width: 350px;
            max-height: 500px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px) saturate(200%);
            -webkit-backdrop-filter: blur(20px) saturate(200%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.2);
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
            transition: all 0.3s ease;
            z-index: 99;
            overflow-y: auto;
        }

        .edit-panel.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .edit-panel h4 {
            color: #198754;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .edit-panel .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 0;
        }

        .edit-panel .close-btn:hover {
            color: #198754;
        }

        .edit-panel .medico-item {
            background: rgba(25, 135, 84, 0.05);
            border: 1px solid rgba(25, 135, 84, 0.2);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .edit-panel .medico-item input {
            flex: 1;
            border: 1px solid rgba(25, 135, 84, 0.3);
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 13px;
            background: rgba(255, 255, 255, 0.7);
        }

        .edit-panel .medico-item input:focus {
            outline: none;
            border-color: #198754;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 0 8px rgba(25, 135, 84, 0.2);
        }

        .edit-panel .btn-edit-save {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .edit-panel .btn-edit-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(25, 135, 84, 0.3);
        }

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

        /* Enhanced Medico Item Styles */
        .medico-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: rgba(25, 135, 84, 0.05);
            border: 1px solid rgba(25, 135, 84, 0.2);
            border-radius: 12px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .medico-item:hover {
            background: rgba(25, 135, 84, 0.08);
            border-color: rgba(25, 135, 84, 0.4);
            box-shadow: 0 4px 12px rgba(25, 135, 84, 0.1);
        }

        .medico-avatar {
            flex-shrink: 0;
            position: relative;
        }

        .medico-edit-btn {
            position: absolute;
            bottom: -8px;
            right: -8px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            color: #fff;
            border: 3px solid #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 18px rgba(25,135,84,0.3);
            cursor: pointer;
            z-index: 6;
        }

        .medico-info {
            flex: 1;
            min-width: 0;
        }

        .medico-info h6 {
            margin: 0 0 4px 0;
            font-weight: 600;
            color: #198754;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .medico-info p {
            margin: 0;
            font-size: 0.85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .medico-actions {
            flex-shrink: 0;
            display: flex;
            gap: 6px;
        }

        .medico-actions .btn {
            padding: 4px 8px;
            font-size: 0.85rem;
        }

        #medicosList {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
        }

        #medicosList::-webkit-scrollbar {
            width: 6px;
        }

        #medicosList::-webkit-scrollbar-track {
            background: rgba(25, 135, 84, 0.1);
            border-radius: 10px;
        }

        #medicosList::-webkit-scrollbar-thumb {
            background: rgba(25, 135, 84, 0.3);
            border-radius: 10px;
        }

        #medicosList::-webkit-scrollbar-thumb:hover {
            background: rgba(25, 135, 84, 0.5);
        }
    </style>
</head>
<body>
        <div class="container py-4">
            <div class="card shadow-lg mb-4" style="border-radius: 24px;">
                <div class="card-header d-flex justify-content-between align-items-center bg-white position-relative" style="border-radius: 18px 18px 0 0;">
                    <div class="d-flex align-items-center gap-3">
                        <?php
                        $headerImg = '';
                        if (!empty($medicos[$medico_id]['imagen'])) {
                            $headerImg = $medicos[$medico_id]['imagen'];
                        } else {
                            $headerImg = 'https://ui-avatars.com/api/?name=' . urlencode($medicos[$medico_id]['nombre'] ?? 'Medico') . '&background=198754&color=ffffff&bold=true&size=60';
                        }
                        ?>
                        <div class="header-avatar">
                            <img src="<?php echo htmlspecialchars($headerImg); ?>" 
                                 alt="<?php echo htmlspecialchars($medicos[$medico_id]['nombre'] ?? ''); ?>" 
                                 class="rounded-circle" 
                                 style="width: 60px; height: 60px; border: 2px solid #198754;">
                            <button class="header-edit-btn" onclick="window.location.href='Editar_medico.php?id=<?php echo (int)$medico_id; ?>'" title="Editar médico">
                                <i class="bi bi-pencil" style="font-size:16px"></i>
                            </button>
                        </div>
                        <div>
                            <h3 class="mb-0" style="font-weight:700; color:#198754; font-size:1.8rem;"> <?php echo htmlspecialchars($medicos[$medico_id]['nombre'] ?? 'Médico'); ?> </h3>
                            <span class="badge" style="font-size:1.1rem; background-color:#198754; color:#ffffff;"> <?php echo htmlspecialchars($medicos[$medico_id]['especialidad'] ?? 'Médico de la Clínica'); ?> </span>
                        </div>
                    </div>
                    <button class="btn" style="position: absolute; top: 18px; right: 18px; background-color:#198754; color:#ffffff; border-color:#198754;" onclick="backToSelection()">
                        <i class="bi bi-arrow-left"></i> Atrás
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
                        <!-- "Editar visibilidad" moved to the edit panel for better visibility -->
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

        <!-- Floating Edit Button -->
        <?php if ($medico_id !== 0): ?>
            <button class="fab-edit" onclick="toggleEditPanel()" title="Editar médicos">
                <i class="bi bi-pencil-square"></i>
            </button>
        <?php endif; ?>

        <!-- Edit Panel -->
        <div class="edit-panel" id="editPanel">
            <button class="close-btn" onclick="toggleEditPanel()">
                <i class="bi bi-x-lg"></i>
            </button>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                <h4 style="margin:0;display:flex;align-items:center;gap:10px;">
                    <i class="bi bi-person-gear"></i>
                    Médicos Disponibles
                </h4>
                </div>
            <div id="medicosList"></div>
        </div>

    <script>
        // PHP data to JS
        var citas = <?php echo json_encode($citas); ?>;
        var disp = <?php echo json_encode($disp); ?>;
        var medico_id = <?php echo (int)$medico_id; ?>;
        // Medicos data (from server)
        var medicosData = <?php echo json_encode($medicos); ?>;

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

        // Glassmorphism Edit Panel Functions + Edit Mode
        let __editMode = false;

        function toggleEditPanel() {
            const panel = document.getElementById('editPanel');
            if (panel) {
                panel.classList.toggle('active');
                if (panel.classList.contains('active')) {
                    populateMedicosList();
                }
            }
        }

        function openEditMode() {
            const panel = document.getElementById('editPanel');
            if (panel && !panel.classList.contains('active')) {
                toggleEditPanel();
            }
            if (!__editMode) {
                toggleEditMode();
            }
            // focus first checkbox if exists
            setTimeout(function(){
                const first = document.querySelector('#medicosList input[data-visible-checkbox]');
                if (first) first.focus();
            }, 120);
        }

        function toggleEditMode() {
            __editMode = !__editMode;
            // If switching off, save selections (function kept for backwards compatibility)
            if (!__editMode) {
                saveVisibleMedicos();
            }
            // Re-render list
            populateMedicosList();
        }

        function loadVisibleMedicos() {
            try {
                const raw = localStorage.getItem('medicos_visible');
                if (!raw) return null; // null means no preference (show all)
                const arr = JSON.parse(raw);
                if (!Array.isArray(arr)) return null;
                return arr.map(function(v){ return String(v); });
            } catch (e) {
                return null;
            }
        }

        function saveVisibleMedicos() {
            const list = document.getElementById('medicosList');
            if (!list) return;
            const checks = list.querySelectorAll('input[data-visible-checkbox]');
            const sel = [];
            checks.forEach(function(c){ if (c.checked) sel.push(String(c.dataset.id)); });
            try { localStorage.setItem('medicos_visible', JSON.stringify(sel)); showToast('Preferencias guardadas'); } catch (e) { console.error(e); }
            applyVisibleFilter();
        }

        function applyVisibleFilter() {
            const visible = loadVisibleMedicos();
            // If null => show all
            const cards = document.querySelectorAll('.card-medico');
            if (!cards) return;
            cards.forEach(function(card){
                const id = String(card.dataset.medicoId || card.getAttribute('data-medico-id') || '');
                if (!id) return;
                if (visible === null) {
                    card.style.display = '';
                } else {
                    card.style.display = visible.indexOf(id) !== -1 ? '' : 'none';
                }
            });
        }

        function populateMedicosList() {
            const medicos = medicosData || {};
            const list = document.getElementById('medicosList');
            if (!list) return;
            
            const visible = loadVisibleMedicos();
            list.innerHTML = '';
            
            // Header: edit mode button
            const header = document.createElement('div');
            header.style.display = 'flex';
            header.style.justifyContent = 'space-between';
            header.style.alignItems = 'center';
            header.style.marginBottom = '8px';
            const title = document.createElement('div');
            title.innerHTML = '<strong>Médicos Disponibles</strong>';
            header.appendChild(title);
            list.appendChild(header);

            // Items
            for (const [id, medico] of Object.entries(medicos)) {
                const item = document.createElement('div');
                item.className = 'medico-item glass-card';
                const isVisible = visible === null ? true : (visible.indexOf(String(id)) !== -1);
                // merge overrides
                const overrides = loadMedicosOverrides() || {};
                const md = Object.assign({}, medico, overrides[id] || {});
                item.innerHTML = `
                    <div class="medico-avatar" style="position:relative;">
                        <img src="${md.imagen ? md.imagen : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(md.nombre) + '&background=ffffff&color=198754&bold=true&size=60'}" 
                             alt="${md.nombre}"
                             style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">
                        <button class="medico-edit-btn" onclick="event.stopPropagation(); editMedico(${id}, '${md.nombre.replace(/'/g, "\\'")}', '${md.email.replace(/'/g, "\\'")}')" title="Editar médico">
                            <i class="bi bi-pencil" style="font-size:14px"></i>
                        </button>
                    </div>
                    <div class="medico-info">
                        <h6>${md.nombre}</h6>
                        <p class="text-muted">${md.email} ${md.especialidad?('- '+md.especialidad):''}</p>
                    </div>
                    <div class="medico-actions">
                    </div>
                `;
                list.appendChild(item);
            }
            // Save hint when in edit mode
            if (__editMode) {
                const footer = document.createElement('div');
                footer.style.marginTop = '8px';
                footer.innerHTML = '<small class="text-muted">Marque los médicos que desea ver y presione "Guardar cambios".</small>';
                list.appendChild(footer);
            }
        }

        function editMedico(id, nombre, email) {
            openDoctorEditor(id);
        }

        // Apply filter on load
        document.addEventListener('DOMContentLoaded', function(){ applyVisibleFilter(); });
    </script>

    <script src="assets/js/script.js"></script>
</body>
</html>

<!-- Doctor Edit Modal (client-side, persists to localStorage) -->
<div id="doctorEditModal" class="modal" style="display:none;">
    <div class="modal-content p-3" style="max-width:540px;">
        <span class="close" onclick="closeDoctorEditor()">&times;</span>
        <h2 style="color:#198754; font-weight:700;">Editar Médico</h2>
        <form id="doctor-edit-form" style="margin-top:12px;">
            <input type="hidden" id="edit-medico-id">
            <div class="mb-2">
                <label class="form-label">Nombre</label>
                <input id="edit-nombre" class="form-control" required />
            </div>
            <div class="mb-2">
                <label class="form-label">Especialidad</label>
                <input id="edit-especialidad" class="form-control" />
            </div>
            <div class="mb-2">
                <label class="form-label">Email</label>
                <input id="edit-email" class="form-control" />
            </div>
            <div class="mb-2">
                <label class="form-label">URL Imagen</label>
                <input id="edit-imagen" class="form-control" placeholder="https://..." />
            </div>
            <div class="d-flex gap-2 mt-3">
                <button type="button" class="btn btn-success" onclick="saveDoctorEdit()">Guardar</button>
                <button type="button" class="btn btn-secondary" onclick="closeDoctorEditor()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
    // doctor overrides persistence
    function loadMedicosOverrides() {
        try { return JSON.parse(localStorage.getItem('medicos_overrides') || '{}'); } catch(e){ return {}; }
    }
    function saveMedicosOverrides(obj) {
        try { localStorage.setItem('medicos_overrides', JSON.stringify(obj || {})); } catch(e) { console.error(e); }
    }

    function openDoctorEditor(id) {
        const overrides = loadMedicosOverrides();
        const base = medicosData && medicosData[id] ? medicosData[id] : {nombre:'', email:'', especialidad:'', imagen:''};
        const over = overrides[id] || {};
        const md = Object.assign({}, base, over);
        document.getElementById('edit-medico-id').value = id;
        document.getElementById('edit-nombre').value = md.nombre || '';
        document.getElementById('edit-email').value = md.email || '';
        document.getElementById('edit-especialidad').value = md.especialidad || '';
        document.getElementById('edit-imagen').value = md.imagen || '';
        document.getElementById('doctorEditModal').style.display = 'block';
    }

    function closeDoctorEditor() {
        document.getElementById('doctorEditModal').style.display = 'none';
    }

    function saveDoctorEdit() {
        const id = String(document.getElementById('edit-medico-id').value || '');
        if (!id) return;
        const nombre = document.getElementById('edit-nombre').value || '';
        const email = document.getElementById('edit-email').value || '';
        const especialidad = document.getElementById('edit-especialidad').value || '';
        const imagen = document.getElementById('edit-imagen').value || '';
        const overrides = loadMedicosOverrides();
        overrides[id] = { nombre: nombre, email: email, especialidad: especialidad, imagen: imagen };
        saveMedicosOverrides(overrides);
        // re-render panel and cards
        populateMedicosList();
        applyVisibleFilter();
        // update selection grid cards (if present)
        const cards = document.querySelectorAll('.cardx');
        cards.forEach(function(card){
            const onclick = card.getAttribute('onclick') || '';
            // attempt to extract id from onclick or data-medico-id
        });
        closeDoctorEditor();
        showToast('Cambios guardados localmente');
    }
</script>
