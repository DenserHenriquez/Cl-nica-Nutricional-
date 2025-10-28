<?php
<<<<<<< Updated upstream
session_start();
require_once __DIR__ . '/db_connection.php';

function generarExpediente() {
    $fecha = date('Ymd');
    $rand  = strtoupper(bin2hex(random_bytes(4)));
    return "EXP-{$fecha}-{$rand}";
}

$errores = [];

// Si viene POST: validar, insertar y redirigir (PRG)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id        = isset($_POST['usuario_id']) ? trim($_POST['usuario_id']) : '';
    $telefono          = isset($_POST['contacto_emergencia_telefono']) ? trim($_POST['contacto_emergencia_telefono']) : '';
    $tipo_paciente     = isset($_POST['tipo_paciente']) ? trim($_POST['tipo_paciente']) : '';
    $historial_inicial = isset($_POST['historial_inicial']) ? trim($_POST['historial_inicial']) : '';
    $activo            = isset($_POST['activo']) ? 1 : 0;

    // Validaciones
    if ($usuario_id === '' || !ctype_digit($usuario_id) || (int)$usuario_id <= 0) {
        $errores[] = "El campo Usuario ID es obligatorio y debe ser numérico.";
    }
    if ($telefono === '') {
        $errores[] = "El teléfono de emergencia es obligatorio.";
    } else {
        $soloDigitos = preg_replace('/\D+/', '', $telefono);
        if (strlen($soloDigitos) < 7 || strlen($soloDigitos) > 20) {
            $errores[] = "El teléfono de emergencia no parece válido.";
        }
    }
    if ($tipo_paciente === '') {
        $errores[] = "El tipo de paciente es obligatorio.";
    }
    if ($historial_inicial === '') {
        $errores[] = "El historial clínico inicial es obligatorio.";
    }

    // Verificar que usuario_id exista en la tabla usuarios (opcional pero recomendado)
    if (empty($errores)) {
        $chk = $conexion->prepare("SELECT 1 FROM usuarios WHERE id_usuarios = ? LIMIT 1");
        if ($chk) {
            $uid = (int)$usuario_id;
            $chk->bind_param("i", $uid);
            $chk->execute();
            $resChk = $chk->get_result();
            if (!$resChk || $resChk->num_rows === 0) {
                $errores[] = "El Usuario ID no existe en la tabla de usuarios.";
            }
            $chk->close();
        } else {
            // si falla la preparación, no bloqueamos por esto, pero registramos
            error_log("Fallo prepare en verificación de usuario: " . $conexion->error);
        }
    }

    if (!$errores) {
        $expediente = generarExpediente();
        $stmt = $conexion->prepare("
            INSERT INTO pacientes
                (usuario_id, contacto_emergencia_telefono, tipo_paciente, historial_inicial, expediente_unique, activo)
            VALUES
                (?, ?, ?, ?, ?, ?)
        ");
        if ($stmt) {
            $stmt->bind_param("issssi", $usuario_id, $telefono, $tipo_paciente, $historial_inicial, $expediente, $activo);
            if ($stmt->execute()) {
                // Redirigir con PRG para evitar reenvío con F5
                $exp_qs = urlencode($expediente);
                header("Location: Registropacientes.php?ok=1&exp={$exp_qs}");
                exit;
            } else {
                $errores[] = "Error al guardar en la base de datos.";
            }
            $stmt->close();
        } else {
            $errores[] = "No se pudo preparar el guardado.";
=======
// Registropacientes.php
// Formulario para registrar pacientes con campos específicos

require_once __DIR__ . '/db_connection.php';
session_start();

// Verificar sesión de usuario
if (!isset($_SESSION['id_usuarios'])) {
    header('Location: index.php');
    exit;
}

$user_id = (int)$_SESSION['id_usuarios'];
$user_name = $_SESSION['nombre'] ?? '';

// Obtener nombre completo del usuario logueado
if ($user_id > 0 && empty($user_name)) {
    if ($stmt = $conexion->prepare('SELECT Nombre_completo FROM usuarios WHERE id_usuarios = ? LIMIT 1')) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($dbName);
        if ($stmt->fetch() && $dbName) {
            $user_name = $dbName;
            $_SESSION['nombre'] = $dbName;
        }
        $stmt->close();
    }
}

// Crear tabla Expediente si no existe
$createExpedienteTable = "
CREATE TABLE IF NOT EXISTS Expediente (
    id_expediente INT AUTO_INCREMENT PRIMARY KEY,
    id_pacientes INT NOT NULL,
    talla DECIMAL(5,2) COMMENT 'Talla en cm',
    peso DECIMAL(5,2) COMMENT 'Peso en kg',
    estatura DECIMAL(5,2) COMMENT 'Estatura en cm',
    IMC DECIMAL(5,2) COMMENT 'Índice de Masa Corporal',
    masa_muscular DECIMAL(5,2) COMMENT 'Masa muscular en kg',
    enfermedades_base TEXT COMMENT 'Enfermedades de base',
    medicamentos TEXT COMMENT 'Medicamentos',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pacientes) REFERENCES pacientes(id_pacientes) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";
$conexion->query($createExpedienteTable);

$errores = [];
$exito = '';

// Manejo de POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF simple
    if (!isset($_POST['csrf']) || !isset($_SESSION['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $errores[] = 'Token inválido. Recargue la página.';
    }

    $dni = isset($_POST['dni']) ? trim($_POST['dni']) : '';
    $fecha_nacimiento = isset($_POST['fecha_nacimiento']) ? trim($_POST['fecha_nacimiento']) : '';
    $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';

    // Datos del expediente
    $talla = isset($_POST['talla']) ? (float)$_POST['talla'] : null;
    $peso = isset($_POST['peso']) ? (float)$_POST['peso'] : null;
    $estatura = isset($_POST['estatura']) ? (float)$_POST['estatura'] : null;
    $imc = isset($_POST['imc']) ? (float)$_POST['imc'] : null;
    $masa_muscular = isset($_POST['masa_muscular']) ? (float)$_POST['masa_muscular'] : null;
    $enfermedades_base = isset($_POST['enfermedades_base']) ? trim($_POST['enfermedades_base']) : '';
    $medicamentos = isset($_POST['medicamentos']) ? trim($_POST['medicamentos']) : '';

    // Validaciones
    if (!preg_match('/^\d{13}$/', $dni)) {
        $errores[] = 'DNI debe contener exactamente 13 dígitos numéricos.';
    }

    if (empty($fecha_nacimiento)) {
        $errores[] = 'Fecha de nacimiento es obligatoria.';
    } else {
        $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
        if (!$fecha_obj) {
            $errores[] = 'Fecha de nacimiento inválida.';
        } else {
            $hoy = new DateTime();
            $edad = $hoy->diff($fecha_obj)->y;
            if ($edad < 0 || $edad > 150) {
                $errores[] = 'Fecha de nacimiento inválida.';
            }
        }
    }

    if (!preg_match('/^\d{8}$/', $telefono)) {
        $errores[] = 'Teléfono debe contener exactamente 8 dígitos numéricos.';
    }

    if (empty($errores)) {
        // Calcular edad
        $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
        $hoy = new DateTime();
        $edad = $hoy->diff($fecha_obj)->y;

        // Insertar paciente
        $sql = "INSERT INTO pacientes (id_usuarios, nombre_completo, DNI, fecha_nacimiento, edad, telefono)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('isssis', $user_id, $user_name, $dni, $fecha_nacimiento, $edad, $telefono);
            if ($stmt->execute()) {
                $id_paciente = $conexion->insert_id;

                // Insertar expediente
                $sql_expediente = "INSERT INTO Expediente (id_pacientes, talla, peso, estatura, IMC, masa_muscular, enfermedades_base, medicamentos)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_expediente = $conexion->prepare($sql_expediente);
                if ($stmt_expediente) {
                    $stmt_expediente->bind_param('idddddss', $id_paciente, $talla, $peso, $estatura, $imc, $masa_muscular, $enfermedades_base, $medicamentos);
                    if ($stmt_expediente->execute()) {
                        $exito = 'Paciente y expediente registrados correctamente.';
                    } else {
                        $errores[] = 'Error al guardar expediente: ' . $stmt_expediente->error;
                    }
                    $stmt_expediente->close();
                } else {
                    $errores[] = 'Error preparando consulta de expediente: ' . $conexion->error;
                }
            } else {
                $errores[] = 'Error al guardar en BD: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $errores[] = 'Error preparando consulta: ' . $conexion->error;
>>>>>>> Stashed changes
        }
    }
}

<<<<<<< Updated upstream
// Mensaje de éxito desde GET (después de PRG)
$exito = null;
if (isset($_GET['ok']) && $_GET['ok'] === '1') {
    $exp_show = isset($_GET['exp']) ? $_GET['exp'] : '';
    $exito = $exp_show ? "Paciente registrado correctamente. Expediente: " . htmlspecialchars($exp_show, ENT_QUOTES, 'UTF-8')
                       : "Paciente registrado correctamente.";
}

// Consultar últimos pacientes con nombre de usuario (JOIN)
$limit = 20;
$pacientes = [];
$q = $conexion->prepare("
    SELECT
        p.id_paciente,
        p.usuario_id,
        u.Nombre_completo AS nombre_paciente,
        p.contacto_emergencia_telefono,
        p.tipo_paciente,
        p.historial_inicial,
        p.expediente_unique,
        p.activo,
        p.fecha_registro
    FROM pacientes p
    LEFT JOIN usuarios u ON u.id_usuarios = p.usuario_id
    ORDER BY p.id_paciente DESC
    LIMIT ?
");
if ($q) {
    $q->bind_param("i", $limit);
    $q->execute();
    $res = $q->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $pacientes[] = $row;
        }
    }
    $q->close();
}
=======
// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

>>>>>>> Stashed changes
?>
<!DOCTYPE html>
<html lang="es">
<head>
<<<<<<< Updated upstream
    <meta charset="UTF-8">
    <title>Registro de Pacientes</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
    <style>
        .form-wrapper {
            max-width: 520px;
            background: white;
            margin: 40px auto 20px auto;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0px 8px 20px rgba(0,0,0,0.1);
        }
        .form-wrapper h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #46A2FD;
        }
        .form-group { margin-top: 14px; }
        .form-group label { font-size: 14px; color: #444; display:block; margin-bottom:6px; }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            background: #F2F2F2;
            border: none;
            border-radius: 6px;
            outline: none;
            font-size: 15px;
        }
        .form-group textarea { resize: vertical; min-height: 90px; }
        .btn { width: 100%; background: #46A2FD; color: #fff; border: none; padding: 10px; margin-top: 18px; border-radius: 6px; cursor: pointer; font-weight: bold; }

        .btn.secondary { background: #777; width: auto; padding: 10px 14px; text-decoration: none; display: inline-block; color: #fff; text-align: center; border-radius: 6px; }

        .alert { margin-top: 12px; padding: 10px; border-radius: 6px; font-size: 14px; }
        .alert.ok { background: #e8f9ee; color: #156d2d; border: 1px solid #b8eac7; }
        .alert.err { background: #fdecea; color: #8a1c1c; border: 1px solid #f5c2c0; }

        .list-wrapper {
            max-width: 1100px;
            margin: 10px auto 60px auto;
            background: rgba(255,255,255,0.92);
            border-radius: 12px;
            box-shadow: 0px 8px 20px rgba(0,0,0,0.08);
            padding: 20px;
        }
        .list-wrapper h3 { color: #46A2FD; margin: 0 0 12px 0; }
        .tabla {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        .tabla thead th {
            text-align: left;
            background: #f2f6ff;
            padding: 10px;
            border-bottom: 1px solid #e6ecf5;
        }
        .tabla tbody td {
            padding: 10px;
            border-bottom: 1px solid #eef1f5;
            vertical-align: top;
        }
        .badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            display: inline-block;
        }
        .badge.ok { background: #e7f7ed; color: #1b6d36; border: 1px solid #bfe6cd; }
        .badge.off { background: #fff2ee; color: #8a1c1c; border: 1px solid #f5c2c0; }
        .empty {
            padding: 16px; background: #fafbfe; border: 1px dashed #dbe3f0; border-radius: 10px; color: #6b7280;
        }

        @media (max-width: 900px) {
            .tabla thead { display: none; }
            .tabla, .tabla tbody, .tabla tr, .tabla td { display: block; width: 100%; }
            .tabla tr { margin-bottom: 12px; border: 1px solid #eef1f5; border-radius: 8px; }
            .tabla tbody td { border-bottom: none; }
            .tabla tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                display: block;
                margin-bottom: 4px;
                color: #374151;
=======
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Registro de Pacientes</title>
    <link rel="stylesheet" href="assets/css/estilos.css" />
    <style>
        :root {
            --primary-900: #0d47a1;
            --primary-700: #1565c0;
            --primary-500: #1976d2;
            --primary-300: #42a5f5;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-700: #374151;
            --gray-900: #111827;
            --success: #10b981;
            --error: #ef4444;
            --radius: 8px;
            --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 20px auto;
            background: var(--white);
            padding: 24px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        h1 {
            color: var(--primary-900);
            text-align: center;
            margin-bottom: 8px;
            font-size: 2rem;
            font-weight: 700;
        }

        .subtitle {
            color: var(--gray-700);
            text-align: center;
            margin-bottom: 24px;
            font-size: 1rem;
        }

        .card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .row {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .row > * {
            flex: 1 1 200px;
        }

        label {
            display: block;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 4px;
        }

        input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
        }

        button {
            background: var(--primary-500);
            color: var(--white);
            border: none;
            cursor: pointer;
            font-weight: 500;
            padding: 10px 16px;
            border-radius: 6px;
            transition: background 0.2s;
            width: 100%;
        }

        button:hover {
            background: var(--primary-700);
        }

        h3 {
            color: var(--primary-900);
            font-size: 1.25rem;
            font-weight: 600;
            margin-top: 24px;
            margin-bottom: 16px;
            border-bottom: 2px solid var(--primary-300);
            padding-bottom: 8px;
        }

        .errores {
            background: #fef2f2;
            color: var(--error);
            padding: 12px;
            border-radius: var(--radius);
            border: 1px solid #fecaca;
            margin-bottom: 16px;
        }

        .exito {
            background: #f0fdf4;
            color: var(--success);
            padding: 12px;
            border-radius: var(--radius);
            border: 1px solid #bbf7d0;
            margin-bottom: 16px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .form-actions a {
            display: inline-block;
            padding: 10px 16px;
            background: var(--gray-100);
            color: var(--gray-700);
            text-decoration: none;
            border-radius: 6px;
            border: 1px solid var(--gray-200);
            transition: background 0.2s;
            flex: 1;
            text-align: center;
        }

        .form-actions a:hover {
            background: var(--gray-200);
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 16px;
            }
            .row {
                flex-direction: column;
                gap: 12px;
>>>>>>> Stashed changes
            }
        }
    </style>
</head>
<body>
<<<<<<< Updated upstream

<div class="form-wrapper">
    <h2>Registro de Paciente</h2>

    <?php if ($exito): ?>
        <div class="alert ok"><?php echo $exito; ?></div>
    <?php endif; ?>

    <?php if ($errores): ?>
        <div class="alert err">
            <?php foreach ($errores as $e) echo "<div>".htmlspecialchars($e, ENT_QUOTES, 'UTF-8')."</div>"; ?>
        </div>
    <?php endif; ?>

    <!-- Botón para volver al Menú Principal (fuera del form, para no enviar por accidente) -->
    <div style="max-width:520px;margin:0 auto 12px auto;text-align:right;">
        <a href="Menuprincipal.php" class="btn secondary">Volver al Menú</a>
    </div>

    <form method="post" autocomplete="off">
        <div class="form-group">
            <label>Usuario ID</label>
            <input type="number" name="usuario_id" required>
        </div>

        <div class="form-group">
            <label>Teléfono de emergencia</label>
            <input type="text" name="contacto_emergencia_telefono" placeholder="+504 9999-1234" required>
        </div>

        <div class="form-group">
            <label>Tipo de paciente</label>
            <select name="tipo_paciente" required>
                <option value="">-- Selecciona --</option>
                <option value="Pérdida de peso">Pérdida de peso</option>
                <option value="Ganancia muscular">Ganancia muscular</option>
                <option value="Control metabólico">Control metabólico</option>
                <option value="Mejora de hábitos">Mejora de hábitos</option>
                <option value="Seguimiento">Seguimiento</option>
            </select>
        </div>

        <div class="form-group">
            <label>Historial clínico inicial</label>
            <textarea name="historial_inicial" rows="4" placeholder="Antecedentes relevantes, motivo de consulta, notas iniciales..." required></textarea>
        </div>

        <div class="form-group">
            <label><input type="checkbox" name="activo" checked> Activo (1)</label>
        </div>

        <button class="btn" type="submit">Guardar</button>
    </form>
</div>

<div class="list-wrapper">
    <h3>Pacientes registrados (últimos <?php echo (int)$limit; ?>)</h3>

    <?php if (empty($pacientes)): ?>
        <div class="empty">Aún no hay pacientes registrados.</div>
    <?php else: ?>
        <table class="tabla">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuario ID</th>
                    <th>Nombre del paciente</th>
                    <th>Teléfono emergencia</th>
                    <th>Tipo de paciente</th>
                    <th>Historial inicial</th>
                    <th>Expediente</th>
                    <th>Estado</th>
                    <th>Fecha registro</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pacientes as $p): ?>
                    <tr>
                        <td data-label="ID"><?php echo (int)$p['id_paciente']; ?></td>
                        <td data-label="Usuario ID"><?php echo (int)$p['usuario_id']; ?></td>
                        <td data-label="Nombre del paciente"><?php echo htmlspecialchars($p['nombre_paciente'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td data-label="Teléfono emergencia"><?php echo htmlspecialchars($p['contacto_emergencia_telefono'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td data-label="Tipo de paciente"><?php echo htmlspecialchars($p['tipo_paciente'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td data-label="Historial inicial"><?php echo nl2br(htmlspecialchars($p['historial_inicial'] ?? '', ENT_QUOTES, 'UTF-8')); ?></td>
                        <td data-label="Expediente"><?php echo htmlspecialchars($p['expediente_unique'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td data-label="Estado">
                            <?php if ((int)$p['activo'] === 1): ?>
                                <span class="badge ok">Activo</span>
                            <?php else: ?>
                                <span class="badge off">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Fecha registro"><?php echo htmlspecialchars($p['fecha_registro'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
=======
    <div class="container">
        <div style="position: relative; margin-bottom: 16px;">
            <a href="Menuprincipal.php" style="position: absolute; top: 0; right: 0; display: inline-block; padding: 6px 12px; background: var(--primary-500); color: var(--white); text-decoration: none; border-radius: 6px; font-weight: 500; font-size: 0.875rem; transition: background 0.2s;">Menu Principal</a>
        </div>
        <h1>Registro de Pacientes</h1>
        <p class="subtitle">Complete los datos para registrar un nuevo paciente.</p>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $e): ?>
                    <div>- <?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="exito"><?= htmlspecialchars($exito, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                <div class="row">
                    <label>Nombre completo
                        <input type="text" value="<?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') ?>" readonly />
                    </label>
                </div>

                <div class="row">
                    <label>DNI (13 dígitos)
                        <input type="text" name="dni" pattern="\d{13}" maxlength="13" placeholder="0823200610125" required />
                    </label>
                </div>

                <div class="row">
                    <label>Fecha de nacimiento
                        <input type="date" name="fecha_nacimiento" required onchange="calcularEdad()" />
                    </label>
                    <label>Edad
                        <input type="text" id="edad" readonly />
                    </label>
                </div>

                <div class="row">
                    <label>Teléfono (8 dígitos)
                        <input type="text" name="telefono" pattern="\d{8}" maxlength="8" placeholder="99553364" required />
                    </label>
                </div>

                <h3>Evaluación Física del Paciente</h3>

                <div class="row">
                    <label>Talla (cm)
                        <input type="number" name="talla" step="0.01" placeholder="170.5" />
                    </label>
                    <label>Peso (kg)
                        <input type="number" name="peso" step="0.01" placeholder="70.5" />
                    </label>
                </div>

                <div class="row">
                    <label>Estatura (cm)
                        <input type="number" name="estatura" step="0.01" placeholder="170.5" />
                    </label>
                    <label>IMC
                        <input type="number" name="imc" step="0.01" placeholder="24.3" />
                    </label>
                </div>

                <div class="row">
                    <label>Masa Muscular (kg)
                        <input type="number" name="masa_muscular" step="0.01" placeholder="50.0" />
                    </label>
                </div>

                <div class="row">
                    <label>Enfermedades de Base
                        <textarea name="enfermedades_base" rows="3" placeholder="Ej: Diabetes, Hipertensión"></textarea>
                    </label>
                    <label>Medicamentos
                        <textarea name="medicamentos" rows="3" placeholder="Ej: Metformina, Losartán"></textarea>
                    </label>
                </div>

                <button type="submit">Guardar</button>
            </form>
        </div>


    </div>

    <script>
        function calcularEdad() {
            const fechaInput = document.querySelector('input[name="fecha_nacimiento"]');
            const edadInput = document.getElementById('edad');

            if (fechaInput.value) {
                const fechaNac = new Date(fechaInput.value);
                const hoy = new Date();
                let edad = hoy.getFullYear() - fechaNac.getFullYear();
                const mes = hoy.getMonth() - fechaNac.getMonth();

                if (mes < 0 || (mes === 0 && hoy.getDate() < fechaNac.getDate())) {
                    edad--;
                }

                edadInput.value = edad;
            } else {
                edadInput.value = '';
            }
        }
    </script>
</body>
</html>
>>>>>>> Stashed changes
