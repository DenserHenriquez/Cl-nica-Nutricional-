<?php
// Registro_Alimentos.php
// Funcionalidades:
// - Formulario para registrar comidas diarias (desayuno, almuerzo, cena y snacks)
// - Subida de fotos de los platos (validación de tipo y tamaño)
// - Guardado en BD vinculado al paciente (id_pacientes)
// - Validaciones de campos
// - Historial diario o semanal filtrable por fecha

require_once __DIR__ . '/db_connection.php';
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
if ($row = $result->fetch_assoc()) {
    $paciente_id = (int)$row['id_pacientes'];
} else {
    // Usuario no es paciente registrado
    header('Location: Menuprincipal.php?error=No eres un paciente registrado.');
    exit;
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
// columnas: id (PK), paciente_id (FK -> pacientes.id_pacientes), fecha (DATE),
// tipo_comida (ENUM o VARCHAR: desayuno|almuerzo|cena|snack), descripcion (TEXT),
// hora (TIME), foto_path (VARCHAR), created_at (DATETIME)
$conexion->query("CREATE TABLE IF NOT EXISTS alimentos_registro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    fecha DATE NOT NULL,
    tipo_comida VARCHAR(20) NOT NULL,
    descripcion TEXT NOT NULL,
    hora TIME NOT NULL,
    foto_path VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_paciente_fecha (paciente_id, fecha),
    CONSTRAINT fk_alimentos_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(id_pacientes) ON DELETE CASCADE
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
        $stmtCheck = $conexion->prepare("SELECT foto_path FROM alimentos_registro WHERE id = ? AND paciente_id = ? LIMIT 1");
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
            $stmtDelete = $conexion->prepare("DELETE FROM alimentos_registro WHERE id = ? AND paciente_id = ?");
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
            // Validar MIME
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);
            $ext = null;
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif'
            ];
            if (!isset($allowed[$mime])) {
                $errores[] = 'Formato de imagen inválido. Solo JPG, PNG o GIF.';
            } else {
                $ext = $allowed[$mime];
            }
            // Si todo ok, mover archivo
            if (empty($errores)) {
                $basename = 'paciente_' . $paciente_id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
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
        $sql = "INSERT INTO alimentos_registro (paciente_id, fecha, tipo_comida, descripcion, hora, foto_path)
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
             WHERE paciente_id = ? AND fecha = ?
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
             WHERE paciente_id = ? AND fecha BETWEEN ? AND ?
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
            max-width: 1200px;
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

        .grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
        }

        .card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .card h2 {
            color: var(--primary-700);
            margin-top: 0;
            margin-bottom: 16px;
            font-size: 1.25rem;
            font-weight: 600;
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

        input, select, textarea, button {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        button {
            background: var(--primary-500);
            color: var(--white);
            border: none;
            cursor: pointer;
            font-weight: 500;
            padding: 10px 16px;
            transition: background 0.2s;
        }

        button:hover {
            background: var(--primary-700);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .table th, .table td {
            border: 1px solid var(--gray-200);
            padding: 12px;
            text-align: left;
        }

        .table th {
            background: var(--gray-100);
            font-weight: 600;
            color: var(--gray-900);
        }

        .table tbody tr:hover {
            background: var(--gray-50);
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

        .preview {
            max-height: 90px;
            border-radius: 6px;
            border: 1px solid var(--gray-200);
        }

        .muted {
            color: var(--gray-700);
            font-size: 0.875rem;
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
        }

        .form-actions a:hover {
            background: var(--gray-200);
        }

        .btn-back {
            display: inline-block;
            padding: 8px 16px;
            background: var(--primary-500);
            color: var(--white);
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: background 0.2s;
            white-space: nowrap;
        }

        .btn-back:hover {
            background: var(--primary-700);
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 16px;
            }
            .row {
                flex-direction: column;
                gap: 12px;
            }
            .form-actions {
                flex-direction: column;
            }
            .btn-back {
                margin-top: 16px;
                align-self: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h1>Registro de Alimentos</h1>
                <p class="subtitle">Paciente #<?= (int)$paciente_id ?> | Registre sus comidas diarias con foto opcional.</p>
            </div>
            <a href="Menuprincipal.php" class="btn-back">← Regresar al Menú Principal</a>
        </div>

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
            <h2>Nuevo registro</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <div class="row">
                    <label>Fecha
                        <input type="date" name="fecha" value="<?= htmlspecialchars($hoy, ENT_QUOTES, 'UTF-8') ?>" required />
                    </label>
                    <label>Hora
                        <input type="time" name="hora" required />
                    </label>
                    <label>Tipo de comida
                        <select name="tipo_comida" required>
                            <option value="desayuno">Desayuno</option>
                            <option value="almuerzo">Almuerzo</option>
                            <option value="cena">Cena</option>
                            <option value="snack">Snack</option>
                        </select>
                    </label>
                </div>
                <div class="row">
                    <label style="flex:1 1 100%">Descripción del plato / alimentos
                        <textarea name="descripcion" rows="3" placeholder="Ej: Ensalada de pollo, arroz integral, agua" required></textarea>
                    </label>
                </div>
                <div class="row">
                    <label>Foto del plato (opcional)
                        <input type="file" name="foto" accept="image/jpeg,image/png,image/gif" id="fotoInput" onchange="previewImage(event)" />
                        <span class="muted">Formatos: JPG, PNG, GIF. Máx 3MB.</span>
                        <div id="imagePreview" style="margin-top: 10px; display: none;">
                            <img id="previewImg" class="preview" alt="Vista previa" />
                        </div>
                    </label>
                </div>
                <div class="form-actions">
                    <button type="submit">Guardar registro</button>
                    <a href="?id=<?= (int)$paciente_id ?>&vista=diaria&fecha=<?= htmlspecialchars($hoy, ENT_QUOTES, 'UTF-8') ?>">Ver hoy</a>
                    <a href="?id=<?= (int)$paciente_id ?>&vista=semanal&fecha=<?= htmlspecialchars($hoy, ENT_QUOTES, 'UTF-8') ?>">Ver semana actual</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Historial <?= $vista === 'semanal' ? 'semanal' : 'diario' ?></h2>
            <form method="get" class="row" action="">
                <input type="hidden" name="id" value="<?= (int)$paciente_id ?>" />
                <label>Vista
                    <select name="vista">
                        <option value="diaria" <?= $vista==='diaria'?'selected':'' ?>>Diaria</option>
                        <option value="semanal" <?= $vista==='semanal'?'selected':'' ?>>Semanal</option>
                    </select>
                </label>
                <label>Fecha base
                    <input type="date" name="fecha" value="<?= htmlspecialchars($fechaFiltro, ENT_QUOTES, 'UTF-8') ?>" />
                </label>
                <button type="submit">Aplicar</button>
            </form>

            <?php if (empty($historial)): ?>
                <p class="muted">No hay registros para el periodo seleccionado.</p>
            <?php else: ?>
                <table class="table">
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
                                        <button type="submit" style="background: #ef4444; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.875rem;" onclick="return confirm('¿Eliminar este registro?')">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Galería de Comidas Registradas</h2>
            <?php
            // Obtener todas las comidas con fotos del paciente
            $sqlGaleria = "SELECT fecha, tipo_comida, descripcion, hora, foto_path
                           FROM alimentos_registro
                           WHERE paciente_id = ? AND foto_path IS NOT NULL
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
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px;">
                    <?php foreach ($galeria as $item): ?>
                        <div style="border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; background: var(--white);">
                            <img src="<?= htmlspecialchars($item['foto_path'], ENT_QUOTES, 'UTF-8') ?>" alt="Foto de comida" style="width: 100%; height: 150px; object-fit: cover; border-radius: 6px;" />
                            <div style="margin-top: 8px;">
                                <div style="font-weight: 600; color: var(--primary-700); text-transform: capitalize;">
                                    <?= htmlspecialchars($item['tipo_comida'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div style="font-size: 0.875rem; color: var(--gray-700);">
                                    <?= htmlspecialchars($item['fecha'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(substr($item['hora'],0,5), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div style="font-size: 0.875rem; color: var(--gray-900); margin-top: 4px;">
                                    <?= nl2br(htmlspecialchars($item['descripcion'], ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
