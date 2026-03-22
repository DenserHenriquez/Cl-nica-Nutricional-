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

class EvaluadorNutricional {
    public static function clasificarIMC($imc) {
        if ($imc < 18.5) return ['label' => 'Delgadez I', 'color' => '#fd7e14'];
        if ($imc < 25.0) return ['label' => 'Normal', 'color' => '#28a745'];
        if ($imc < 30.0) return ['label' => 'Sobrepeso', 'color' => '#ffc107'];
        if ($imc < 35.0) return ['label' => 'Obesidad I', 'color' => '#dc3545'];
        if ($imc < 40.0) return ['label' => 'Obesidad II', 'color' => '#c82333'];
        return ['label' => 'Obesidad III', 'color' => '#721c24'];
    }
}

function clasificarGrasaCorporal($porcentaje, $edad, $sexo) {
    // Definición de rangos según sexo y edad
    $rangos = [
        'mujer' => [
            '20-39' => [21, 33, 39],
            '40-59' => [23, 34, 40],
            '60-79' => [24, 36, 42]
        ],
        'hombre' => [
            '20-39' => [8, 20, 25],
            '40-59' => [11, 22, 28],
            '60-79' => [13, 25, 30]
        ]
    ];

    // Determinar grupo de edad
    $grupo = '';
    if ($edad >= 20 && $edad <= 39) $grupo = '20-39';
    elseif ($edad >= 40 && $edad <= 59) $grupo = '40-59';
    elseif ($edad >= 60) $grupo = '60-79';
    else return ["Bajo", "text-muted"]; // Rango por defecto para menores

    $limites = $rangos[$sexo][$grupo];

    // Lógica de clasificación
    if ($porcentaje < $limites[0]) {
        return ["Bajo", "text-info"];
    } elseif ($porcentaje < $limites[1]) {
        return ["Recomendado", "text-success"];
    } elseif ($porcentaje < $limites[2]) {
        return ["Alto", "text-warning"];
    } else {
        return ["Muy Alto", "text-danger"];
    }
}

/**
 * Función para evaluar el porcentaje de músculo esquelético.
 * @param float $porcentaje El valor obtenido de la báscula.
 * @param string $sexo 'M' para Masculino, 'F' para Femenino.
 * @return array Retorna un arreglo con el nivel y la descripción.
 */
function evaluarMusculoEsqueletico($porcentaje, $sexo) {
    $resultado = [
        'nivel' => '',
        'estado' => '',
        'color' => '' // Opcional para interfaces UI
    ];

    $sexo = strtoupper($sexo);

    if ($sexo === 'F') {
        // Rangos para F
        if ($porcentaje < 24.3) {
            $resultado['nivel'] = 'Bajo';
            $resultado['estado'] = 'Riesgo de sarcopenia o debilidad.';
            $resultado['color'] = 'red';
        } elseif ($porcentaje <= 30.2) {
            $resultado['nivel'] = 'Recomendado';
            $resultado['estado'] = 'Nivel saludable.';
            $resultado['color'] = 'green';
        } elseif ($porcentaje <= 35.2) {
            $resultado['nivel'] = 'Alto';
            $resultado['estado'] = 'Buen tono muscular.';
            $resultado['color'] = 'blue';
        } else {
            $resultado['nivel'] = 'Muy Alto';
            $resultado['estado'] = 'Nivel óptimo/atlético.';
            $resultado['color'] = 'purple';
        }
    } elseif ($sexo === 'M') {
        // Rangos para M
        if ($porcentaje < 32.9) {
            $resultado['nivel'] = 'Bajo';
            $resultado['estado'] = 'Necesidad de fortalecer el sistema.';
            $resultado['color'] = 'red';
        } elseif ($porcentaje <= 39.1) {
            $resultado['nivel'] = 'Recomendado';
            $resultado['estado'] = 'Rango estándar de salud.';
            $resultado['color'] = 'green';
        } elseif ($porcentaje <= 45.8) {
            $resultado['nivel'] = 'Alto';
            $resultado['estado'] = 'Nivel de deportista.';
            $resultado['color'] = 'blue';
        } else {
            $resultado['nivel'] = 'Muy Alto';
            $resultado['estado'] = 'Alto rendimiento.';
            $resultado['color'] = 'purple';
        }
    } else {
        $resultado['nivel'] = 'Error';
        $resultado['estado'] = 'Sexo no válido definido.';
    }

    return $resultado;
}

/**
 * Función para clasificar el nivel de grasa visceral
 * @param int $nivel Índice de grasa visceral (1-59)
 * @return array Retorna la categoría, el riesgo y una clase de color para el diseño
 */
function clasificarGrasaVisceral($nivel) {
    if ($nivel >= 1 && $nivel <= 9) {
        return [
            'estado' => 'Saludable (En Forma)',
            'alerta' => 'Bajo',
            'color'  => 'success' // Verde
        ];
    } elseif ($nivel >= 10 && $nivel <= 12) {
        return [
            'estado' => 'Exceso (Alerta)',
            'alerta' => 'Moderado',
            'color'  => 'warning' // Amarillo/Naranja
        ];
    } elseif ($nivel >= 13) {
        return [
            'estado' => 'Alto (Peligro)',
            'alerta' => 'Alto',
            'color'  => 'danger' // Rojo
        ];
    } else {
        return [
            'estado' => 'Valor inválido',
            'alerta' => 'N/A',
            'color'  => 'secondary'
        ];
    }
}

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

// Migration: Add new composition fields if they don't exist
$fields = ['grasa_visceral', 'grasa_corporal', 'musculo_esqueletico'];
foreach ($fields as $field) {
    $__chkF = $conexion->query("SHOW COLUMNS FROM consultas_medicas LIKE '$field'");
    if (!$__chkF || $__chkF->num_rows === 0) {
        @$conexion->query("ALTER TABLE consultas_medicas ADD COLUMN $field DECIMAL(10,2) NULL AFTER masa_muscular");
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
    $sql = "SELECT p.id_pacientes, p.nombre_completo, p.DNI, p.telefono, p.fecha_nacimiento, p.edad,
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

// Defensive: ensure sexo column exists in pacientes (before any queries)
$checkSexoPac = $conexion->query("SHOW COLUMNS FROM pacientes LIKE 'sexo'");
if (!$checkSexoPac || $checkSexoPac->num_rows === 0) {
    $addSexoSql = "ALTER TABLE pacientes ADD COLUMN sexo ENUM('M','F') NOT NULL DEFAULT 'M' AFTER nombre_completo";
    if ($conexion->query($addSexoSql) === TRUE) {
        // Sync with usuarios.sexo where possible
        $syncSql = "UPDATE pacientes p JOIN usuarios u ON p.id_usuarios = u.id_usuarios SET p.sexo = u.sexo WHERE u.sexo IS NOT NULL";
        $conexion->query($syncSql);
    }
}

// Fetch additional patient data for grasa classification (edad and sexo from pacientes/usuarios)
$edadUsuario = 30; // Default
$sexoUsuario = 'M'; // Default DB value

if ($pacienteSel && isset($pacienteSel['id_pacientes'])) {
    // Dynamic query: include sexo only if column confirmed
    $selectFields = "edad";
    $hasSexo = ($checkSexoPac && $checkSexoPac->num_rows > 0);
    if ($hasSexo) {
        $selectFields .= ", sexo";
    }
    $sqlPacienteData = "SELECT $selectFields FROM pacientes WHERE id_pacientes = ?";
    if ($stPaciente = $conexion->prepare($sqlPacienteData)) {
        $stPaciente->bind_param('i', $pacienteSel['id_pacientes']);
        $stPaciente->execute();
        $rsPaciente = $stPaciente->get_result();
        if ($patientData = $rsPaciente->fetch_assoc()) {
            $edadUsuario = (int)($patientData['edad'] ?? 30);
            $sexoUsuario = $patientData['sexo'] ?? 'M';
        }
        $stPaciente->close();
    }
    
    // Fallback to usuarios.sexo if not in pacientes
    if ($sexoUsuario === 'M' || empty($sexoUsuario)) {
        $sqlUserSexo = "SELECT sexo FROM usuarios WHERE id_usuarios = (SELECT id_usuarios FROM pacientes WHERE id_pacientes = ?)";
        if ($stSexo = $conexion->prepare($sqlUserSexo)) {
            $stSexo->bind_param('i', $pacienteSel['id_pacientes']);
            $stSexo->execute();
            $rsSexo = $stSexo->get_result();
            if ($userSexo = $rsSexo->fetch_assoc()) {
                $sexoUsuario = $userSexo['sexo'] ?? 'M';
            }
            $stSexo->close();
        }
    }
    
    // Normalize for functions (M/F -> hombre/mujer)
    $sexoNorm = $sexoUsuario === 'M' ? 'hombre' : 'mujer';
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

    $grasa_visceral = post('grasa_visceral');
    $grasa_corporal = post('grasa_corporal');
    $masa_muscular = post('masa_muscular');
    $musculo_esqueletico = post('musculo_esqueletico');
    $motivo = post('motivo');
    $notas = post('notas');

    $pMM = ($masa_muscular === '' ? null : $masa_muscular);
    $pGV = ($grasa_visceral === '' ? null : $grasa_visceral);
    $pGC = ($grasa_corporal === '' ? null : $grasa_corporal);
    $pME = ($musculo_esqueletico === '' ? null : $musculo_esqueletico);

    // Validaciones básicas
    if ($paciente_id <= 0) { $errores[] = 'Seleccione un paciente.'; }
    if ($peso !== '' && !is_numeric($peso)) $errores[] = 'El peso debe ser numérico.';
    if ($estatura !== '' && !is_numeric($estatura)) $errores[] = 'La estatura debe ser numérica (cm).';
    if ($edad_metabolica !== '' && !is_numeric($edad_metabolica)) $errores[] = 'La edad metabólica debe ser numérica.';

    if ($grasa_visceral !== '' && !is_numeric($grasa_visceral)) $errores[] = 'Grasa visceral debe ser numérica.';
    if ($grasa_corporal !== '' && !is_numeric($grasa_corporal)) $errores[] = '% Grasa corporal debe ser numérica.';
    if ($musculo_esqueletico !== '' && !is_numeric($musculo_esqueletico)) $errores[] = '% Músculo esquelético debe ser numérica.';

    // Calcular IMC si procede
    $imc = null;
    if ($peso !== '' && $estatura !== '' && is_numeric($peso) && is_numeric($estatura) && floatval($estatura) > 0) {
        $imc = round(floatval($peso) / pow(floatval($estatura)/100, 2), 2);
    }

    if (!$errores) {
// $sqlI = "INSERT INTO consultas_medicas ..."; // DISABLED: table does not exist in schema
        if ($st = $conexion->prepare($sqlI)) {
            $pPeso = ($peso === '' ? null : $peso);
            $pEst = ($estatura === '' ? null : $estatura);
            $pEdad = ($edad_metabolica === '' ? null : $edad_metabolica);
            $pIMC = ($imc === null ? null : $imc);

            $st->bind_param('iiddddddddss', $userId, $paciente_id, $pPeso, $pEst, $pEdad, $pIMC, $pMM, $pGV, $pGC, $pME, $motivo, $notas);
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
$sqlList = "SELECT id, fecha, peso, estatura, edad_metabolica, imc, masa_muscular, grasa_visceral, grasa_corporal, musculo_esqueletico, motivo, notas
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
        .header-section { background: linear-gradient(135deg, #198754 0%, #146c43 100%); color: white; padding: 1.1rem 1.6rem; margin: 12px 1rem 1rem; border-radius: 12px; }
        .header-section h1 { font-size: 2.2rem; font-weight: 700; margin: 0; line-height: 1.3; }
        .header-section p { font-size: 1.05rem; opacity: 0.92; margin: 0; }
        .medical-icon { font-size: 1.9rem; color: #ffffff; }
        .hist-card td, .hist-card th { font-size: .95rem; }
        
        /* Dynamic Results Button */
        .resultados-btn {
            position: relative;
        }
        .resultados-btn .metric-count {
            background: #198754;
            color: white;
            min-width: 18px;
            height: 18px;
            font-weight: 600;
        }
        .resultados-btn .metric-count.one { background: #28a745; }
        .resultados-btn .metric-count.two-three { background: #ffc107; color: #000; border-color: #000; }
        .resultados-btn .metric-count.four { background: #dc3545; }
        
        /* IMC Button hover */
        .btn-outline-success.imc-btn:hover { 
            background-color: #198754 !important; 
            border-color: #198754 !important; 
            color: white !important; 
            transform: scale(1.05); 
        }
        #imc.form-control:focus { border-color: #198754 !important; box-shadow: 0 0 0 0.2rem rgba(25,135,84,0.25) !important; }
        #imc-badge { transition: all 0.2s ease; }
        /* IMC Modal enhancements */
        #modalIMC .modal-content { border-radius: 16px; }
        #modalIMC .display-4 { font-weight: 700; color: #198754 !important; }
        #modalIMC .badge { min-width: 120px; }
        
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
    <div class="header-section d-flex align-items-center gap-3">
        <div class="medical-icon"><i class="bi bi-journal-medical"></i></div>
        <div>
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


            <?php 
            // Keep computations for modals/table buttons (IMC/Grasa class available on demand)
            $imcActual = $ultimaConsulta['imc'] ?? 0;
            if ($ultimaConsulta && isset($ultimaConsulta['grasa_corporal']) && $ultimaConsulta['grasa_corporal'] !== null) {
                $porcentajeGrasa = (float)$ultimaConsulta['grasa_corporal'];
            } else {
                $porcentajeGrasa = 0;
            }
            ?>


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
                                <input type="number" step="0.01" class="form-control" id="estatura" name="estatura" value="<?= isset($ultimaConsulta['estatura']) ? h((string)$ultimaConsulta['estatura']) : '' ?>" oninput="calcularIMC()">

                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="edad_metabolica">Edad metabólica</label>
                                <input type="number" step="0.01" class="form-control" id="edad_metabolica" name="edad_metabolica">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="imc">IMC <small class="text-muted">(Auto-calculado)</small></label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-control" id="imc" name="imc" readonly placeholder="peso / (altura/100)²">
                                    <span class="input-group-text bg-success text-white px-2" id="imc-badge" style="display:none; min-width:80px; font-weight:600;">Calculando...</span>
                                </div>

                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label" for="musculo_esqueletico">% Músculo Esquelético</label>
                                <input type="number" step="0.01" class="form-control" id="musculo_esqueletico" name="musculo_esqueletico">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="grasa_visceral">Grasa Visceral</label>
                                <input type="number" step="0.01" class="form-control" id="grasa_visceral" name="grasa_visceral">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="grasa_corporal">% Grasa Corporal</label>
                                <input type="number" step="0.01" class="form-control" id="grasa_corporal" name="grasa_corporal">
                            </div>
                            <div class="col-md-6">
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
                        <button class="btn btn-light btn-sm flex-shrink-0" type="submit" style="white-space:nowrap;"><i class="bi bi-funnel"></i> Filtrar</button>
                        <a class="btn btn-outline-light btn-sm flex-shrink-0" href="Consulta_Medica.php?paciente_id=<?= (int)$pacienteSel['id_pacientes'] ?>" style="white-space:nowrap;"><i class="bi bi-x-circle"></i> Limpiar</a>
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
                                    <th>Grasa Visceral</th>
    <th>% Grasa Corporal</th>
    <th>% Músculo Esquelético</th>
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
                                            <td><?= h($c['grasa_visceral'] !== null ? number_format((float)$c['grasa_visceral'], 2) : '') ?></td>
                                            <td><?= h($c['grasa_corporal'] !== null ? number_format((float)$c['grasa_corporal'], 2) : '') ?>%</td>
                                            <td><?= h($c['musculo_esqueletico'] !== null ? number_format((float)$c['musculo_esqueletico'], 2) : '') ?>%</td>
                                            <td><?= h($c['motivo'] ?? '') ?></td>
                                            <td class="text-center" style="width:1%; white-space:nowrap;">
                                                <button type="button" class="btn btn-sm btn-outline-primary ver-consulta" 
                                                    data-bs-toggle="modal" data-bs-target="#modalVerConsulta"
                                                    data-fecha="<?= h(date('d/m/Y H:i', strtotime($c['fecha']))) ?>"
                                                    data-peso="<?= h($c['peso'] !== null ? number_format((float)$c['peso'], 2) : '') ?>"
                                                    data-estatura="<?= h($c['estatura'] !== null ? number_format((float)$c['estatura'], 2) : '') ?>"
                                                    data-edad="<?= h($c['edad_metabolica'] !== null ? number_format((float)$c['edad_metabolica'], 2) : '') ?>"
                                                    data-imc="<?= h($c['imc'] !== null ? number_format((float)$c['imc'], 2) : '') ?>"
                                                    data-gv="<?= h($c['grasa_visceral'] !== null ? number_format((float)$c['grasa_visceral'], 2) : '') ?>"
                                                    data-gc="<?= h($c['grasa_corporal'] !== null ? number_format((float)$c['grasa_corporal'], 2) : '') ?>"
                                                    data-me="<?= h($c['musculo_esqueletico'] !== null ? number_format((float)$c['musculo_esqueletico'], 2) : '') ?>"
                                                    data-mm="<?= h($c['masa_muscular'] !== null ? number_format((float)$c['masa_muscular'], 2) : '') ?>"
                                                    data-motivo="<?= h($c['motivo'] ?? '') ?>"
                                                    data-notas="<?= h($c['notas'] ?? '') ?>"
                                                    title="Ver detalles">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php
                                                $imcVal = $c['imc'] !== null ? (float)$c['imc'] : 0;
                                                $gcVal = $c['grasa_corporal'] !== null ? (float)$c['grasa_corporal'] : 0;
                                                $meVal = $c['musculo_esqueletico'] !== null ? (float)$c['musculo_esqueletico'] : 0;
                                                $gvVal = $c['grasa_visceral'] !== null ? (float)$c['grasa_visceral'] : 0;
                                                $gvEval = clasificarGrasaVisceral($gvVal);
                                                ?>
                                                <button type="button" class="btn btn-sm btn-outline-info resultados-btn ms-1 position-relative" 
                                                    data-bs-toggle="modal" data-bs-target="#modalResultadosCompletos"
                                                    data-imc="<?= number_format($imcVal, 1) ?>"
                                                    data-gc="<?= number_format($gcVal, 1) ?>"
                                                    data-me="<?= number_format($meVal, 1) ?>"
                                                    data-gv="<?= number_format($gvVal, 1) ?>"
                                                    data-gv-estado="<?= h($gvEval['estado']) ?>"
                                                    data-gv-alerta="<?= h($gvEval['alerta']) ?>"
                                                    data-gv-color="<?= h($gvEval['color']) ?>"
                                                    data-available-metrics='<?= json_encode([$imcVal>0?"IMC":null, $gcVal>0?"%Grasa Corporal":null, $meVal>0?"%Musculo Esquelético":null, $gvVal>0?"Grasa Visceral":null]) ?>'
                                                    title="">
                                                    <i class="bi bi-graph-up-arrow"></i>
                                                    <span class="metric-count position-absolute top-0 start-100 translate-middle badge rounded-pill border border-white" style="font-size:0.65rem;"></span>
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

    <!-- Modal IMC + Grasa Corporal -->
    <div class="modal fade" id="modalIMC" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header bg-gradient" style="background: linear-gradient(135deg, #198754 0%, #146c43 100%); color: white;">
          <h6 class="modal-title mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Evaluación Corporal (IMC + Grasa + Músculo)</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-4">
            <!-- IMC Section -->
            <div class="text-center mb-4 pb-3 border-bottom">
              <div id="imc-value" class="display-5 fw-bold mb-2 text-primary" style="font-size: 2.2rem; min-height: 2.5rem;">--</div>
              <div id="imc-badge" class="badge fs-6 px-4 py-2 mx-auto d-block" style="font-weight: 600; max-width: 200px;">Sin datos</div>
              <div id="imc-label" class="mt-1 small text-muted">Sin IMC registrado</div>
            </div>
            
            <!-- Grasa Corporal Section -->
            <div class="text-center mb-4 pb-3 border-top border-bottom">
              <div id="gc-value" class="h4 fw-bold mb-2 text-success" style="font-size: 1.8rem;">-- %</div>
              <div id="gc-badge" class="badge fs-6 px-4 py-2 mx-auto d-block" style="font-weight: 600; max-width: 200px; background: #6c757d; color: white;">Sin datos</div>
              <div id="gc-label" class="mt-1 small text-muted">Sin % Grasa Corporal registrado</div>
            </div>

            <!-- Músculo Esquelético Section -->
            <div class="text-center">
              <div id="me-value" class="h4 fw-bold mb-2 text-info" style="font-size: 1.8rem;">-- %</div>
              <div id="me-badge" class="badge fs-6 px-4 py-2 mx-auto d-block" style="font-weight: 600; max-width: 200px; background: #6c757d; color: white;">Sin datos</div>
              <div id="me-label" class="mt-1 small text-muted">Sin % Músculo Esquelético registrado</div>
              <div id="me-estado" class="mt-2 small text-muted fst-italic" style="font-size: 0.9rem;"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Resultados Corporales Completos (4 métricas) -->
    <div class="modal fade" id="modalResultadosCompletos" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header" style="background: linear-gradient(135deg, #198754 0%, #146c43 100%); color: white;">
            <h6 class="modal-title mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Resultados Corporales Completos</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-4">
            <!-- 1. IMC Section -->
            <div class="text-center mb-4 pb-3 border-bottom">
              <div id="rc-imc-value" class="display-5 fw-bold mb-2 text-primary" style="font-size: 2.2rem; min-height: 2.5rem;">--</div>
              <div id="rc-imc-badge" class="badge fs-6 px-4 py-2 mx-auto d-block" style="font-weight: 600; max-width: 200px;">Sin datos</div>
              <div id="rc-imc-label" class="mt-1 small text-muted">Sin IMC registrado</div>
            </div>
            
            <!-- 2. Grasa Corporal Section -->
            <div class="text-center mb-4 pb-3 border-bottom">
              <div id="rc-gc-value" class="h4 fw-bold mb-2 text-success" style="font-size: 1.8rem;">-- %</div>
              <div id="rc-gc-badge" class="badge fs-6 px-4 py-2 mx-auto d-block" style="font-weight: 600; max-width: 200px; background: #6c757d; color: white;">Sin datos</div>
              <div id="rc-gc-label" class="mt-1 small text-muted">Sin % Grasa Corporal registrado</div>
            </div>

            <!-- 3. Músculo Esquelético Section -->
            <div class="text-center mb-4 pb-3 border-bottom">
              <div id="rc-me-value" class="h4 fw-bold mb-2 text-info" style="font-size: 1.8rem;">-- %</div>
              <div id="rc-me-badge" class="badge fs-6 px-4 py-2 mx-auto d-block" style="font-weight: 600; max-width: 200px; background: #6c757d; color: white;">Sin datos</div>
              <div id="rc-me-label" class="mt-1 small text-muted">Sin % Músculo Esquelético registrado</div>
              <div id="rc-me-estado" class="mt-2 small text-muted fst-italic" style="font-size: 0.9rem;"></div>
            </div>

            <!-- 4. Grasa Visceral Section -->
            <div class="text-center">
              <div id="rc-gv-value" class="h4 fw-bold mb-2 text-warning" style="font-size: 1.8rem;">--</div>
              <div id="rc-gv-card" class="card mx-auto" style="max-width: 300px; border-left: 5px solid #6c757d;">
                <div class="card-body p-3">
                  <h6 class="card-title mb-2">Grasa Visceral</h6>
                  <p class="mb-1">Estado: <span id="rc-gv-estado" class="badge fs-6">N/D</span></p>
                  <p class="mb-0">Riesgo: <strong id="rc-gv-alerta">N/A</strong></p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
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
              <div class="col-md-2"><strong>Grasa V:</strong> <span id="vc-gv"></span></div>
              <div class="col-md-2"><strong>% Grasa C:</strong> <span id="vc-gc"></span>%</div>
              <div class="col-md-2">
                <strong>Grasa Class:</strong> 
                <span id="vc-gc-class" class="badge bg-white border small text-muted">N/D</span>
              </div>
              <div class="col-md-2"><strong>% Músc. Esq:</strong> <span id="vc-me"></span>%</div>
              <div class="col-md-2"><strong>IMC:</strong> <span id="vc-imc"></span></div>
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
            var estatura = parseFloat(document.getElementById('estatura').value);
            var imcEl = document.getElementById('imc');
            var badgeEl = document.getElementById('imc-badge');
            
            if (!isNaN(peso) && !isNaN(estatura) && estatura > 0) {
                var imc = peso / Math.pow(estatura/100, 2);
                imcEl.value = imc.toFixed(2);
                
                // Clasificación IMC (igual que PHP)
                var classification = getIMCClassification(imc);
                if (badgeEl) {
                    badgeEl.textContent = classification.label;
                    badgeEl.style.backgroundColor = classification.color;
                    badgeEl.style.display = 'inline-flex';
                    badgeEl.style.alignItems = 'center';
                }
                imcEl.classList.add('is-valid');
            } else {
                imcEl.value = '';
                if (badgeEl) badgeEl.style.display = 'none';
                imcEl.classList.remove('is-valid');
            }
        }
        
        function getIMCClassification(imc) {
            if (imc < 18.5) return {label: 'Delgadez I', color: '#fd7e14'};
            if (imc < 25) return {label: 'Normal', color: '#28a745'};
            if (imc < 30) return {label: 'Sobrepeso', color: '#ffc107'};
            if (imc < 35) return {label: 'Obesidad I', color: '#dc3545'};
            if (imc < 40) return {label: 'Obesidad II', color: '#c82333'};
            return {label: 'Obesidad III', color: '#721c24'};
        }

        function getGrasaClass(porc, edad, sexo) {
            var rangos = {
                'mujer': {
                    '20-39': [21, 33, 39],
                    '40-59': [23, 34, 40],
                    '60-79': [24, 36, 42]
                },
                'hombre': {
                    '20-39': [8, 20, 25],
                    '40-59': [11, 22, 28],
                    '60-79': [13, 25, 30]
                }
            };
            var grupo = '';
            if (edad >= 20 && edad <= 39) grupo = '20-39';
            else if (edad >= 40 && edad <= 59) grupo = '40-59';
            else if (edad >= 60) grupo = '60-79';
            else return ['Bajo', 'text-muted'];
            
            var limites = rangos[sexo] ? rangos[sexo][grupo] : [0,0,0];
            if (porc < limites[0]) return ['Bajo', 'text-info'];
            else if (porc < limites[1]) return ['Recomendado', 'text-success'];
            else if (porc < limites[2]) return ['Alto', 'text-warning'];
            else return ['Muy Alto', 'text-danger'];
        }
        
        // Inicializar cálculo
        (function(){
          var pesoEl = document.getElementById('peso');
          var estaturaEl = document.getElementById('estatura');
          if (pesoEl && estaturaEl) {
            [pesoEl, estaturaEl].forEach(function(el){
              el.addEventListener('input', calcularIMC);
            });
            // Calcular inicial si hay valores
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
  // Rellenar modales
  (function(){
    // Modal detalles consulta
    document.addEventListener('click', function(ev){
      var btn = ev.target.closest('.ver-consulta');
      if (!btn) return;
      var g = function(k){ return btn.getAttribute('data-'+k) || ''; };
      var set = function(id, val){ var el = document.getElementById(id); if (el) el.textContent = val || ''; };
      set('vc-fecha', g('fecha'));
      set('vc-peso', g('peso'));
      set('vc-estatura', g('estatura'));
      set('vc-edad', g('edad'));
      set('vc-gv', g('gv'));
      set('vc-gc', g('gc'));
      
      // Compute grasa class for modal
      var gcVal = parseFloat(g('gc')) || 0;
      var edadU = <?= $edadUsuario ?>;
      var sexoU = '<?= $sexoUsuario ?>';
      var gcClassText = 'N/D';
      var gcClassColor = 'text-muted';
      if (gcVal > 0) {
        var gcClassResult = getGrasaClass(gcVal, edadU, sexoU);
        gcClassText = gcClassResult[0];
        gcClassColor = gcClassResult[1];
      }
      var gcClassEl = document.getElementById('vc-gc-class');
      if (gcClassEl) {
        gcClassEl.textContent = gcClassText;
        gcClassEl.className = 'badge bg-white border small ' + gcClassColor;
      }
      
      set('vc-me', g('me'));
      set('vc-imc', g('imc'));
      set('vc-mm', g('mm'));
      set('vc-motivo', g('motivo'));
      var notas = g('notas');
      var notasEl = document.getElementById('vc-notas');
      if (notasEl){ notasEl.textContent = notas || ''; }
    });

    // Modal IMC + Grasa classification
    document.addEventListener('click', function(ev){
      var btn = ev.target.closest('.imc-btn');
      if (!btn) return;
      
      var imcVal = parseFloat(btn.getAttribute('data-imc')) || 0;
      var gcVal = parseFloat(btn.getAttribute('data-gc')) || 0;
      
      var edadU = <?= $edadUsuario ?? 30 ?>;
      var sexoU = '<?= $sexoUsuario ?? 'hombre' ?>';
      
      // IMC Section
      var imcValueEl = document.getElementById('imc-value');
      var imcBadgeEl = document.getElementById('imc-badge');
      var imcLabelEl = document.getElementById('imc-label');
      
      if (imcValueEl) imcValueEl.textContent = isNaN(imcVal) || imcVal <= 0 ? '--' : imcVal.toFixed(1);
      
      if (imcVal > 0) {
        var imcClass = getIMCClassification(imcVal);
        if (imcBadgeEl) {
          imcBadgeEl.textContent = imcClass.label;
          imcBadgeEl.style.backgroundColor = imcClass.color;
          imcBadgeEl.style.color = imcClass.label === 'Normal' ? '#155724' : '#fff';
          imcBadgeEl.classList.remove('text-dark');
        }
        if (imcLabelEl) imcLabelEl.textContent = 'IMC: ' + imcVal.toFixed(1);
      } else {
        if (imcBadgeEl) {
          imcBadgeEl.textContent = 'Sin IMC';
          imcBadgeEl.style.backgroundColor = '#6c757d';
          imcBadgeEl.style.color = '#fff';
        }
        if (imcLabelEl) imcLabelEl.textContent = 'No hay registro de IMC';
      }
      
      // Grasa Corporal Section
      var gcValueEl = document.getElementById('gc-value');
      var gcBadgeEl = document.getElementById('gc-badge');
      var gcLabelEl = document.getElementById('gc-label');
      
      if (gcValueEl) gcValueEl.textContent = isNaN(gcVal) ? '--' : gcVal.toFixed(1) + ' %';
      
      if (gcVal > 0) {
        var gcClassResult = getGrasaClass(gcVal, edadU, sexoU);
        var gcClassText = gcClassResult[0];
        var gcClassColorClass = gcClassResult[1];
        
        if (gcBadgeEl) {
          gcBadgeEl.textContent = gcClassText;
          // Map class to color (matching Bootstrap-like)
          var gcColor = '#6c757d';
          if (gcClassColorClass === 'text-success') gcColor = '#28a745';
          else if (gcClassColorClass === 'text-info') gcColor = '#17a2b8';
          else if (gcClassColorClass === 'text-warning') gcColor = '#ffc107';
          else if (gcClassColorClass === 'text-danger') gcColor = '#dc3545';
          else if (gcClassColorClass === 'text-muted') gcColor = '#6c757d';
          
          gcBadgeEl.style.backgroundColor = gcColor;
          gcBadgeEl.style.color = gcClassText === 'Recomendado' ? '#155724' : '#fff';
          gcBadgeEl.classList.remove('text-dark');
        }
        if (gcLabelEl) gcLabelEl.textContent = '%' + gcVal.toFixed(1) + ' | ' + gcClassText;
      } else {
        if (gcBadgeEl) {
          gcBadgeEl.textContent = 'Sin datos';
          gcBadgeEl.style.backgroundColor = '#6c757d';
          gcBadgeEl.style.color = '#fff';
        }
        if (gcLabelEl) gcLabelEl.textContent = 'No hay registro de % Grasa Corporal';
      }

      // Músculo Esquelético Section
      var meVal = parseFloat(btn.getAttribute('data-me')) || 0;
      var meValueEl = document.getElementById('me-value');
      var meBadgeEl = document.getElementById('me-badge');
      var meLabelEl = document.getElementById('me-label');
      var meEstadoEl = document.getElementById('me-estado');
      
      if (meValueEl) meValueEl.textContent = isNaN(meVal) ? '--' : meVal.toFixed(1) + ' %';
      
      if (meVal > 0) {
        var meNivel = btn.getAttribute('data-me-nivel') || 'N/D';
        var meEstado = btn.getAttribute('data-me-estado') || '';
        var meColor = btn.getAttribute('data-me-color') || '#6c757d';
        
        if (meBadgeEl) {
          meBadgeEl.textContent = meNivel;
          meBadgeEl.style.backgroundColor = meColor;
          meBadgeEl.style.color = (meNivel === 'Recomendado') ? '#155724' : '#fff';
        }
        if (meLabelEl) meLabelEl.textContent = meVal.toFixed(1) + '% | ' + meNivel;
        if (meEstadoEl) meEstadoEl.textContent = meEstado;
      } else {
        if (meBadgeEl) {
          meBadgeEl.textContent = 'Sin datos';
          meBadgeEl.style.backgroundColor = '#6c757d';
          meBadgeEl.style.color = '#fff';
        }
        if (meLabelEl) meLabelEl.textContent = 'Sin % Músculo Esquelético registrado';
        if (meEstadoEl) meEstadoEl.textContent = '';
      }
    });

    // Dynamic titles and badges for resultados-btn (runs on DOM load)
    function updateResultadosButtons() {
        document.querySelectorAll('.resultados-btn').forEach(function(btn) {
            try {
                var metrics = JSON.parse(btn.getAttribute('data-available-metrics') || '[]');
                var available = metrics.filter(Boolean);
                var count = available.length;
                
                var title = count === 0 ? 'Sin métricas disponibles' : 
                           'Ver: ' + available.join(' + ') + 
                           (count === 4 ? ' (Completo)' : '');
                btn.title = title;
                
                var badgeEl = btn.querySelector('.metric-count');
                if (badgeEl) {
                    badgeEl.textContent = count;
                    badgeEl.className = 'metric-count position-absolute top-0 start-100 translate-middle badge rounded-pill border border-white ' + 
                                      (count === 1 ? 'one' : 
                                       (count <= 3 ? 'two-three' : 'four'));
                }
            } catch(e) {
                console.warn('Error updating button:', e);
            }
        });
    }
    
    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateResultadosButtons);
    } else {
        updateResultadosButtons();
    }
    
    // Modal Resultados Corporales Completos (4 métricas)
    document.addEventListener('click', function(ev){
      var btn = ev.target.closest('.resultados-btn');
      if (!btn) return;
      
      var imcVal = parseFloat(btn.getAttribute('data-imc')) || 0;
      var gcVal = parseFloat(btn.getAttribute('data-gc')) || 0;
      var meVal = parseFloat(btn.getAttribute('data-me')) || 0;
      var gvVal = parseFloat(btn.getAttribute('data-gv')) || 0;
      var gvEstado = btn.getAttribute('data-gv-estado') || 'N/D';
      var gvAlerta = btn.getAttribute('data-gv-alerta') || 'N/A';
      var gvColor = btn.getAttribute('data-gv-color') || 'secondary';
      
      // === 1. IMC ===
      var imcValueEl = document.getElementById('rc-imc-value');
      var imcBadgeEl = document.getElementById('rc-imc-badge');
      var imcLabelEl = document.getElementById('rc-imc-label');
      if (imcValueEl) imcValueEl.textContent = imcVal > 0 ? imcVal.toFixed(1) : '--';
      if (imcVal > 0) {
        var imcClass = getIMCClassification(imcVal);
        if (imcBadgeEl) {
          imcBadgeEl.textContent = imcClass.label;
          imcBadgeEl.style.backgroundColor = imcClass.color;
          imcBadgeEl.style.color = '#fff';
        }
        if (imcLabelEl) imcLabelEl.textContent = 'IMC calculado: ' + imcVal.toFixed(1);
      } else {
        if (imcBadgeEl) {
          imcBadgeEl.textContent = 'Sin IMC';
          imcBadgeEl.style.backgroundColor = '#6c757d';
          imcBadgeEl.style.color = '#fff';
        }
        if (imcLabelEl) imcLabelEl.textContent = 'No hay registro de IMC';
      }
      
      // === 2. % Grasa Corporal ===
      var gcValueEl = document.getElementById('rc-gc-value');
      var gcBadgeEl = document.getElementById('rc-gc-badge');
      var gcLabelEl = document.getElementById('rc-gc-label');
      if (gcValueEl) gcValueEl.textContent = gcVal > 0 ? gcVal.toFixed(1) + ' %' : '-- %';
      if (gcVal > 0) {
        // Simple classification (matching PHP)
        var gcClassText = gcVal < 20 ? 'Bajo' : (gcVal < 30 ? 'Recomendado' : (gcVal < 35 ? 'Alto' : 'Muy Alto'));
        var gcColor = gcVal < 20 ? '#17a2b8' : (gcVal < 30 ? '#28a745' : (gcVal < 35 ? '#ffc107' : '#dc3545'));
        if (gcBadgeEl) {
          gcBadgeEl.textContent = gcClassText;
          gcBadgeEl.style.backgroundColor = gcColor;
          gcBadgeEl.style.color = '#fff';
        }
        if (gcLabelEl) gcLabelEl.textContent = gcClassText + ' (' + gcVal.toFixed(1) + '%)';
      } else {
        if (gcBadgeEl) {
          gcBadgeEl.textContent = 'Sin datos';
          gcBadgeEl.style.backgroundColor = '#6c757d';
          gcBadgeEl.style.color = '#fff';
        }
        if (gcLabelEl) gcLabelEl.textContent = 'No hay registro de % Grasa Corporal';
      }
      
      // === 3. % Músculo Esquelético ===
      var meValueEl = document.getElementById('rc-me-value');
      var meBadgeEl = document.getElementById('rc-me-badge');
      var meLabelEl = document.getElementById('rc-me-label');
      var meEstadoEl = document.getElementById('rc-me-estado');
      if (meValueEl) meValueEl.textContent = meVal > 0 ? meVal.toFixed(1) + ' %' : '-- %';
      if (meVal > 0) {
        var meNivel, meEstado, meColor;
        // Simplified ranges (matching PHP evaluarMusculoEsqueletico)
        if (meVal < 25) { meNivel = 'Bajo'; meEstado = 'Fortalecer'; meColor = '#dc3545'; }
        else if (meVal <= 35) { meNivel = 'Recomendado'; meEstado = 'Saludable'; meColor = '#28a745'; }
        else if (meVal <= 42) { meNivel = 'Alto'; meEstado = 'Buen tono'; meColor = '#17a2b8'; }
        else { meNivel = 'Muy Alto'; meEstado = 'Óptimo'; meColor = '#6f42c1'; }
        
        if (meBadgeEl) {
          meBadgeEl.textContent = meNivel;
          meBadgeEl.style.backgroundColor = meColor;
          meBadgeEl.style.color = '#fff';
        }
        if (meLabelEl) meLabelEl.textContent = meNivel + ' (' + meVal.toFixed(1) + '%)';
        if (meEstadoEl) meEstadoEl.textContent = meEstado;
      } else {
        if (meBadgeEl) {
          meBadgeEl.textContent = 'Sin datos';
          meBadgeEl.style.backgroundColor = '#6c757d';
          meBadgeEl.style.color = '#fff';
        }
        if (meLabelEl) meLabelEl.textContent = 'Sin % Músculo Esquelético registrado';
        if (meEstadoEl) meEstadoEl.textContent = '';
      }
      
      // === 4. Grasa Visceral ===
      var gvValueEl = document.getElementById('rc-gv-value');
      var gvEstadoEl = document.getElementById('rc-gv-estado');
      var gvAlertaEl = document.getElementById('rc-gv-alerta');
      var gvCard = document.getElementById('rc-gv-card');
      
      if (gvValueEl) gvValueEl.textContent = gvVal > 0 ? gvVal.toFixed(1) : '--';
      if (gvEstadoEl) {
        gvEstadoEl.textContent = gvEstado;
        gvEstadoEl.className = 'badge badge-' + gvColor + ' fs-6';
      }
      if (gvAlertaEl) gvAlertaEl.textContent = gvAlerta;
      
      // Update card border
      var colorMap = { 'success': '#28a745', 'warning': '#ffc107', 'danger': '#dc3545', 'secondary': '#6c757d' };
      if (gvCard) gvCard.style.borderLeftColor = colorMap[gvColor] || '#6c757d';
    });
  })();
</script>

</body>
</html>
