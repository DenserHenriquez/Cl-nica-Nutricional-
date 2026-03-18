<?php
session_start();
require_once __DIR__ . '/db_connection.php';

if (!isset($_SESSION['id_usuarios'])) {
    header('Location: Login.php');
    exit;
}

// Helpers
function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function post($k, $d=null){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function getv($k, $d=null){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }

$userId = (int)($_SESSION['id_usuarios'] ?? 0);
$userRole = $_SESSION['rol'] ?? 'Paciente';
$isPrivileged = in_array($userRole, ['Medico','Administrador'], true);

if (!$isPrivileged) {
    // Sólo médicos y administradores pueden registrar consultas
    http_response_code(403);
    echo '<div style="padding:20px;font-family:sans-serif;">'
       . '<h3>Acceso restringido</h3>'
       . '<p>Solo Médicos o Administradores pueden acceder a esta sección.</p>'
       . '</div>';
    exit;
}

if (!isset($conexion) || !($conexion instanceof mysqli)) {
    die('Error de conexión a la base de datos.');
}

// Crear la tabla de consultas si no existe
$conexion->query("CREATE TABLE IF NOT EXISTS consultas_medicas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medico_id INT NOT NULL,
    paciente_id INT NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    peso DECIMAL(10,2) NULL,
    estatura DECIMAL(10,2) NULL,
    edad_metabolica DECIMAL(10,2) NULL,
    imc DECIMAL(10,2) NULL,
    masa_muscular DECIMAL(10,2) NULL,
    motivo TEXT NULL,
    notas TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_paciente (paciente_id),
    INDEX idx_medico (medico_id),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
// Migration: ensure column 'edad_metabolica' exists; if only 'talla' exists, rename it
$__chkEdad = $conexion->query("SHOW COLUMNS FROM consultas_medicas LIKE 'edad_metabolica'");
if ($__chkEdad && $__chkEdad->num_rows === 0) {
    $__chkT = $conexion->query("SHOW COLUMNS FROM consultas_medicas LIKE 'talla'");
    if ($__chkT && $__chkT->num_rows > 0) {
        @$conexion->query("ALTER TABLE consultas_medicas CHANGE talla edad_metabolica DECIMAL(10,2) NULL");
    } else {
        @$conexion->query("ALTER TABLE consultas_medicas ADD COLUMN edad_metabolica DECIMAL(10,2) NULL AFTER estatura");
    }
}

// Endpoint AJAX: sugerencias de pacientes para autocompletar
if (isset($_GET['ajax']) && $_GET['ajax'] === 'pacientes') {
    header('Content-Type: application/json; charset=utf-8');
    $q = getv('q', '');
    $out = [];
    if ($q !== '' && mb_strlen($q) >= 2) {
        $like = '%' . $q . '%';
        $sql = "SELECT p.id_pacientes, p.nombre_completo, p.DNI, p.telefono
                FROM pacientes p
                WHERE p.nombre_completo LIKE ? OR p.DNI LIKE ?
                ORDER BY p.nombre_completo ASC
                LIMIT 10";
        if ($st = $conexion->prepare($sql)) {
            $st->bind_param('ss', $like, $like);
            $st->execute();
            $rs = $st->get_result();
            $rows = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
            $st->close();
            foreach ($rows as $r) {
                $out[] = [
                    'id' => (int)$r['id_pacientes'],
                    'nombre' => (string)$r['nombre_completo'],
                    'dni' => (string)($r['DNI'] ?? ''),
                    'telefono' => (string)($r['telefono'] ?? ''),
                ];
            }
        }
    }
    echo json_encode($out);
    exit;
}

// Búsqueda de pacientes (por nombre o DNI). La relación pacientes.id_usuarios = usuarios.id_usuarios
$search = getv('q', '');
$paciente_id = (int)getv('paciente_id', 0);
$paciente_nombre = getv('paciente_nombre', '');
$errores = [];
$exito = '';
$pacienteSel = null;

if ($paciente_id > 0) {
    $sql = "SELECT p.id_pacientes, p.nombre_completo, p.DNI, p.telefono, p.fecha_nacimiento,
                   u.id_usuarios
            FROM pacientes p
            INNER JOIN usuarios u ON u.id_usuarios = p.id_usuarios
            WHERE p.id_pacientes = ?
            LIMIT 1";
    if ($st = $conexion->prepare($sql)) {
        $st->bind_param('i', $paciente_id);
        $st->execute();
        $rs = $st->get_result();
        $pacienteSel = $rs ? $rs->fetch_assoc() : null;
        $st->close();
    }
} elseif ($paciente_nombre !== '') {
    $sql = "SELECT p.id_pacientes, p.nombre_completo, p.DNI, p.telefono, p.fecha_nacimiento,
                   u.id_usuarios
            FROM pacientes p
            INNER JOIN usuarios u ON u.id_usuarios = p.id_usuarios
            WHERE p.nombre_completo = ?
            ORDER BY p.id_pacientes ASC
            LIMIT 1";
    if ($st = $conexion->prepare($sql)) {
        $st->bind_param('s', $paciente_nombre);
        $st->execute();
        $rs = $st->get_result();
        $pacienteSel = $rs ? $rs->fetch_assoc() : null;
        $st->close();
    }
}

$results = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $sql = "SELECT p.id_pacientes, p.nombre_completo, p.DNI, p.telefono
            FROM pacientes p
            WHERE p.nombre_completo LIKE ? OR p.DNI LIKE ?
            ORDER BY p.nombre_completo ASC
            LIMIT 20";
    if ($st = $conexion->prepare($sql)) {
        $st->bind_param('ss', $like, $like);
        $st->execute();
        $rs = $st->get_result();
        $results = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
        $st->close();
    }
}

// Guardar consulta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accion') === 'guardar_consulta') {
    $paciente_id = (int)post('paciente_id');
    $peso = post('peso');
    $estatura = post('estatura');
    $edad_metabolica = post('edad_metabolica');
    $masa_muscular = post('masa_muscular');
    $motivo = post('motivo');
    $notas = post('notas');

    // Validaciones básicas
    if ($paciente_id <= 0) { $errores[] = 'Seleccione un paciente.'; }
    if ($peso !== '' && !is_numeric($peso)) $errores[] = 'El peso debe ser numérico.';
    if ($estatura !== '' && !is_numeric($estatura)) $errores[] = 'La estatura debe ser numérica (cm).';
    if ($edad_metabolica !== '' && !is_numeric($edad_metabolica)) $errores[] = 'La edad metabólica debe ser numérica.';
    if ($masa_muscular !== '' && !is_numeric($masa_muscular)) $errores[] = 'La masa muscular debe ser numérica (kg).';

    // Calcular IMC si procede
    $imc = null;
    if ($peso !== '' && $estatura !== '' && is_numeric($peso) && is_numeric($estatura) && floatval($estatura) > 0) {
        $imc = round(floatval($peso) / pow(floatval($estatura)/100, 2), 2);
    }

    if (!$errores) {
        $sqlI = "INSERT INTO consultas_medicas (medico_id, paciente_id, peso, estatura, edad_metabolica, imc, masa_muscular, motivo, notas)
                 VALUES (?,?,?,?,?,?,?,?,?)";
        if ($st = $conexion->prepare($sqlI)) {
            $pPeso = ($peso === '' ? null : $peso);
            $pEst = ($estatura === '' ? null : $estatura);
            $pEdad = ($edad_metabolica === '' ? null : $edad_metabolica);
            $pIMC = ($imc === null ? null : $imc);
            $pMM  = ($masa_muscular === '' ? null : $masa_muscular);
            $st->bind_param('iidddddss', $userId, $paciente_id, $pPeso, $pEst, $pEdad, $pIMC, $pMM, $motivo, $notas);
            if ($st->execute()) {
                $exito = 'Consulta guardada correctamente.';
                // Cargar paciente seleccionado para volver a mostrar formulario limpio
                header('Location: Consulta_Medica.php?paciente_id='.(int)$paciente_id.'&ok=1');
                exit;
            } else {
                $errores[] = 'No se pudo guardar la consulta.';
            }
            $st->close();
        } else {
            $errores[] = 'Error al preparar la inserción.';
        }
    }
}

// Consultas recientes del paciente seleccionado
$consultas = [];
$ultimaConsulta = null;
$fecha_desde = getv('fd', '');
$fecha_hasta = getv('fh', '');
if ($pacienteSel) {
    // Construir filtros de fecha si están presentes
    $where = ' paciente_id = ? ';
    $types = 'i';
    $params = [ (int)$pacienteSel['id_pacientes'] ];
    if ($fecha_desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {
        $where .= ' AND fecha >= ? ';
        $types .= 's';
        $params[] = $fecha_desde . ' 00:00:00';
    }
    if ($fecha_hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
        $where .= ' AND fecha <= ? ';
        $types .= 's';
        $params[] = $fecha_hasta . ' 23:59:59';
    }
    $sqlList = "SELECT id, fecha, peso, estatura, edad_metabolica, imc, masa_muscular, motivo, notas
                FROM consultas_medicas
                WHERE $where
                ORDER BY fecha DESC
                LIMIT 100";
    $stc = $conexion->prepare($sqlList);
    if ($stc) {
        $stc->bind_param($types, ...$params);
        $stc->execute();
        $rc = $stc->get_result();
        $consultas = $rc ? $rc->fetch_all(MYSQLI_ASSOC) : [];
        $stc->close();
        if (!empty($consultas)) { $ultimaConsulta = $consultas[0]; }
    }
}

$ok = (int)getv('ok', 0) === 1;
if ($ok && !$exito) { $exito = 'Consulta guardada correctamente.'; }

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Consulta Médica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
        .btn-primary { background-color: #198754; border-color: #198754; }
        .btn-primary:hover { background-color: #146c43; border-color: #13653f; }
        .bg-primary { background-color: #198754 !important; }
        .form-label { font-weight: 600; color: #198754; }
        .header-section { background: linear-gradient(135deg, #198754 0%, #146c43 100%); color: white; padding: 0.8rem 0; margin-bottom: 1rem; }
        .header-section h1 { font-size: 2.2rem; font-weight: 700; margin: 0.15rem 0 0.25rem; }
        .header-section p { font-size: 1.05rem; opacity: 0.95; margin: 0; }
        .medical-icon { font-size: 1.9rem; margin-bottom: 0.35rem; color: #ffffff; }
        .hist-card td, .hist-card th { font-size: .95rem; }
        /* Typeahead styles */
        .typeahead-wrapper { position: relative; }
        .typeahead-list {
            position: absolute;
            top: 100%; left: 0; right: 0;
            background: #fff;
            border: 1px solid #dee2e6;
            border-top: none;
            z-index: 1051;
            max-height: 280px;
            overflow-y: auto;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.08);
        }
        .typeahead-item { padding: 8px 10px; cursor: pointer; display:flex; justify-content:space-between; align-items:center; }
        .typeahead-item:hover, .typeahead-item.active { background: #e9f7ef; }
        .typeahead-name { font-weight: 600; color: #198754; }
        .typeahead-meta { font-size: 0.86rem; color: #6c757d; }
        @media (max-width:576px) {
            .header-section h1 { font-size:1.3rem; }
            .header-section p { font-size:.82rem; }
            .typeahead-list { max-height:200px; }
        }
    </style>
</head>
<body>
    <div class="header-section">
        <div class="container text-center">
            <div class="medical-icon"><i class="bi bi-journal-medical"></i></div>
            <h1>Consulta Médica</h1>
            <p>Busque un paciente y registre los datos de la consulta.</p>
        </div>
    </div>

    <div class="container mb-5">
        <?php if (!empty($errores)): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errores as $e): ?>
                        <li><?= h($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= h($exito) ?>
            </div>
        <?php endif; ?>

        <!-- Búsqueda de Paciente -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-search me-2"></i>Buscar Paciente</h5>
            </div>
            <div class="card-body">
                <form method="get" class="row g-2 align-items-center">
                    <div class="col-md-8">
                        <div class="typeahead-wrapper position-relative">
                            <input type="text" class="form-control" id="buscar_paciente" name="q" value="<?= h($search) ?>" placeholder="Ingrese nombre o DNI del paciente" autocomplete="off">
                            <div id="typeahead-list" class="typeahead-list" style="display:none;"></div>
                        </div>
                    </div>
                    <div class="col-md-4 d-grid d-md-flex justify-content-md-end gap-2">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Buscar</button>
                        <?php if ($search !== ''): ?>
                            <a class="btn btn-secondary" href="Consulta_Medica.php">Limpiar</a>
                        <?php endif; ?>
                    </div>
                </form>
                <?php if ($search !== ''): ?>
                    <div class="table-responsive mt-3">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>DNI</th>
                                    <th>Teléfono</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($results)): ?>
                                    <tr><td colspan="4" class="text-center">Sin resultados</td></tr>
                                <?php else: ?>
                                    <?php foreach ($results as $r): ?>
                                        <tr>
                                            <td><?= h($r['nombre_completo']) ?></td>
                                            <td><?= h($r['DNI']) ?></td>
                                            <td><?= h($r['telefono']) ?></td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-success" href="Consulta_Medica.php?paciente_id=<?= (int)$r['id_pacientes'] ?>">
                                                    Seleccionar
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($pacienteSel): ?>
            <!-- Ficha del paciente seleccionado -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="bi bi-person-badge me-2"></i>Paciente Seleccionado</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><strong>Nombre:</strong> <?= h($pacienteSel['nombre_completo'] ?? '') ?></div>
                        <div class="col-md-4"><strong>DNI:</strong> <?= h($pacienteSel['DNI'] ?? '') ?></div>
                        <div class="col-md-4"><strong>Teléfono:</strong> <?= h($pacienteSel['telefono'] ?? '') ?></div>
                    </div>
                </div>
            </div>

            <!-- Formulario de consulta -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-clipboard2-pulse me-2"></i>Nueva Consulta</h5>
                    <?php if ($ultimaConsulta): ?>
                        <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalConsultaAnterior">
                            <i class="bi bi-eye"></i> Ver consulta anterior
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="accion" value="guardar_consulta">
                        <input type="hidden" name="paciente_id" value="<?= (int)$pacienteSel['id_pacientes'] ?>">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label" for="peso">Peso (kg)</label>
                                <input type="number" step="0.01" class="form-control" id="peso" name="peso" oninput="calcularIMC()">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="estatura">Estatura (cm)</label>
                                <input type="number" step="0.01" class="form-control" id="estatura" name="estatura" value="<?= isset($ultimaConsulta['estatura']) ? h((string)$ultimaConsulta['estatura']) : '' ?>" oninput="calcularIMC()" readonly data-bs-toggle="tooltip" data-bs-placement="top" title="La estatura es fija y se gestiona desde Expediente/Actualizar Perfil.">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="edad_metabolica">Edad metabólica</label>
                                <input type="number" step="0.01" class="form-control" id="edad_metabolica" name="edad_metabolica">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="imc">IMC</label>
                                <input type="text" class="form-control" id="imc" name="imc">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label" for="masa_muscular">Masa Muscular (kg)</label>
                                <input type="number" step="0.01" class="form-control" id="masa_muscular" name="masa_muscular">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="motivo">Motivo de la consulta</label>
                                <input type="text" class="form-control" id="motivo" name="motivo" maxlength="255">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="notas">Notas adicionales</label>
                            <textarea class="form-control" id="notas" name="notas" rows="3" placeholder="Observaciones, recomendaciones, etc."></textarea>
                        </div>
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save me-2"></i>Guardar Consulta</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Historial de consultas -->
            <div class="card hist-card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-clock-history me-2"></i>Historial Médico</h5>
                    <form method="get" class="d-flex align-items-center gap-2" style="margin:0;">
                        <input type="hidden" name="paciente_id" value="<?= (int)$pacienteSel['id_pacientes'] ?>">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light">Desde</span>
                            <input type="date" class="form-control" name="fd" value="<?= h($fecha_desde) ?>">
                        </div>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light">Hasta</span>
                            <input type="date" class="form-control" name="fh" value="<?= h($fecha_hasta) ?>">
                        </div>
                        <button class="btn btn-light btn-sm" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
                        <a class="btn btn-outline-light btn-sm" href="Consulta_Medica.php?paciente_id=<?= (int)$pacienteSel['id_pacientes'] ?>"><i class="bi bi-x-circle"></i> Limpiar</a>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Peso</th>
                                    <th>Estatura</th>
                                    <th>Edad metabólica</th>
                                    <th>IMC</th>
                                    <th>Masa muscular</th>
                                    <th>Motivo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($consultas)): ?>
                                    <tr><td colspan="7" class="text-center">Sin registros</td></tr>
                                <?php else: ?>
                                    <?php foreach ($consultas as $c): ?>
                                        <tr>
                                            <td><?= h(date('d/m/Y H:i', strtotime($c['fecha']))) ?></td>
                                            <td><?= h($c['peso'] !== null ? number_format((float)$c['peso'], 2) : '') ?></td>
                                            <td><?= h($c['estatura'] !== null ? number_format((float)$c['estatura'], 2) : '') ?></td>
                                            <td><?= h($c['edad_metabolica'] !== null ? number_format((float)$c['edad_metabolica'], 2) : '') ?></td>
                                            <td><?= h($c['imc'] !== null ? number_format((float)$c['imc'], 2) : '') ?></td>
                                            <td><?= h($c['masa_muscular'] !== null ? number_format((float)$c['masa_muscular'], 2) : '') ?></td>
                                            <td><?= h($c['motivo'] ?? '') ?></td>
                                            <td class="text-center" style="width:1%; white-space:nowrap;">
                                                <button type="button" class="btn btn-sm btn-outline-primary ver-consulta" 
                                                    data-bs-toggle="modal" data-bs-target="#modalVerConsulta"
                                                    data-fecha="<?= h(date('d/m/Y H:i', strtotime($c['fecha']))) ?>"
                                                    data-peso="<?= h($c['peso'] !== null ? number_format((float)$c['peso'], 2) : '') ?>"
                                                    data-estatura="<?= h($c['estatura'] !== null ? number_format((float)$c['estatura'], 2) : '') ?>"
                                                    data-edad="<?= h($c['edad_metabolica'] !== null ? number_format((float)$c['edad_metabolica'], 2) : '') ?>"
                                                    data-imc="<?= h($c['imc'] !== null ? number_format((float)$c['imc'], 2) : '') ?>"
                                                    data-mm="<?= h($c['masa_muscular'] !== null ? number_format((float)$c['masa_muscular'], 2) : '') ?>"
                                                    data-motivo="<?= h($c['motivo'] ?? '') ?>"
                                                    data-notas="<?= h($c['notas'] ?? '') ?>"
                                                    title="Ver detalles">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Ver Consulta (reusable) -->
    <div class="modal fade" id="modalVerConsulta" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header" style="background:#198754;color:#fff;">
            <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Detalle de consulta</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-3"><strong>Fecha:</strong> <span id="vc-fecha"></span></div>
              <div class="col-md-3"><strong>Peso:</strong> <span id="vc-peso"></span> kg</div>
              <div class="col-md-3"><strong>Estatura:</strong> <span id="vc-estatura"></span> cm</div>
              <div class="col-md-3"><strong>Edad metabólica:</strong> <span id="vc-edad"></span></div>
              <div class="col-md-3"><strong>IMC:</strong> <span id="vc-imc"></span></div>
              <div class="col-md-3"><strong>Masa muscular:</strong> <span id="vc-mm"></span> kg</div>
              <div class="col-md-6"><strong>Motivo:</strong> <span id="vc-motivo"></span></div>
              <div class="col-12">
                <strong>Observaciones/Receta:</strong>
                <div id="vc-notas" class="mt-1" style="white-space:pre-wrap;border:1px solid #e9ecef;border-radius:8px;padding:10px;background:#fafafa;"></div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <?php if ($ultimaConsulta): ?>
    <!-- Modal Consulta Anterior -->
    <div class="modal fade" id="modalConsultaAnterior" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header" style="background:#198754;color:#fff;">
            <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Consulta anterior (<?= h(date('d/m/Y H:i', strtotime($ultimaConsulta['fecha']))) ?>)</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-3"><strong>Peso:</strong> <?= h($ultimaConsulta['peso'] !== null ? number_format((float)$ultimaConsulta['peso'], 2) : ''); ?> kg</div>
              <div class="col-md-3"><strong>Estatura:</strong> <?= h($ultimaConsulta['estatura'] !== null ? number_format((float)$ultimaConsulta['estatura'], 2) : ''); ?> cm</div>
              <div class="col-md-3"><strong>Edad metabólica:</strong> <?= h($ultimaConsulta['edad_metabolica'] !== null ? number_format((float)$ultimaConsulta['edad_metabolica'], 2) : ''); ?></div>
              <div class="col-md-3"><strong>IMC:</strong> <?= h($ultimaConsulta['imc'] !== null ? number_format((float)$ultimaConsulta['imc'], 2) : ''); ?></div>
              <div class="col-md-3"><strong>Masa muscular:</strong> <?= h($ultimaConsulta['masa_muscular'] !== null ? number_format((float)$ultimaConsulta['masa_muscular'], 2) : ''); ?> kg</div>
              <div class="col-md-9"><strong>Motivo:</strong> <?= h($ultimaConsulta['motivo'] ?? ''); ?></div>
              <div class="col-12">
                <strong>Observaciones/Receta:</strong>
                <div class="mt-1" style="white-space:pre-wrap;border:1px solid #e9ecef;border-radius:8px;padding:10px;background:#fafafa;">
                  <?= nl2br(h($ultimaConsulta['notas'] ?? '')); ?>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <script>
        function calcularIMC() {
            var peso = parseFloat(document.getElementById('peso').value);
            var estatura = parseFloat(document.getElementById('estatura').value); // cm (estática por default)
            var imcEl = document.getElementById('imc');
            if (!isNaN(peso) && !isNaN(estatura) && estatura > 0) {
                var imc = peso / Math.pow(estatura/100, 2);
                // Solo sugerimos el cálculo si el usuario no lo ha modificado manualmente
                if (!imcEl.dataset.edited || imcEl.value.trim() === '') {
                    imcEl.value = imc.toFixed(2);
                }
            }
        }
        // Detectar edición manual del IMC para no sobreescribirlo
        (function(){
          var imcEl = document.getElementById('imc');
          if (imcEl) {
            imcEl.addEventListener('input', function(){ this.dataset.edited = '1'; });
          }
          // Si existe estatura previa y el IMC está vacío, precalcular al cargar
          var e = document.getElementById('estatura');
          var p = document.getElementById('peso');
          if (e && p && imcEl && imcEl.value.trim() === '') {
            calcularIMC();
          }
        })();
    </script>
    <script>
        (function(){
            var input = document.getElementById('buscar_paciente');
            if (!input) return;
            var list = document.getElementById('typeahead-list');
            var activeIndex = -1;
            var itemsData = [];
            var abortCtrl = null;
            function debounce(fn, ms){ var t; return function(){ var a=arguments; clearTimeout(t); t=setTimeout(function(){ fn.apply(null,a); }, ms); }; }
            function hideList(){ if (list){ list.style.display='none'; list.innerHTML=''; activeIndex=-1; itemsData=[]; } }
            function showList(){ if (list && list.innerHTML.trim()!==''){ list.style.display='block'; } }
            function render(items){
                itemsData = items || [];
                if (!itemsData.length){ hideList(); return; }
                var html = '';
                for (var i=0;i<itemsData.length;i++){
                    var it = itemsData[i];
                    var meta = [];
                    if (it.dni) meta.push('DNI: '+it.dni);
                    if (it.telefono) meta.push('Tel: '+it.telefono);
                    html += '<div class="typeahead-item" data-index="'+i+'">'
                         +   '<div class="typeahead-name">'+escapeHtml(it.nombre)+'</div>'
                         +   '<div class="typeahead-meta">'+escapeHtml(meta.join(' · '))+'</div>'
                         + '</div>';
                }
                list.innerHTML = html;
                // attach events
                Array.prototype.forEach.call(list.querySelectorAll('.typeahead-item'), function(el){
                    el.addEventListener('mouseenter', function(){ setActive(parseInt(this.getAttribute('data-index'))); });
                    el.addEventListener('mousedown', function(e){ e.preventDefault(); selectIndex(parseInt(this.getAttribute('data-index'))); });
                });
                showList();
            }
            function setActive(idx){
                var nodes = list.querySelectorAll('.typeahead-item');
                Array.prototype.forEach.call(nodes, function(n){ n.classList.remove('active'); });
                if (idx>=0 && idx<nodes.length){ nodes[idx].classList.add('active'); activeIndex = idx; }
            }
            function selectIndex(idx){
                if (idx<0 || idx>=itemsData.length) return;
                var it = itemsData[idx];
                window.location.href = 'Consulta_Medica.php?paciente_id=' + encodeURIComponent(String(it.id));
            }
            function escapeHtml(s){ return (s||'').replace(/[&<>"']/g,function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]);}); }
            var doFetch = debounce(function(q){
                if (q.length < 2){ hideList(); return; }
                if (abortCtrl){ try{ abortCtrl.abort(); }catch(e){} }
                abortCtrl = (window.AbortController? new AbortController(): null);
                var url = 'Consulta_Medica.php?ajax=pacientes&q=' + encodeURIComponent(q);
                fetch(url, abortCtrl? { signal: abortCtrl.signal } : undefined)
                    .then(function(r){ return r.ok ? r.json() : []; })
                    .then(function(data){ render(Array.isArray(data)? data: []); })
                    .catch(function(){ /* ignore */ });
            }, 180);

            input.addEventListener('input', function(){ doFetch(this.value.trim()); });
            input.addEventListener('keydown', function(e){
                var nodes = list.querySelectorAll('.typeahead-item');
                if (e.key === 'ArrowDown'){
                    e.preventDefault();
                    if (!nodes.length){ doFetch(input.value.trim()); return; }
                    setActive((activeIndex+1) % nodes.length);
                } else if (e.key === 'ArrowUp'){
                    e.preventDefault();
                    if (!nodes.length){ return; }
                    setActive((activeIndex-1+nodes.length) % nodes.length);
                } else if (e.key === 'Enter'){
                    if (activeIndex >= 0){ e.preventDefault(); selectIndex(activeIndex); }
                } else if (e.key === 'Escape'){
                    hideList();
                }
            });
            document.addEventListener('click', function(ev){ if (!ev.target.closest('.typeahead-wrapper')) hideList(); });
        })();
    </script>
<script>
  // Inicializar tooltips de Bootstrap 5
  (function(){
    if (window.bootstrap && document.querySelectorAll('[data-bs-toggle="tooltip"]').length){
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl); });
    }
  })();
</script>
<script>
  // Rellenar modal de detalle de consulta al hacer clic en el botón ojo
  (function(){
    document.addEventListener('click', function(ev){
      var btn = ev.target.closest('.ver-consulta');
      if (!btn) return;
      var g = function(k){ return btn.getAttribute('data-'+k) || ''; };
      var set = function(id, val){ var el = document.getElementById(id); if (el) el.textContent = val || ''; };
      set('vc-fecha', g('fecha'));
      set('vc-peso', g('peso'));
      set('vc-estatura', g('estatura'));
      set('vc-edad', g('edad'));
      set('vc-imc', g('imc'));
      set('vc-mm', g('mm'));
      set('vc-motivo', g('motivo'));
      var notas = g('notas');
      var notasEl = document.getElementById('vc-notas');
      if (notasEl){ notasEl.textContent = notas || ''; }
    });
  })();
</script>
</body>
</html>
