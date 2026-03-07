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
    categoria VARCHAR(100) NOT NULL DEFAULT 'Otros / Especializados',
    nombre_examen VARCHAR(255) NOT NULL DEFAULT '',
    fecha_examen DATE NOT NULL DEFAULT CURRENT_DATE,
    notas TEXT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (id_pacientes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Añadir columna categoria si no existe (verificar primero si la columna exista)
try {
    $resultCheck = $conexion->query("SHOW COLUMNS FROM examenes LIKE 'categoria'");
    if ($resultCheck && $resultCheck->num_rows === 0) {
        $conexion->query("ALTER TABLE examenes ADD COLUMN categoria VARCHAR(100) NOT NULL DEFAULT 'Otros / Especializados'");
    }
    // nombre_examen
    $resultCheck = $conexion->query("SHOW COLUMNS FROM examenes LIKE 'nombre_examen'");
    if ($resultCheck && $resultCheck->num_rows === 0) {
        $conexion->query("ALTER TABLE examenes ADD COLUMN nombre_examen VARCHAR(255) NOT NULL DEFAULT '' AFTER categoria");
    }
    // fecha_examen
    $resultCheck = $conexion->query("SHOW COLUMNS FROM examenes LIKE 'fecha_examen'");
    if ($resultCheck && $resultCheck->num_rows === 0) {
        $conexion->query("ALTER TABLE examenes ADD COLUMN fecha_examen DATE NOT NULL DEFAULT CURRENT_DATE AFTER nombre_examen");
    }
    // notas
    $resultCheck = $conexion->query("SHOW COLUMNS FROM examenes LIKE 'notas'");
    if ($resultCheck && $resultCheck->num_rows === 0) {
        $conexion->query("ALTER TABLE examenes ADD COLUMN notas TEXT NULL AFTER fecha_examen");
    }
} catch (Exception $e) {
    // Continuar sin abortar si ya existan las columnas
}

try {
    $conexion->query($ensureTableSql);
} catch (Exception $e) {
    // Continuar sin abortar
}

$errors = [];
$success = null;

// Solo pacientes pueden subir o editar exámenes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isStaff) {
    $isEditing = isset($_POST['exam_id']) && intval($_POST['exam_id']) > 0;
    $editingId = $isEditing ? intval($_POST['exam_id']) : null;

    // Validar campos comunes
    $categoria = isset($_POST['categoria_examen']) ? trim($_POST['categoria_examen']) : '';
    $categoriasValidas = ['Perfil Metabólico', 'Nutrientes y Sangre', 'Función Orgánica', 'Composición Corporal', 'Otros / Especializados'];
    if (empty($categoria)) {
        $errors[] = 'Debe seleccionar una categoría de examen.';
    } elseif (!in_array($categoria, $categoriasValidas)) {
        $errors[] = 'La categoría de examen seleccionada no es válida.';
    }

    $nombreExamen = isset($_POST['nombre_examen']) ? trim($_POST['nombre_examen']) : '';
    if (empty($nombreExamen)) {
        $errors[] = 'Debe ingresar un nombre para el examen.';
    }

    $fechaExamen = isset($_POST['fecha_examen']) ? trim($_POST['fecha_examen']) : '';
    if (empty($fechaExamen)) {
        $errors[] = 'Debe seleccionar la fecha del examen.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaExamen)) {
        $errors[] = 'La fecha del examen no tiene el formato válido (AAAA-MM-DD).';
    }

    $notas = isset($_POST['notas']) ? trim($_POST['notas']) : '';

    // si estamos editando, no es obligatorio re-subir archivo
    $file = $_FILES['examen_pdf'] ?? null;
    $hasNewFile = $file && $file['error'] !== UPLOAD_ERR_NO_FILE;

    if ($hasNewFile) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error en la subida del archivo. Código: ' . $file['error'];
        } else {
            if ($file['size'] <= 0 || $file['size'] > $MAX_SIZE_BYTES) {
                $errors[] = 'El archivo debe ser mayor a 0 bytes y no exceder 10MB.';
            }
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
        }
    }

    if (empty($errors)) {
        if ($isEditing) {
            // verificar propiedad del examen
            $stmtCheck = $conexion->prepare('SELECT ruta, id_pacientes FROM examenes WHERE id = ? LIMIT 1');
            $stmtCheck->bind_param('i', $editingId);
            $stmtCheck->execute();
            $res = $stmtCheck->get_result();
            $row = $res->fetch_assoc();
            $stmtCheck->close();
            if (!$row || intval($row['id_pacientes']) !== $pacienteId) {
                $errors[] = 'No puedes editar ese examen.';
            } else {
                $updateFields = 'categoria=?, nombre_examen=?, fecha_examen=?, notas=?';
                $params = [$categoria, $nombreExamen, $fechaExamen, $notas];
                $types = 'ssss';
                if ($hasNewFile) {
                    // mover archivo y actualizar ruta/tamano
                    $safeOriginal = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $originalName);
                    $timestamp = date('Ymd_His');
                    $rand = substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
                    $destFileName = 'examen_' . $pacienteId . '_' . $timestamp . '_' . $rand . '.pdf';
                    $destPath = $uploadDir . $destFileName;
                    $relativePath = 'uploads/examenes/' . $destFileName;
                    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                        $errors[] = 'No se pudo mover el archivo subido.';
                    } else {
                        $updateFields .= ', ruta=?, tamano=?';
                        $types .= 'si';
                        $params[] = $relativePath;
                        $params[] = $file['size'];
                        // opcional: borrar archivo viejo
                        if (!empty($row['ruta']) && is_file(__DIR__ . '/' . $row['ruta'])) {
                            @unlink(__DIR__ . '/' . $row['ruta']);
                        }
                    }
                }
                $sql = 'UPDATE examenes SET ' . $updateFields . ' WHERE id=?';
                $types .= 'i';
                $params[] = $editingId;
                $stmtUpd = $conexion->prepare($sql);
                if ($stmtUpd) {
                    $stmtUpd->bind_param($types, ...$params);
                    if ($stmtUpd->execute()) {
                        $success = 'Examen actualizado correctamente.';
                    } else {
                        $errors[] = 'Error al actualizar examen.';
                    }
                    $stmtUpd->close();
                }
            }
        } else {
            // nuevo registro: mover archivo y guardar
            $safeOriginal = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $originalName);
            $timestamp = date('Ymd_His');
            $rand = substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
            $destFileName = 'examen_' . $pacienteId . '_' . $timestamp . '_' . $rand . '.pdf';
            $destPath = $uploadDir . $destFileName;
            $relativePath = 'uploads/examenes/' . $destFileName;
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $errors[] = 'No se pudo mover el archivo subido al directorio de destino.';
            } else {
                try {
                    $stmt = $conexion->prepare('INSERT INTO examenes (id_pacientes, nombre_paciente, ruta, tipo, descripcion_paciente, tamano, categoria, nombre_examen, fecha_examen, notas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $tipo = 'pdf';
                    $descripcion = 'Examen de laboratorio';
                    $fileSize = $file['size'];
                    $stmt->bind_param('issssissss', $pacienteId, $safeOriginal, $relativePath, $tipo, $descripcion, $fileSize, $categoria, $nombreExamen, $fechaExamen, $notas);
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

// Borra examen si se solicitó
if (isset($_GET['delete']) && $pacienteId) {
    $delId = intval($_GET['delete']);
    $stmtDel = $conexion->prepare('DELETE FROM examenes WHERE id = ? AND id_pacientes = ?');
    if ($stmtDel) {
        $stmtDel->bind_param('ii', $delId, $pacienteId);
        $stmtDel->execute();
        $stmtDel->close();
    }
    header('Location: Examenes.php?id_pacientes=' . $pacienteId);
    exit;
}

// Listar examenes del paciente (solo si hay un paciente seleccionado)
$examenes = [];
if ($pacienteId) {
    try {
        $stmt = $conexion->prepare('SELECT id, nombre_paciente, ruta, tipo, descripcion_paciente, tamano, categoria, nombre_examen, fecha_examen, notas, creado_en FROM examenes WHERE id_pacientes = ? ORDER BY creado_en DESC');
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
        /* category badges */
        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .category-perfil { background-color: #fdd835; color: #000; }
        .category-nutrientes { background-color: #d32f2f; color: #fff; }
        .category-funcion { background-color: #1976d2; color: #fff; }
        .category-composicion { background-color: #6a1b9a; color: #fff; }
        .category-otros { background-color: #616161; color: #fff; }
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
        .upload-zone {
            cursor: pointer;
        }
        .upload-zone:hover {
            background-color: #f1f1f1;
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
                <form method="post" enctype="multipart/form-data" class="row g-3" id="uploadForm">
                    <input type="hidden" id="exam_id" name="exam_id" value="">
                    <div class="col-md-4">
                        <label for="categoria_examen" class="form-label">
                            <i class="bi bi-tags me-1"></i>Categoría de Examen *
                        </label>
                        <select class="form-select" id="categoria_examen" name="categoria_examen" required>
                            <option value="">-- Seleccionar Categoría --</option>
                            <option value="Perfil Metabólico">Perfil Metabólico</option>
                            <option value="Nutrientes y Sangre">Nutrientes y Sangre</option>
                            <option value="Función Orgánica">Función Orgánica</option>
                            <option value="Composición Corporal">Composición Corporal</option>
                            <option value="Otros / Especializados">Otros / Especializados</option>
                        </select>
                        <span class="muted">Seleccione la categoría del examen</span>
                    </div>
                    <div class="col-md-4">
                        <label for="nombre_examen" class="form-label">
                            <i class="bi bi-card-text me-1"></i>Nombre del Examen *
                        </label>
                        <input type="text" class="form-control" id="nombre_examen" name="nombre_examen" required>
                    </div>
                    <div class="col-md-2">
                        <label for="fecha_examen" class="form-label">
                            <i class="bi bi-calendar3 me-1"></i>Fecha *
                        </label>
                        <input type="date" class="form-control" id="fecha_examen" name="fecha_examen" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label for="notas" class="form-label">
                            <i class="bi bi-journal-text me-1"></i>Notas (opcional)
                        </label>
                        <textarea class="form-control" id="notas" name="notas" rows="1"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">
                            <i class="bi bi-folder2-open me-1"></i>Cargar Archivo
                        </label>
                        <div class="border rounded upload-zone position-relative text-center py-5" id="uploadZone">
                            <div class="text-muted">
                                <i class="bi bi-cloud-upload fs-1"></i>
                                <p class="mt-2 mb-0">Arrastre y suelte su archivo o haga clic para examinar</p>
                            </div>
                            <input type="file" class="form-control position-absolute top-0 start-0 w-100 h-100 opacity-0" id="examen_pdf" name="examen_pdf" accept="application/pdf" required>
                        </div>
                        <span class="muted">Solo se aceptan archivos .pdf (máx 10MB)</span>
                    </div>
                    <div class="col-12 d-grid">
                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                            <i class="bi bi-plus-circle me-2"></i>Añadir a la Lista
                        </button>
                    </div>
                    <div class="col-12 d-grid" id="cancelWrapper" style="display:none; margin-top:10px;">
                        <button type="button" class="btn btn-secondary" id="cancelEdit">Cancelar edición</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Listado de exámenes -->
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-list me-2"></i>Listado de Exámenes</h5>
                <div style="width: 300px;">
                    <input type="text" id="searchExam" class="form-control form-control-sm" placeholder="Buscar examen..." style="background-color: rgba(255,255,255,0.9); color: #000;">
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($examenes)): ?>
                    <p class="muted text-center py-4">No hay exámenes registrados para este paciente.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Categoría</th>
                                    <th>Nombre del Examen</th>
                                    <th>Fecha</th>
                                    <th>Notas</th>
                                    <th>Archivo</th>
                                    <th>Tamaño</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="examTable">
                                <?php foreach ($examenes as $ex): ?>
                                    <?php
                                        // determine badge class (no icons)
                                        $cat = $ex['categoria'] ?? 'Otros / Especializados';
                                        $catClass = '';
                                        switch ($cat) {
                                            case 'Perfil Metabólico':
                                                $catClass = 'category-perfil';
                                                break;
                                            case 'Nutrientes y Sangre':
                                                $catClass = 'category-nutrientes';
                                                break;
                                            case 'Función Orgánica':
                                                $catClass = 'category-funcion';
                                                break;
                                            case 'Composición Corporal':
                                                $catClass = 'category-composicion';
                                                break;
                                            default:
                                                $catClass = 'category-otros';
                                        }
                                    ?>
                                    <tr data-id="<?= (int)$ex['id'] ?>" data-cat="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars($ex['nombre_examen'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-date="<?= htmlspecialchars($ex['fecha_examen'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-notas="<?= htmlspecialchars($ex['notas'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                        <td>
                                            <span class="category-badge <?= $catClass ?>">
                                                <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($ex['nombre_examen'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($ex['fecha_examen'] ?? $ex['creado_en'])), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= nl2br(htmlspecialchars($ex['notas'] ?? '', ENT_QUOTES, 'UTF-8')) ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-file-earmark-pdf-fill file-icon me-2"></i>
                                                <div>
                                                    <strong><?= htmlspecialchars($ex['nombre_paciente'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= round(((int)$ex['tamano']) / 1024, 1) ?> KB</td>
                                        <td>
                                            <button type="button" class="btn btn-outline-primary btn-sm btn-edit" data-id="<?= (int)$ex['id'] ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="?id_pacientes=<?= $pacienteId ?>&delete=<?= (int)$ex['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Eliminar este examen?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                            <a class="btn btn-primary btn-sm" target="_blank" href="<?= htmlspecialchars($ex['ruta'], ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="bi bi-eye me-1"></i>
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
    <script>
        // zona de arrastre para carga de archivo
        const zone = document.getElementById('uploadZone');
        if (zone) {
            zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('bg-light'); });
            zone.addEventListener('dragleave', e => { zone.classList.remove('bg-light'); });
            zone.addEventListener('drop', e => { zone.classList.remove('bg-light'); });
            // hacer clic redirige al input
            zone.addEventListener('click', () => {
                const inp = document.getElementById('examen_pdf');
                if (inp) inp.click();
            });
        }

        // búsqueda/filtrado en tabla
        const searchInput = document.getElementById('searchExam');
        const table = document.getElementById('examTable');
        if (searchInput && table) {
            searchInput.addEventListener('keyup', () => {
                const filter = searchInput.value.toLowerCase();
                const rows = table.querySelectorAll('tr');
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(filter) ? '' : 'none';
                });
            });
        }
    </script>
</body>
</html>
