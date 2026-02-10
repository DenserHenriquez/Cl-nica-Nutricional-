<?php
// Gestion_Recetas.php
// Módulo para buscar, ver, editar y eliminar recetas guardadas

require_once __DIR__ . '/db_connection.php';
session_start();

// Inicializar CSRF temprano para evitar warnings y asegurar disponibilidad
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

// Verificar sesión de usuario
if (!isset($_SESSION['id_usuarios'])) {
    header('Location: index.php');
    exit;
}

$user_id = (int)$_SESSION['id_usuarios'];

// Detectar rol y privilegios (Médico/Administrador)
$role = $_SESSION['rol'] ?? null;
if ($role === null) {
    if ($stmtRole = $conexion->prepare("SELECT Rol FROM usuarios WHERE id_usuarios = ? LIMIT 1")) {
        $stmtRole->bind_param('i', $user_id);
        $stmtRole->execute();
        $stmtRole->bind_result($dbRole);
        if ($stmtRole->fetch()) { $role = $dbRole; $_SESSION['rol'] = $dbRole; }
        $stmtRole->close();
    }
}
$isPrivileged = ($role === 'Medico' || $role === 'Administrador');

// Si NO es privilegiado, validar registro de paciente
$paciente_id = 0;
$noRegistrado = false;
if (!$isPrivileged) {
    $stmt = $conexion->prepare("SELECT id_pacientes FROM pacientes WHERE id_usuarios = ? LIMIT 1");
    if (!$stmt) { die("Error preparing statement: " . $conexion->error); }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $paciente_id = (int)$row['id_pacientes'];
    } else {
        // Paciente no registrado: mostrar aviso y ocultar UI (sin redirección)
        $paciente_id = 0;
        $noRegistrado = true;
    }
    $stmt->close();
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


// Manejo de eliminación
$errores = [];
$exito = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        $errores[] = 'Token inválido.';
    } else {
        $receta_id = isset($_POST['receta_id']) ? (int)$_POST['receta_id'] : 0;
        if ($receta_id <= 0) {
            $errores[] = 'ID de receta inválido.';
        } else {
            // Verificar permisos: solo el paciente o privilegiados
            $sqlCheck = "SELECT id_pacientes FROM recetas WHERE id = ?";
            if ($stmtCheck = $conexion->prepare($sqlCheck)) {
                $stmtCheck->bind_param('i', $receta_id);
                $stmtCheck->execute();
                $resultCheck = $stmtCheck->get_result();
                if ($rowCheck = $resultCheck->fetch_assoc()) {
                    $recetaPacienteId = (int)$rowCheck['id_pacientes'];
                    $canDelete = $isPrivileged || ($paciente_id === $recetaPacienteId);
                    if (!$canDelete) {
                        $errores[] = 'No tienes permisos para eliminar esta receta.';
                    } else {
                        $sqlDel = "DELETE FROM recetas WHERE id = ?";
                        if ($stmtDel = $conexion->prepare($sqlDel)) {
                            $stmtDel->bind_param('i', $receta_id);
                            if ($stmtDel->execute()) {
                                $exito = 'Receta eliminada correctamente.';
                            } else {
                                $errores[] = 'Error al eliminar la receta.';
                            }
                            $stmtDel->close();
                        } else {
                            $errores[] = 'Error preparando consulta de eliminación.';
                        }
                    }
                } else {
                    $errores[] = 'Receta no encontrada.';
                }
                $stmtCheck->close();
            }
        }
    }
}

// Búsqueda (solo ejecutar si no está en estado no-registrado para Paciente)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$recetas = [];
if (!($noRegistrado && !$isPrivileged)) {
    $sql = "SELECT id, nombre, ingredientes, porciones, instrucciones, nota_nutricional, foto_path, created_at FROM recetas WHERE 1=1";
    $params = [];
    $types = '';
    if (!$isPrivileged) {
        $sql .= " AND id_pacientes = ?";
        $params[] = $paciente_id;
        $types .= 'i';
    }
    if (!empty($search)) {
        $sql .= " AND (nombre LIKE ? OR ingredientes LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $types .= 'ss';
    }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $conexion->prepare($sql);
    if ($stmt) {
        if ($types !== '') { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recetas[] = $row;
        }
        $stmt->close();
    }
}


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Gestionar Recetas</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
        .btn-primary { background-color: #198754; border-color: #198754; }
        .btn-primary:hover { background-color: #146c43; border-color: #13653f; }
        .form-label { font-weight: 600; color: #198754; }
        .alert { border-radius: 0.375rem; }
        /* Header styles matched to Actualizar_perfil.php for consistent look */
        .header-section { background: linear-gradient(135deg, #198754 0%, #146c43 100%); color: white; padding: 0.8rem 0; margin-bottom: 1rem; }
        .header-section h1 { font-size: 2.2rem; font-weight: 700; margin: 0.15rem 0 0.25rem; }
        .header-section p { font-size: 1.05rem; opacity: 0.95; margin: 0; }
        .medical-icon { font-size: 1.9rem; margin-bottom: 0.35rem; color: #ffffff; }
        .muted { color: #6c757d; font-size: 0.875rem; }
        .preview { max-height: 150px; border-radius: 6px; border: 1px solid #dee2e6; }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header-section">
        <div class="container text-center">
            <div class="medical-icon">
                <i class="bi bi-journal-text"></i>
            </div>
            <h1>Gestionar Recetas</h1>
            <p>
                <?php if ($isPrivileged): ?>
                    Busca y visualiza todas las recetas.
                <?php else: ?>
                    Paciente #<?= (int)$paciente_id ?> | Busca y visualiza tus recetas.
                <?php endif; ?>
            </p>
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

        <?php if (!empty($noRegistrado) && !$isPrivileged): ?>
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Paciente nuevo: primero necesitas actualizar tus datos con tu médico tratante. Si aún no estás registrado como paciente en la clínica, ponte en contacto con el personal o tu médico para completar tu registro.
            </div>
        <?php endif; ?>

        <?php if (!($noRegistrado && !$isPrivileged)): ?>
        <div class="mb-4">
            <form method="get" class="d-flex">
                <input type="text" class="form-control me-2" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por nombre o ingredientes">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-1"></i>Buscar
                </button>
            </form>
        </div>



        <?php if (empty($recetas)): ?>
            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle me-2"></i>No hay recetas guardadas.
            </div>
        <?php else: ?>
            <?php foreach ($recetas as $receta): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-journal-text me-2"></i><?= htmlspecialchars($receta['nombre'], ENT_QUOTES, 'UTF-8') ?>
                        </h5>
                        <div class="btn-group" role="group">
                            <?php if ($isPrivileged): ?>
                            <a href="Editar_Receta.php?id=<?= (int)$receta['id'] ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-pencil me-1"></i>Editar
                            </a>
                            <a href="Crear_Receta.php?duplicar=<?= (int)$receta['id'] ?>" class="btn btn-outline-info btn-sm">
                                <i class="bi bi-copy me-1"></i>Duplicar
                            </a>
                            <form method="post" class="delete-receta-form" style="display:inline;" data-nombre="<?= htmlspecialchars($receta['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="receta_id" value="<?= (int)$receta['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-trash me-1"></i>Eliminar
                                </button>
                            </form>
                            <?php endif; ?>
                            <a href="Exportar_Receta.php?id=<?= (int)$receta['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-file-earmark-pdf me-1"></i>Exportar PDF
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Ingredientes:</strong>
                                <p class="mb-2"><?= nl2br(htmlspecialchars($receta['ingredientes'], ENT_QUOTES, 'UTF-8')) ?></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Porciones:</strong>
                                <p class="mb-2"><?= $receta['porciones'] ? htmlspecialchars($receta['porciones'], ENT_QUOTES, 'UTF-8') : 'N/A' ?></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Instrucciones:</strong>
                                <p class="mb-2"><?= nl2br(htmlspecialchars($receta['instrucciones'], ENT_QUOTES, 'UTF-8')) ?: 'N/A' ?></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Nota Nutricional:</strong>
                                <p class="mb-2"><?= nl2br(htmlspecialchars($receta['nota_nutricional'], ENT_QUOTES, 'UTF-8')) ?: 'N/A' ?></p>
                            </div>
                            <?php if (!empty($receta['foto_path'])): ?>
                                <div class="col-12">
                                    <strong>Foto:</strong>
                                    <img src="<?= htmlspecialchars($receta['foto_path'], ENT_QUOTES, 'UTF-8') ?>" alt="Foto de receta" class="img-fluid mt-2" style="max-width: 200px;" />
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php endif; ?>
    </div>


</body>
</html>

<!-- Confirm Delete Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #198754 0%, #146c43 100%); color: #fff;">
                <h5 class="modal-title" id="confirmDeleteLabel">Confirmar eliminación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p id="confirmDeleteMessage">¿Estás seguro de eliminar esta receta?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="confirmDeleteBtn" class="btn btn-primary">Eliminar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
        var pendingForm = null;
        var modalEl = document.getElementById('confirmDeleteModal');
        var bsModal = new bootstrap.Modal(modalEl);
        var msgEl = document.getElementById('confirmDeleteMessage');
        var confirmBtn = document.getElementById('confirmDeleteBtn');

        document.querySelectorAll('.delete-receta-form').forEach(function(form){
                form.addEventListener('submit', function(e){
                        e.preventDefault();
                        pendingForm = form;
                        var nombre = form.getAttribute('data-nombre') || '';
                        if (nombre) {
                                msgEl.textContent = "¿Estás seguro de eliminar la receta '" + nombre + "'?";
                        } else {
                                msgEl.textContent = '¿Estás seguro de eliminar esta receta?';
                        }
                        bsModal.show();
                });
        });

        confirmBtn.addEventListener('click', function(){
                if (pendingForm) {
                        // submit original form
                        pendingForm.submit();
                        pendingForm = null;
                        bsModal.hide();
                }
        });
});
</script>