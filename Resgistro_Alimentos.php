<?php
// Registro_Alimentos.php
// Funcionalidades:
// - Formulario para registrar comidas diarias (desayuno, almuerzo, cena y snacks)
// - Subida de fotos de los platos (validación de tipo y tamaño)
// - Guardado en BD vinculado al paciente (id_pacientes)
// - Validaciones de campos
// - Historial diario o semanal filtrable por fecha

require_once __DIR__. '/db_connection.php';
session_start();

// Verificar sesión de usuario
if (!isset($_SESSION['id_usuarios'])) {
    header('Location: index.php');
    exit;
}

$user_id = (int)$_SESSION['id_usuarios'];

// Obtener id_pacientes desde la BD usando id_usuarios
$stmt = $conexion->prepare("SELECT id_pacientes FROM pacientes WHERE id_usuarios = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$noRegistrado = false;
$userRole = $_SESSION['rol'] ?? 'Paciente';
$isStaff = in_array($userRole, ['Medico', 'Administrador'], true);

if ($row = $result->fetch_assoc()) {
    $paciente_id = (int)$row['id_pacientes'];
} else {
    // Si el usuario es un Paciente que aún no está registrado como paciente en la tabla,
    // mostramos un aviso en la interfaz en lugar de redirigir inmediatamente.
    if ($userRole === 'Paciente') {
        $paciente_id = 0;
        $noRegistrado = true;
    } else {
        // Médico/Administrador no necesitan registro de paciente; acceso completo sin restricciones
        $paciente_id = 0;
        $noRegistrado = false;
    }
}
$stmt->close();

// Configuración de subida
$uploadDir = __DIR__ . '/assets/images/alimentos';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        $errores[] = 'No se pudo crear el directorio para subir imágenes.';
    }
}
$errores = [];
$exito = '';

// Crear tabla si no existe (defensivo)
// Tabla sugerida: alimentos_registro
// columnas: id (PK), id_pacientes (FK -> pacientes.id_pacientes), fecha (DATE),
// tipo_comida (ENUM o VARCHAR: desayuno|almuerzo|cena|snack), descripcion (TEXT),
// hora (TIME), foto_path (VARCHAR), created_at (DATETIME)
$conexion->query("CREATE TABLE IF NOT EXISTS alimentos_registro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pacientes INT NOT NULL,
    fecha DATE NOT NULL,
    tipo_comida VARCHAR(20) NOT NULL,
    descripcion TEXT NOT NULL,
    hora TIME NOT NULL,
    foto_path VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_paciente_fecha (id_pacientes, fecha),
    CONSTRAINT fk_alimentos_paciente FOREIGN KEY (id_pacientes) REFERENCES pacientes(id_pacientes) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Manejo de POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF simple
    if (!isset($_POST['csrf']) || !isset($_SESSION['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $errores[] = 'Token inválido. Recargue la página.';
    }

    // Manejo de eliminación
    if (isset($_POST['delete_id']) && is_numeric($_POST['delete_id'])) {
        $delete_id = (int)$_POST['delete_id'];

        // Verificar que el registro pertenece al paciente
        $stmtCheck = $conexion->prepare("SELECT foto_path FROM alimentos_registro WHERE id = ? AND id_pacientes = ? LIMIT 1");
        $stmtCheck->bind_param('ii', $delete_id, $paciente_id);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        if ($rowCheck = $resultCheck->fetch_assoc()) {
            // Eliminar archivo si existe
            if (!empty($rowCheck['foto_path'])) {
                $filePath = __DIR__ . '/' . $rowCheck['foto_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            // Eliminar de BD
            $stmtDelete = $conexion->prepare("DELETE FROM alimentos_registro WHERE id = ? AND id_pacientes = ?");
            $stmtDelete->bind_param('ii', $delete_id, $paciente_id);
            if ($stmtDelete->execute()) {
                $exito = 'Registro eliminado correctamente.';
            } else {
                $errores[] = 'Error al eliminar el registro.';
            }
            $stmtDelete->close();
        } else {
            $errores[] = 'Registro no encontrado o no autorizado.';
        }
        $stmtCheck->close();
    }

    $fecha = isset($_POST['fecha']) ? trim($_POST['fecha']) : '';
    $tipo  = isset($_POST['tipo_comida']) ? trim($_POST['tipo_comida']) : '';
    $desc  = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
    $hora  = isset($_POST['hora']) ? trim($_POST['hora']) : '';

    // Validaciones básicas
    if ($fecha === '') $errores[] = 'La fecha es obligatoria';
    if ($hora === '') $errores[] = 'La hora es obligatoria';
    $tiposPermitidos = ['desayuno','almuerzo','cena','snack'];
    if (!in_array($tipo, $tiposPermitidos, true)) $errores[] = 'Tipo de comida inválido';
    if ($desc === '') $errores[] = 'La descripción es obligatoria';

    // Validar imagen (opcional). Si viene, validar tipo y tamaño
    $fotoPath = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['foto'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errores[] = 'Error al subir la imagen.';
        } else {
            // Validar tamaño (por ejemplo máx 3MB)
            $maxSize = 3 * 1024 * 1024; // 3MB
            if ($file['size'] > $maxSize) {
                $errores[] = 'La imagen excede el tamaño máximo (3MB).';
            }
            // Validar MIME: aceptar cualquier imagen (image/*) y mapear extensión
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']) ?: '';
            $ext = null;
            if (strpos($mime, 'image/') !== 0) {
                $errores[] = 'Archivo inválido. Debe ser una imagen.';
            } else {
                // Mapeo de tipos comunes -> extensión
                $map = [
                    'image/jpeg' => 'jpg',
                    'image/pjpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/gif'  => 'gif',
                    'image/webp' => 'webp',
                    'image/bmp'  => 'bmp',
                    'image/x-ms-bmp' => 'bmp',
                    'image/tiff' => 'tiff',
                    'image/svg+xml' => 'svg',
                    'image/x-icon' => 'ico',
                    'image/vnd.microsoft.icon' => 'ico',
                    'image/avif' => 'avif',
                    'image/heic' => 'heic',
                    'image/heif' => 'heif'
                ];
                if (isset($map[$mime])) {
                    $ext = $map[$mime];
                } else {
                    // Fallback: usar la extensión original si existe, sanearla
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if ($ext === '') {
                        $ext = 'img';
                    }
                    $ext = preg_replace('/[^a-z0-9]+/', '', $ext);
                    if ($ext === '') {
                        $ext = 'img';
                    }
                }
            }
            // Si todo ok, mover archivo
            if (empty($errores)) {
                $basename = 'paciente_' . $paciente_id . '' . date('Ymd_His') . '' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadDir . '/' . $basename;
                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    $errores[] = 'No se pudo guardar la imagen en el servidor.';
                } else {
                    // Ruta relativa para guardar en BD
                    $fotoPath = 'assets/images/alimentos/' . $basename;
                }
            }
        }
    }

    if (empty($errores)) {
        $sql = "INSERT INTO alimentos_registro (id_pacientes, fecha, tipo_comida, descripcion, hora, foto_path)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('isssss', $paciente_id, $fecha, $tipo, $desc, $hora, $fotoPath);
            if ($stmt->execute()) {
                $exito = 'Registro guardado correctamente.';
            } else {
                $errores[] = 'Error al guardar en BD: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $errores[] = 'Error preparando consulta: ' . $conexion->error;
        }
    }
}

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

// Filtros de historial
$vista = isset($_GET['vista']) && $_GET['vista'] === 'semanal' ? 'semanal' : 'diaria';
$hoy = date('Y-m-d');
$fechaFiltro = isset($_GET['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha']) ? $_GET['fecha'] : $hoy;

$historial = [];
if ($vista === 'diaria') {
    $sqlH = "SELECT id, fecha, tipo_comida, descripcion, hora, foto_path
             FROM alimentos_registro
             WHERE id_pacientes = ? AND fecha = ?
             ORDER BY hora ASC, id ASC";
    $stmtH = $conexion->prepare($sqlH);
    if ($stmtH) {
        $stmtH->bind_param('is', $paciente_id, $fechaFiltro);
        if ($stmtH->execute()) {
            $res = $stmtH->get_result();
            while ($row = $res->fetch_assoc()) {
                $historial[] = $row;
            }
        }
        $stmtH->close();
    }
} else {
    // semanal: lunes a domingo que contenga fechaFiltro
    $ts = strtotime($fechaFiltro);
    $dow = (int)date('N', $ts); // 1=lunes,7=domingo
    $ini = date('Y-m-d', strtotime('-' . ($dow - 1) . ' days', $ts));
    $fin = date('Y-m-d', strtotime('+' . (7 - $dow) . ' days', $ts));

    $sqlH = "SELECT id, fecha, tipo_comida, descripcion, hora, foto_path
             FROM alimentos_registro
             WHERE id_pacientes = ? AND fecha BETWEEN ? AND ?
             ORDER BY fecha ASC, hora ASC, id ASC";
    $stmtH = $conexion->prepare($sqlH);
    if ($stmtH) {
        $stmtH->bind_param('iss', $paciente_id, $ini, $fin);
        if ($stmtH->execute()) {
            $res = $stmtH->get_result();
            while ($row = $res->fetch_assoc()) {
                $historial[] = $row;
            }
        }
        $stmtH->close();
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Registro de Alimentos</title>
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
        .table {
            margin-top: 1rem;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .preview {
            max-height: 90px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
        }
        .gallery-item {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 0.75rem;
            background: #ffffff;
        }
        .gallery-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 6px;
        }
        .muted {
            color: #6c757d;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header-section">
        <div class="container text-center">
            <div class="medical-icon">
                <i class="bi bi-heart-pulse-fill"></i>
            </div>
            <h1>Registro de Alimentos</h1>
            <p>Paciente #<?= (int)$paciente_id ?> | Registre sus comidas diarias con foto opcional.</p>
        </div>
    </div>

    <div class="container mb-5">
        <?php if (!empty($errores)): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errores as $e): ?>
                        <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($exito, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($noRegistrado) && isset($_SESSION['rol']) && $_SESSION['rol'] === 'Paciente'): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Paciente nuevo: primero necesitas actualizar tus datos con tu médico tratante. Si aún no estás registrado como paciente en la clínica, ponte en contacto con el personal o tu médico para completar tu registro.
            </div>
        <?php endif; ?>

        <?php if (!empty($noRegistrado) && isset($_SESSION['rol']) && $_SESSION['rol'] === 'Paciente'): ?>
            <!-- No mostrar formulario ni historial para usuario Paciente no registrado -->
        <?php else: ?>
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-plus-circle me-2"></i>Nuevo Registro</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="fecha" class="form-label">
                                <i class="bi bi-calendar me-1"></i>Fecha
                            </label>
                            <input type="date" class="form-control" id="fecha" name="fecha" value="<?= htmlspecialchars($hoy, ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="hora" class="form-label">
                                <i class="bi bi-clock me-1"></i>Hora
                            </label>
                            <input type="time" class="form-control" id="hora" name="hora" required>
                        </div>
                        <div class="col-md-4">
                            <label for="tipo_comida" class="form-label">
                                <i class="bi bi-tag me-1"></i>Tipo de comida
                            </label>
                            <select class="form-control" id="tipo_comida" name="tipo_comida" required>
                                <option value="desayuno">Desayuno</option>
                                <option value="almuerzo">Almuerzo</option>
                                <option value="cena">Cena</option>
                                <option value="snack">Snack</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">
                            <i class="bi bi-card-text me-1"></i>Descripción del plato / alimentos
                        </label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3" placeholder="Ej: Ensalada de pollo, arroz integral, agua" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="foto" class="form-label">
                            <i class="bi bi-camera me-1"></i>Foto del plato (opcional)
                        </label>
                        <input type="file" class="form-control" id="foto" name="foto" accept="image/*" onchange="previewImage(event)">
                        <span class="muted">Formatos: cualquier imagen (JPG, PNG, GIF, WEBP, SVG, etc.). Máx 3MB.</span>
                        <div id="imagePreview" style="margin-top: 10px; display: none;">
                            <img id="previewImg" class="preview" alt="Vista previa" />
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save me-2"></i>Guardar Registro
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-list me-2"></i>Historial <?= $vista === 'semanal' ? 'Semanal' : 'Diario' ?></h5>
            </div>
            <div class="card-body">
                <form method="get" class="row mb-3">
                    <input type="hidden" name="id" value="<?= (int)$paciente_id ?>" />
                    <div class="col-md-4">
                        <label for="vista" class="form-label">Vista</label>
                        <select class="form-control" id="vista" name="vista">
                            <option value="diaria" <?= $vista==='diaria'?'selected':'' ?>>Diaria</option>
                            <option value="semanal" <?= $vista==='semanal'?'selected':'' ?>>Semanal</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="fecha_filtro" class="form-label">Fecha base</label>
                        <input type="date" class="form-control" id="fecha_filtro" name="fecha" value="<?= htmlspecialchars($fechaFiltro, ENT_QUOTES, 'UTF-8') ?>" />
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Aplicar</button>
                    </div>
                </form>

                <?php if (empty($historial)): ?>
                    <p class="muted">No hay registros para el periodo seleccionado.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <?php if ($vista==='semanal'): ?><th>Fecha</th><?php endif; ?>
                                    <th>Hora</th>
                                    <th>Tipo</th>
                                    <th>Descripción</th>
                                    <th>Foto</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historial as $row): ?>
                                    <tr>
                                        <?php if ($vista==='semanal'): ?><td><?= htmlspecialchars($row['fecha'], ENT_QUOTES, 'UTF-8') ?></td><?php endif; ?>
                                        <td><?= htmlspecialchars(substr($row['hora'],0,5), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(ucfirst($row['tipo_comida']), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= nl2br(htmlspecialchars($row['descripcion'], ENT_QUOTES, 'UTF-8')) ?></td>
                                        <td>
                                            <?php if (!empty($row['foto_path'])): ?>
                                                <a href="<?= htmlspecialchars($row['foto_path'], ENT_QUOTES, 'UTF-8') ?>" target="_blank">
                                                    <img class="preview" src="<?= htmlspecialchars($row['foto_path'], ENT_QUOTES, 'UTF-8') ?>" alt="foto" />
                                                </a>
                                            <?php else: ?>
                                                <span class="muted">Sin foto</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('¿Está seguro de que desea eliminar este registro?');">
                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="bi bi-trash"></i> Eliminar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-images me-2"></i>Galería de Comidas Registradas</h5>
            </div>
            <div class="card-body">
                <?php
                // Obtener todas las comidas con fotos del paciente
                $sqlGaleria = "SELECT fecha, tipo_comida, descripcion, hora, foto_path
                               FROM alimentos_registro
                               WHERE id_pacientes = ? AND foto_path IS NOT NULL
                               ORDER BY fecha DESC, hora DESC";
                $stmtGaleria = $conexion->prepare($sqlGaleria);
                $galeria = [];
                if ($stmtGaleria) {
                    $stmtGaleria->bind_param('i', $paciente_id);
                    if ($stmtGaleria->execute()) {
                        $resGaleria = $stmtGaleria->get_result();
                        while ($row = $resGaleria->fetch_assoc()) {
                            $galeria[] = $row;
                        }
                    }
                    $stmtGaleria->close();
                }
                ?>

                <?php if (empty($galeria)): ?>
                    <p class="muted">No hay fotos de comidas registradas aún.</p>
                <?php else: ?>
                    <div class="gallery-grid">
                        <?php foreach ($galeria as $item): ?>
                            <div class="gallery-item">
                                <img src="<?= htmlspecialchars($item['foto_path'], ENT_QUOTES, 'UTF-8') ?>" alt="Foto de comida" />
                                <div style="margin-top: 8px;">
                                    <div style="font-weight: 600; color: #198754; text-transform: capitalize;">
                                        <?= htmlspecialchars($item['tipo_comida'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: #6c757d;">
                                        <?= htmlspecialchars($item['fecha'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(substr($item['hora'],0,5), ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: #495057; margin-top: 4px;">
                                        <?= nl2br(htmlspecialchars($item['descripcion'], ENT_QUOTES, 'UTF-8')) ?>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
        <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function previewImage(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');

            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        }
    </script>
</body>
</html>