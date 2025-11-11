<?php
// Gestion_Recetas.php
// Módulo para buscar, ver, editar y eliminar recetas guardadas

require_once __DIR__ . '/db_connection.php';
session_start();

// Verificar sesión de usuario
if (!isset($_SESSION['id_usuarios'])) {
    header('Location: index.php');
    exit;
}

$user_id = (int)$_SESSION['id_usuarios'];

// Obtener id del paciente desde la BD usando id_usuarios
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
    header('Location: Menuprincipal.php?error=No eres un paciente registrado.');
    exit;
}
$stmt->close();

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

// Búsqueda
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$recetas = [];
$sql = "SELECT id, nombre, ingredientes, porciones, instrucciones, nota_nutricional, foto_path, created_at FROM recetas WHERE id_pacientes = ?";
$params = [$paciente_id];
$types = 'i';
if (!empty($search)) {
    $sql .= " AND (nombre LIKE ? OR ingredientes LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $types .= 'ss';
}
$sql .= " ORDER BY created_at DESC";
$stmt = $conexion->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recetas[] = $row;
    }
    $stmt->close();
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
    <title>Gestionar Recetas</title>
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
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        .alert {
            border-radius: 0.375rem;
        }
        .header-section {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
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
                <i class="bi bi-journal-text"></i>
            </div>
            <h1>Gestionar Recetas</h1>
            <p>Paciente #<?= (int)$paciente_id ?> | Busca y visualiza tus recetas.</p>
        </div>
    </div>

    <div class="container mb-5">



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
    </div>


</body>
</html>