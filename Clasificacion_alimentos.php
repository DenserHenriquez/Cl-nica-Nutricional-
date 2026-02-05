<?php
// Clasificacion_alimentos.php
// Funcionalidades:
// - Formulario para registrar alimentos o platos con información nutricional (calorías, proteínas, grasas, carbohidratos)
// - Guardado en BD centralizada
// - Lista de alimentos registrados para usar como bloques en dietas
// - Validaciones de campos
// - Solo accesible para médicos (asumiendo rol de doctor)

require_once __DIR__ . '/db_connection.php';
session_start();

// Verificar sesión de usuario
if (!isset($_SESSION['id_usuarios'])) {
    header('Location: index.php');
    exit;
}

$user_id = (int)$_SESSION['id_usuarios'];

// Verificar rol: si es paciente, mostrar error en la página
$stmt = $conexion->prepare("SELECT id_pacientes FROM pacientes WHERE id_usuarios = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->fetch_assoc()) {
    // Es paciente, mostrar error
    $errores[] = 'No tienes permisos para acceder a esta sección. Esta página es solo para médicos.';
}
$stmt->close();

// Configuración
$errores = [];
$exito = '';

// Crear tabla si no existe (defensivo)
// Tabla sugerida: alimentos_nutricionales
// columnas: id_alimento (PK), nombre (VARCHAR), tipo (ENUM: alimento|plato), calorias (DECIMAL), proteinas (DECIMAL), grasas (DECIMAL), carbohidratos (DECIMAL), created_by (INT), fecha_creacion (DATETIME)
$conexion->query("CREATE TABLE IF NOT EXISTS alimentos_nutricionales (
    id_alimento INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    tipo ENUM('alimento', 'plato') NOT NULL,
    calorias DECIMAL(10,2) NOT NULL,
    proteinas DECIMAL(10,2) NOT NULL,
    grasas DECIMAL(10,2) NOT NULL,
    carbohidratos DECIMAL(10,2) NOT NULL,
    created_by INT NOT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_created_by (created_by)
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

        // Eliminar de BD
        $stmtDelete = $conexion->prepare("DELETE FROM alimentos_nutricionales WHERE id_alimento = ? AND created_by = ?");
        $stmtDelete->bind_param('ii', $delete_id, $user_id);
        if ($stmtDelete->execute()) {
            $exito = 'Alimento eliminado correctamente.';
        } else {
            $errores[] = 'Error al eliminar el alimento.';
        }
        $stmtDelete->close();
    } else {
        // Agregar nuevo
        $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
        $tipo = isset($_POST['tipo']) ? trim($_POST['tipo']) : '';
        $calorias = isset($_POST['calorias']) ? (float)$_POST['calorias'] : 0;
        $proteinas = isset($_POST['proteinas']) ? (float)$_POST['proteinas'] : 0;
        $grasas = isset($_POST['grasas']) ? (float)$_POST['grasas'] : 0;
        $carbohidratos = isset($_POST['carbohidratos']) ? (float)$_POST['carbohidratos'] : 0;

        // Validaciones
        if ($nombre === '') $errores[] = 'El nombre es obligatorio';
        if (!in_array($tipo, ['alimento', 'plato'], true)) $errores[] = 'Tipo inválido';
        if ($calorias <= 0) $errores[] = 'Las calorías deben ser mayor a 0';
        if ($proteinas < 0) $errores[] = 'Las proteínas no pueden ser negativas';
        if ($grasas < 0) $errores[] = 'Las grasas no pueden ser negativas';
        if ($carbohidratos < 0) $errores[] = 'Los carbohidratos no pueden ser negativos';

        if (empty($errores)) {
            $sql = "INSERT INTO alimentos_nutricionales (nombre, tipo, calorias, proteinas, grasas, carbohidratos, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conexion->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('ssddddi', $nombre, $tipo, $calorias, $proteinas, $grasas, $carbohidratos, $user_id);
                if ($stmt->execute()) {
                    $exito = 'Alimento registrado correctamente.';
                } else {
                    $errores[] = 'Error al guardar en BD: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $errores[] = 'Error preparando consulta: ' . $conexion->error;
            }
        }
    }
}

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

// Obtener lista de alimentos
$alimentos = [];
$sqlList = "SELECT id_alimento, nombre, tipo, calorias, proteinas, grasas, carbohidratos, fecha_creacion
            FROM alimentos_nutricionales
            WHERE created_by = ?
            ORDER BY fecha_creacion DESC";
$stmtList = $conexion->prepare($sqlList);
if ($stmtList) {
    $stmtList->bind_param('i', $user_id);
    if ($stmtList->execute()) {
        $res = $stmtList->get_result();
        while ($row = $res->fetch_assoc()) {
            $alimentos[] = $row;
        }
    }
    $stmtList->close();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Clasificación de Alimentos</title>
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
                <i class="bi bi-apple"></i>
            </div>
            <h1>Clasificación de Alimentos</h1>
            <p>Médico | Registre alimentos y platos con información nutricional para construir dietas.</p>
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

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-plus-circle me-2"></i>Nuevo Alimento / Plato</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label">
                                <i class="bi bi-tag me-1"></i>Nombre
                            </label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="col-md-6">
                            <label for="tipo" class="form-label">
                                <i class="bi bi-list me-1"></i>Tipo
                            </label>
                            <select class="form-control" id="tipo" name="tipo" required>
                                <option value="alimento">Alimento</option>
                                <option value="plato">Plato</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label for="calorias" class="form-label">
                                <i class="bi bi-fire me-1"></i>Calorías (kcal)
                            </label>
                            <input type="number" class="form-control" id="calorias" name="calorias" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="proteinas" class="form-label">
                                <i class="bi bi-egg-fried me-1"></i>Proteínas (g)
                            </label>
                            <input type="number" class="form-control" id="proteinas" name="proteinas" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="grasas" class="form-label">
                                <i class="bi bi-droplet me-1"></i>Grasas (g)
                            </label>
                            <input type="number" class="form-control" id="grasas" name="grasas" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="carbohidratos" class="form-label">
                                <i class="bi bi-graph-up me-1"></i>Carbohidratos (g)
                            </label>
                            <input type="number" class="form-control" id="carbohidratos" name="carbohidratos" step="0.01" min="0" required>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save me-2"></i>Registrar Alimento
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-list me-2"></i>Alimentos Registrados</h5>
            </div>
            <div class="card-body">
                <?php if (empty($alimentos)): ?>
                    <p class="muted">No hay alimentos registrados aún.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped enhance-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Calorías</th>
                                    <th>Proteínas</th>
                                    <th>Grasas</th>
                                    <th>Carbohidratos</th>
                                    <th>Fecha Creación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alimentos as $alimento): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($alimento['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(ucfirst($alimento['tipo']), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($alimento['calorias'], ENT_QUOTES, 'UTF-8') ?> kcal</td>
                                        <td><?= htmlspecialchars($alimento['proteinas'], ENT_QUOTES, 'UTF-8') ?> g</td>
                                        <td><?= htmlspecialchars($alimento['grasas'], ENT_QUOTES, 'UTF-8') ?> g</td>
                                        <td><?= htmlspecialchars($alimento['carbohidratos'], ENT_QUOTES, 'UTF-8') ?> g</td>
                                        <td><?= htmlspecialchars($alimento['fecha_creacion'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('¿Está seguro de que desea eliminar este alimento?');">
                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="delete_id" value="<?= (int)$alimento['id_alimento'] ?>">
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
    </div>
</div>
    <script src="assets/js/script.js"></script>
</body>
</html>
