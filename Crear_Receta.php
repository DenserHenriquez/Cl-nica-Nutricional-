<?php
// Crear_Receta.php
// Formulario para crear recetas asociadas a un paciente

require_once __DIR__ . '/db_connection.php';
session_start();

// Verificar sesión de usuario
if (!isset($_SESSION['id_usuarios'])) {
    header('Location: index.php');
    exit;
}

$user_id = (int)$_SESSION['id_usuarios'];
// Detectar rol y privilegios (Médico/Admin pueden usar esta página sin estar en pacientes)
$role = $_SESSION['rol'] ?? null;
if ($role === null) {
    if ($stmtRole = $conexion->prepare("SELECT Rol FROM usuarios WHERE id_usuarios = ? LIMIT 1")) {
        $stmtRole->bind_param('i', $user_id);
        $stmtRole->execute();
        $stmtRole->bind_result($dbRole);
        if ($stmtRole->fetch() && $dbRole) {
            $role = $dbRole;
            $_SESSION['rol'] = $dbRole;
        }
        $stmtRole->close();
    }
}
$isPrivileged = ($role === 'Medico' || $role === 'Administrador');

// Obtener id del paciente desde la BD usando id_usuarios (solo si NO es privilegiado)
$paciente_id = 0;
$noRegistrado = false;
if (!$isPrivileged) {
    $stmt = $conexion->prepare("SELECT id_pacientes FROM pacientes WHERE id_usuarios = ? LIMIT 1");
    if (!$stmt) {
        die("Error preparing statement: " . $conexion->error);
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $paciente_id = (int)$row['id_pacientes'];
    } else {
        // Paciente no registrado: mostrar aviso y ocultar formulario (no redirigir)
        $paciente_id = 0;
        $noRegistrado = true;
    }
    $stmt->close();
}

// Si es privilegiado, cargar lista de pacientes para seleccionar
$pacientesList = [];
if ($isPrivileged) {
    $resPac = $conexion->query("SELECT id_pacientes, nombre_completo FROM pacientes ORDER BY nombre_completo ASC");
    if ($resPac) {
        while ($r = $resPac->fetch_assoc()) {
            $pacientesList[] = [
                'id' => (int)$r['id_pacientes'],
                'nombre' => (string)$r['nombre_completo']
            ];
        }
        $resPac->close();
    }
}

// Inicializar mensajes
$errores = [];
$exito = '';

// Configuración de subida
$uploadDir = __DIR__ . '/assets/images/recetas';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        $errores[] = 'No se pudo crear el directorio para subir imágenes.';
    }
}

// Crear tabla si no existe
$conexion->query("CREATE TABLE IF NOT EXISTS recetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pacientes INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    ingredientes TEXT NOT NULL,
    porciones INT DEFAULT NULL,
    instrucciones TEXT,
    nota_nutricional TEXT,
    foto_path VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_paciente (id_pacientes),
    CONSTRAINT fk_recetas_paciente FOREIGN KEY (id_pacientes) REFERENCES pacientes(id_pacientes) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Manejo de POST (solo permitir si no está en estado no-registrado o si es privilegiado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !($noRegistrado && !$isPrivileged)) {
    // Validar token CSRF simple
    if (!isset($_POST['csrf']) || !isset($_SESSION['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $errores[] = 'Token inválido. Recargue la página.';
    }

    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $ingredientes = isset($_POST['ingredientes']) ? trim($_POST['ingredientes']) : '';
    $porciones = isset($_POST['porciones']) ? (int)$_POST['porciones'] : null;
    $instrucciones = isset($_POST['instrucciones']) ? trim($_POST['instrucciones']) : '';
    $nota_nutricional = isset($_POST['nota_nutricional']) ? trim($_POST['nota_nutricional']) : '';

    // Validaciones
    if (empty($nombre)) $errores[] = 'El nombre de la receta es obligatorio.';
    if (empty($ingredientes)) $errores[] = 'Los ingredientes son obligatorios.';
    if ($porciones !== null && $porciones <= 0) $errores[] = 'Las porciones deben ser un número positivo.';

    // Validar imagen (opcional)
    $fotoPath = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['foto'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errores[] = 'Error al subir la imagen.';
        } else {
            $maxSize = 3 * 1024 * 1024; // 3MB
            if ($file['size'] > $maxSize) {
                $errores[] = 'La imagen excede el tamaño máximo (3MB).';
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (strpos($mime, 'image/') !== 0) {
                $errores[] = 'El archivo debe ser una imagen.';
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext === '') {
                    $type = substr($mime, 6);
                    $ext = preg_replace('/[^a-z0-9]+/i', '', $type);
                    if ($ext === '') { $ext = 'img'; }
                }
                $basename = 'receta_' . $paciente_id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadDir . '/' . $basename;
                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    $errores[] = 'No se pudo guardar la imagen en el servidor.';
                } else {
                    $fotoPath = 'assets/images/recetas/' . $basename;
                }
            }
        }
    }

    // Determinar paciente destino
    $targetPacienteId = $paciente_id;
    $targetPacienteIds = [$targetPacienteId];
    if ($isPrivileged) {
        $targetPacienteIds = isset($_POST['id_pacientes']) && is_array($_POST['id_pacientes']) ? array_map('intval', $_POST['id_pacientes']) : [];
        // Limpiar duplicados y ceros
        $targetPacienteIds = array_values(array_filter(array_unique($targetPacienteIds), function($v){ return $v > 0; }));
        if (count($targetPacienteIds) === 0) {
            $errores[] = 'Debe seleccionar al menos un paciente.';
        } else {
            // Verificar existencia de pacientes seleccionados
            $placeholders = implode(',', array_fill(0, count($targetPacienteIds), '?'));
            $typesChk = str_repeat('i', count($targetPacienteIds));
            $sqlChk = "SELECT id_pacientes FROM pacientes WHERE id_pacientes IN ($placeholders)";
            if ($stmtChk = $conexion->prepare($sqlChk)) {
                $stmtChk->bind_param($typesChk, ...$targetPacienteIds);
                $stmtChk->execute();
                $resultChk = $stmtChk->get_result();
                $found = [];
                while ($rw = $resultChk->fetch_assoc()) { $found[] = (int)$rw['id_pacientes']; }
                $stmtChk->close();
                $missing = array_diff($targetPacienteIds, $found);
                if (!empty($missing)) {
                    $errores[] = 'Algunos pacientes seleccionados no existen: ' . implode(', ', $missing);
                }
            }
        }
    }

    if (empty($errores)) {
        $sql = "INSERT INTO recetas (id_pacientes, nombre, ingredientes, porciones, instrucciones, nota_nutricional, foto_path)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = $conexion->prepare($sql)) {
            $okCount = 0; $failCount = 0;
            foreach ($targetPacienteIds as $pidInsert) {
                $stmt->bind_param('ississs', $pidInsert, $nombre, $ingredientes, $porciones, $instrucciones, $nota_nutricional, $fotoPath);
                if ($stmt->execute()) { $okCount++; } else { $failCount++; }
            }
            $stmt->close();
            if ($failCount === 0) {
                $exito = 'Receta guardada correctamente para ' . $okCount . ' paciente' . ($okCount !== 1 ? 's' : '') . '.';
            } else {
                $errores[] = 'Guardadas ' . $okCount . ', errores en ' . $failCount . ' inserción(es).';
            }
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

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Crear Receta</title>
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
        .bg-primary-custom {
            background-color: #198754 !important;
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
        .muted {
            color: #6c757d;
            font-size: 0.875rem;
        }
        .preview {
            max-height: 150px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header-section">
        <div class="container text-center">
            <div class="medical-icon">
                <i class="bi bi-journal-plus"></i>
            </div>
            <h1>Crear Receta</h1>
            <p>
                <?php if ($isPrivileged): ?>
                    Seleccione un paciente para crear la receta.
                <?php else: ?>
                    Paciente #<?= (int)$paciente_id ?> | Crea una nueva receta nutricional.
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="container mb-5">
        <?php if (!empty($noRegistrado) && isset($_SESSION['rol']) && $_SESSION['rol'] === 'Paciente'): ?>
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Paciente nuevo: primero necesitas actualizar tus datos con tu médico tratante. Si aún no estás registrado como paciente en la clínica, ponte en contacto con el personal o tu médico para completar tu registro.
            </div>
        <?php endif; ?>

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

        <?php if (!(!empty($noRegistrado) && isset($_SESSION['rol']) && $_SESSION['rol'] === 'Paciente')): ?>
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-plus-circle me-2"></i>Nueva Receta</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                    <?php if ($isPrivileged): ?>
                    <div class="mb-3">
                        <label class="form-label d-flex align-items-center gap-1">
                            <i class="bi bi-people"></i>
                            Seleccione pacientes para enviar la receta
                        </label>
                        <div class="d-flex flex-wrap gap-2 mb-2" id="selectedPatientsSummary"></div>
                        <button type="button" class="btn btn-outline-success btn-sm" id="openPatientsModal">
                            <i class="bi bi-list-check me-1"></i> Elegir Pacientes
                        </button>
                        <div class="form-text">Puede elegir uno o varios pacientes. Se creará una copia de la receta por cada paciente seleccionado.</div>
                        <!-- Contenedor donde se inyectarán inputs ocultos id_pacientes[] -->
                        <div id="hiddenPatientsInputs"></div>
                    </div>
                    <?php endif; ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label">
                                <i class="bi bi-tag me-1"></i>Nombre de la receta
                            </label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="col-md-6">
                            <label for="porciones" class="form-label">
                                <i class="bi bi-hash me-1"></i>Porciones
                            </label>
                            <input type="number" class="form-control" id="porciones" name="porciones" min="1" placeholder="Ej: 4">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="ingredientes" class="form-label">
                            <i class="bi bi-list-check me-1"></i>Ingredientes
                        </label>
                        <textarea class="form-control" id="ingredientes" name="ingredientes" rows="4" placeholder="Lista de ingredientes separados por comas o líneas" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="instrucciones" class="form-label">
                            <i class="bi bi-book me-1"></i>Instrucciones de preparación
                        </label>
                        <textarea class="form-control" id="instrucciones" name="instrucciones" rows="4" placeholder="Pasos para preparar la receta"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="nota_nutricional" class="form-label">
                            <i class="bi bi-info-circle me-1"></i>Nota nutricional
                        </label>
                        <textarea class="form-control" id="nota_nutricional" name="nota_nutricional" rows="3" placeholder="Información nutricional, calorías, etc."></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="foto" class="form-label">
                            <i class="bi bi-camera me-1"></i>Foto de la receta (opcional)
                        </label>
                        <input type="file" class="form-control" id="foto" name="foto" accept="image/*" onchange="previewImage(event)">
                        <div class="form-text">Formatos: cualquier imagen. Máx 3MB.</div>
                        <div id="imagePreview" class="mt-3" style="display: none;">
                            <img id="previewImg" class="preview" alt="Vista previa" />
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save me-2"></i>Guardar Receta
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

        <?php if ($isPrivileged): ?>
        <!-- Modal Selección de Pacientes -->
        <div class="modal fade" id="patientsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-people me-2"></i>Seleccionar Pacientes</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <input type="text" class="form-control" id="patientsSearch" placeholder="Buscar por nombre..." />
                        </div>
                        <div class="list-group" id="patientsList" style="max-height:420px; overflow:auto;">
                            <?php foreach ($pacientesList as $p): ?>
                                <label class="list-group-item d-flex align-items-center gap-2" data-name="<?= strtolower(htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8')) ?>">
                                    <input class="form-check-input flex-shrink-0 patient-checkbox" type="checkbox" value="<?= (int)$p['id'] ?>" />
                                    <span class="flex-grow-1">
                                        <?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                        <small class="text-muted">(ID: <?= (int)$p['id'] ?>)</small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="clearSelectionBtn"><i class="bi bi-x-circle me-1"></i>Limpiar</button>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary" id="selectAllBtn"><i class="bi bi-check-all me-1"></i>Todos</button>
                            <button type="button" class="btn btn-success" id="confirmPatientsBtn"><i class="bi bi-save me-1"></i>Confirmar Selección</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

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

        <?php if ($isPrivileged): ?>
        // --- Gestión modal selección de pacientes ---
        const openBtn = document.getElementById('openPatientsModal');
        const patientsModalEl = document.getElementById('patientsModal');
        const searchInput = document.getElementById('patientsSearch');
        const listEl = document.getElementById('patientsList');
        const confirmBtn = document.getElementById('confirmPatientsBtn');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const clearBtn = document.getElementById('clearSelectionBtn');
        const summaryEl = document.getElementById('selectedPatientsSummary');
        const hiddenInputsEl = document.getElementById('hiddenPatientsInputs');
        let patientsModal;

        if (openBtn) {
            openBtn.addEventListener('click', () => {
                if (!patientsModal) patientsModal = new bootstrap.Modal(patientsModalEl);
                patientsModal.show();
            });
        }

        // Búsqueda en vivo
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                const q = searchInput.value.trim().toLowerCase();
                listEl.querySelectorAll('.list-group-item').forEach(item => {
                    const name = item.getAttribute('data-name');
                    item.style.display = name.includes(q) ? '' : 'none';
                });
            });
        }

        // Seleccionar todos
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', () => {
                listEl.querySelectorAll('.patient-checkbox').forEach(cb => { cb.checked = true; });
            });
        }

        // Limpiar selección
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                listEl.querySelectorAll('.patient-checkbox').forEach(cb => { cb.checked = false; });
                updateSummary([]);
                hiddenInputsEl.innerHTML = '';
            });
        }

        // Confirmar
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => {
                const ids = []; const names = [];
                listEl.querySelectorAll('.patient-checkbox:checked').forEach(cb => {
                    ids.push(cb.value);
                    const label = cb.closest('.list-group-item');
                    names.push(label.querySelector('span').textContent.trim());
                });
                // Actualizar hidden inputs
                hiddenInputsEl.innerHTML = '';
                ids.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'id_pacientes[]';
                    input.value = id;
                    hiddenInputsEl.appendChild(input);
                });
                updateSummary(names);
                if (patientsModal) patientsModal.hide();
            });
        }

        function updateSummary(names) {
            summaryEl.innerHTML = '';
            if (!names.length) {
                summaryEl.innerHTML = '<span class="text-muted">Sin pacientes seleccionados.</span>';
                return;
            }
            names.forEach(n => {
                const pill = document.createElement('span');
                pill.className = 'badge bg-success d-inline-flex align-items-center gap-1';
                pill.textContent = n;
                summaryEl.appendChild(pill);
            });
        }
        // Inicializar si venimos de un POST (persistencia)
        <?php if (isset($_POST['id_pacientes']) && is_array($_POST['id_pacientes'])): ?>
            updateSummary(<?php
                $persistNames = [];
                foreach ($pacientesList as $p) {
                    if (in_array($p['id'], array_map('intval', $_POST['id_pacientes']), true)) {
                        $persistNames[] = htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') . ' (ID: ' . (int)$p['id'] . ')';
                        echo "\n"; // just spacing
                    }
                }
                echo json_encode($persistNames, JSON_UNESCAPED_UNICODE);
            ?>);
        <?php else: ?>
            updateSummary([]);
        <?php endif; ?>
        <?php endif; ?>
    </script>
</body>
</html>
