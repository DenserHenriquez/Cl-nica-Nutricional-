<?php
session_start();
require_once __DIR__ . '/db_connection.php';

// Requiere sesión activa
$userId = isset($_SESSION['id_usuarios']) ? intval($_SESSION['id_usuarios']) : null;
$userRole = isset($_SESSION['rol']) ? strtolower($_SESSION['rol']) : null;

if (!$userId) {
    header('Location: index.php');
    exit;
}

// Determinar si es staff (médico o administrador)
$isStaff = in_array($userRole, ['medico', 'administrador']);

// Si es staff, obtener lista de pacientes para seleccionar
$listaPacientes = [];
if ($isStaff) {
    $stmtPacientes = $conexion->prepare('SELECT id_pacientes, nombre_completo FROM pacientes WHERE estado = "Activo" ORDER BY nombre_completo ASC');
    $stmtPacientes->execute();
    $resultPacientes = $stmtPacientes->get_result();
    while ($row = $resultPacientes->fetch_assoc()) {
        $listaPacientes[] = $row;
    }
    $stmtPacientes->close();
}

// Obtener id_pacientes desde la tabla pacientes usando id_usuarios
$pacienteId = null;
$pacienteNombre = '';
if (!$isStaff) {
    $stmtPaciente = $conexion->prepare('SELECT id_pacientes, nombre_completo FROM pacientes WHERE id_usuarios = ? LIMIT 1');
    $stmtPaciente->bind_param('i', $userId);
    $stmtPaciente->execute();
    $resultPaciente = $stmtPaciente->get_result();
    if ($rowPaciente = $resultPaciente->fetch_assoc()) {
        $pacienteId = intval($rowPaciente['id_pacientes']);
        $pacienteNombre = $rowPaciente['nombre_completo'];
    }
    $stmtPaciente->close();
}

// Si es staff, permitir seleccionar paciente vía ?id_pacientes= o POST
$requestedPacienteId = isset($_GET['id_pacientes']) ? intval($_GET['id_pacientes']) : null;
if ($isStaff && $requestedPacienteId) {
    $pacienteId = $requestedPacienteId;
    // Obtener nombre del paciente seleccionado
    $stmtNombre = $conexion->prepare('SELECT nombre_completo FROM pacientes WHERE id_pacientes = ? LIMIT 1');
    $stmtNombre->bind_param('i', $pacienteId);
    $stmtNombre->execute();
    $resultNombre = $stmtNombre->get_result();
    if ($rowNombre = $resultNombre->fetch_assoc()) {
        $pacienteNombre = $rowNombre['nombre_completo'];
    }
    $stmtNombre->close();
}

// Crear carpeta de destino si no existe
$uploadDir = __DIR__ . '/uploads/examenes/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        die('No se pudo crear el directorio de subida.');
    }
}

// Límite de tamaño: 10MB
$MAX_SIZE_BYTES = 10 * 1024 * 1024;

// Asegurar tabla examenes si no existe
$ensureTableSql = "CREATE TABLE IF NOT EXISTS examenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pacientes INT NOT NULL,
    nombre_paciente VARCHAR(255) NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    descripcion_paciente VARCHAR(50) NOT NULL,
    tamano INT NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (id_pacientes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    $conexion->query($ensureTableSql);
} catch (Exception $e) {
    // Continuar sin abortar
}

$errors = [];
$success = null;

// Solo pacientes pueden subir exámenes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['examen_pdf']) && !$isStaff) {
    $file = $_FILES['examen_pdf'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Error en la subida del archivo. Código: ' . $file['error'];
    } else {
        if ($file['size'] <= 0 || $file['size'] > $MAX_SIZE_BYTES) {
            $errors[] = 'El archivo debe ser mayor a 0 bytes y no exceder 10MB.';
        }

        // Validar tipo por extensión y por MIME
        $originalName = $file['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $errors[] = 'Solo se permiten archivos PDF (.pdf).';
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMimes = ['application/pdf'];
        if (!in_array($mime, $allowedMimes, true)) {
            $fh = fopen($file['tmp_name'], 'rb');
            $magic = $fh ? fread($fh, 4) : '';
            if ($fh) fclose($fh);
            if ($magic !== '%PDF') {
                $errors[] = 'El archivo no parece ser un PDF válido.';
            }
        }

        if (empty($errors)) {
            // Sanitizar nombre y construir destino
            $safeOriginal = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $originalName);
            $timestamp = date('Ymd_His');
            $rand = substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
            $destFileName = 'examen_' . $pacienteId . '_' . $timestamp . '_' . $rand . '.pdf';
            $destPath = $uploadDir . $destFileName;
            $relativePath = 'uploads/examenes/' . $destFileName;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $errors[] = 'No se pudo mover el archivo subido al directorio de destino.';
            } else {
                // Guardar registro en BD
                try {
                    $stmt = $conexion->prepare('INSERT INTO examenes (id_pacientes, nombre_paciente, ruta, tipo, descripcion_paciente, tamano) VALUES (?, ?, ?, ?, ?, ?)');
                    $tipo = 'pdf';
                    $descripcion = 'Examen de laboratorio';
                    $stmt->bind_param('issssi', $pacienteId, $safeOriginal, $relativePath, $tipo, $descripcion, $file['size']);
                    $stmt->execute();
                    $stmt->close();
                    $success = 'Examen subido correctamente.';
                } catch (Exception $e) {
                    $errors[] = 'Subida completada, pero ocurrió un error al guardar en la base de datos.';
                }
            }
        }
    }
}

// Listar examenes del paciente (solo si hay un paciente seleccionado)
$examenes = [];
if ($pacienteId) {
    try {
        $stmt = $conexion->prepare('SELECT id, nombre_paciente, ruta, tipo, descripcion_paciente, tamano, creado_en FROM examenes WHERE id_pacientes = ? ORDER BY creado_en DESC');
        $stmt->bind_param('i', $pacienteId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $examenes[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        // Si la tabla no existe o hay error, examenes quedará vacío
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Exámenes del Paciente</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
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
        .form-label {
            font-weight: 600;
            color: #198754;
        }
        .alert {
            border-radius: 0.375rem;
        }
        .header-section {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            color: white;
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
        .table {
            margin-top: 1rem;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .file-icon {
            font-size: 2rem;
            color: #dc3545;
        }
        .muted {
            color: #6c757d;
            font-size: 0.875rem;
        }
        .preview-pdf {
            width: 100%;
            height: 500px;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header-section">
        <div class="container text-center">
            <div class="medical-icon">
                <i class="bi bi-file-earmark-medical-fill"></i>
            </div>
            <h1>Exámenes del Paciente</h1>
            <p>Gestión de exámenes médicos y resultados de laboratorio</p>
        </div>
    </div>

    <div class="container mb-5">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($isStaff): ?>
            <!-- Selector de paciente para médicos -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="bi bi-people me-2"></i>Seleccionar Paciente</h5>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-8">
                            <label for="pacienteSelect" class="form-label">Paciente</label>
                            <select name="id_pacientes" id="pacienteSelect" class="form-select" onchange="this.form.submit()">
                                <option value="">-- Seleccionar Paciente --</option>
                                <?php foreach ($listaPacientes as $p): ?>
                                    <option value="<?= $p['id_pacientes'] ?>" <?= ($requestedPacienteId == $p['id_pacientes']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($pacienteId && $pacienteNombre): ?>
            <div class="alert alert-info">
                <i class="bi bi-person-fill me-2"></i>
                <strong>Paciente:</strong> <?= htmlspecialchars($pacienteNombre, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (!$isStaff && $pacienteId): ?>
        <!-- Formulario de subida para pacientes -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-upload me-2"></i>Subir Nuevo Examen</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="examen_pdf" class="form-label">
                            <i class="bi bi-file-earmark-pdf me-1"></i>Archivo PDF (máx 10MB)
                        </label>
                        <input type="file" class="form-control" id="examen_pdf" name="examen_pdf" accept="application/pdf" required>
                        <span class="muted">Solo se aceptan archivos .pdf</span>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-upload me-2"></i>Subir Examen
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Listado de exámenes -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-list me-2"></i>Listado de Exámenes</h5>
            </div>
            <div class="card-body">
                <?php if (empty($examenes)): ?>
                    <p class="muted text-center py-4">No hay exámenes registrados para este paciente.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Archivo</th>
                                    <th>Fecha de subida</th>
                                    <th>Tamaño</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($examenes as $ex): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-file-earmark-pdf-fill file-icon me-2"></i>
                                                <div>
                                                    <strong><?= htmlspecialchars($ex['nombre_paciente'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ex['creado_en'])), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= round(((int)$ex['tamano']) / 1024, 1) ?> KB</td>
                                        <td>
                                            <a class="btn btn-primary btn-sm" target="_blank" href="<?= htmlspecialchars($ex['ruta'], ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="bi bi-eye me-1"></i>Ver
                                            </a>
                                            <a class="btn btn-secondary btn-sm" target="_blank" href="<?= htmlspecialchars($ex['ruta'], ENT_QUOTES, 'UTF-8') ?>" download="<?= htmlspecialchars($ex['nombre_paciente'], ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="bi bi-download me-1"></i>Descargar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Visor de PDF -->
        <?php if (!empty($examenes)): ?>
        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-file-earmark-pdf me-2"></i>Visor de Exámenes</h5>
            </div>
            <div class="card-body">
                <embed src="<?= htmlspecialchars($examenes[0]['ruta'], ENT_QUOTES, 'UTF-8') ?>" type="application/pdf" class="preview-pdf">
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
