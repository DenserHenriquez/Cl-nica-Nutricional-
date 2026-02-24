<?php
// Editar_medico.php - formulario simple para editar Nombre y Correo de un médico
session_start();
require_once __DIR__ . '/db_connection.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) {
    die('Error de conexión a la base de datos.');
}

function e($v){ return htmlspecialchars($v ?? '', ENT_QUOTES); }

$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
if ($id <= 0) {
    header('Location: citas_medico.php');
    exit;
}

// Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $especialidad = isset($_POST['especialidad']) ? trim($_POST['especialidad']) : '';
    $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';

    if ($nombre === '' || $email === '') {
        $error = 'Nombre y correo son requeridos.';
    } else {
        // Manejar subida de imagen (opcional)
        $imagenPath = null;
        if (!empty($_FILES['imagen']['name']) && ($_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE)) {
            $file = $_FILES['imagen'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Error al subir la imagen.';
            } else {
                $maxSize = 3 * 1024 * 1024; // 3MB
                if ($file['size'] > $maxSize) {
                    $error = 'La imagen excede 3MB.';
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file['tmp_name']);
                    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/avif' => 'avif', 'image/webp' => 'webp'];
                    if (!isset($allowed[$mime])) {
                        $error = 'Tipo de imagen no permitido.';
                    } else {
                        $ext = $allowed[$mime];
                        $dir = __DIR__ . '/uploads/perfiles';
                        if (!is_dir($dir)) @mkdir($dir, 0755, true);
                        $name = 'medico_' . $id . '_' . time() . '.' . $ext;
                        $destRel = 'uploads/perfiles/' . $name;
                        $dest = __DIR__ . '/' . $destRel;
                        if (move_uploaded_file($file['tmp_name'], $dest)) {
                            $imagenPath = $destRel;
                        } else {
                            $error = 'No se pudo guardar la imagen.';
                        }
                    }
                }
            }
        }

        if (empty($error)) {
            // Asegurar que existan las columnas necesarias (imagen, especialidad)
            $colsRes = $conexion->query("SHOW COLUMNS FROM usuarios");
            $hasImagen = false; $hasEspecialidad = false;
            if ($colsRes) {
                while ($r = $colsRes->fetch_assoc()) {
                    if (strtolower($r['Field']) === 'imagen' || strtolower($r['Field']) === 'imagen_url') $hasImagen = true;
                    if (strtolower($r['Field']) === 'especialidad') $hasEspecialidad = true;
                }
            }
            if (!$hasImagen) {
                @$conexion->query("ALTER TABLE usuarios ADD COLUMN imagen VARCHAR(500) DEFAULT NULL");
                $hasImagen = true;
            }
            if (!$hasEspecialidad) {
                @$conexion->query("ALTER TABLE usuarios ADD COLUMN especialidad VARCHAR(255) DEFAULT NULL");
                $hasEspecialidad = true;
            }
            // Asegurar columna telefono
            $hasTelefono = false;
            $colsRes2 = $conexion->query("SHOW COLUMNS FROM usuarios");
            if ($colsRes2) { while ($r = $colsRes2->fetch_assoc()) { if (strtolower($r['Field']) === 'telefono') $hasTelefono = true; } }
            if (!$hasTelefono) {
                @$conexion->query("ALTER TABLE usuarios ADD COLUMN telefono VARCHAR(30) DEFAULT NULL");
            }

            // Preparar UPDATE según si hay imagen
            if ($imagenPath !== null) {
                $stmt = $conexion->prepare("UPDATE usuarios SET Nombre_completo = ?, Correo_electronico = ?, especialidad = ?, imagen = ?, telefono = ? WHERE id_usuarios = ?");
                if ($stmt) {
                    $stmt->bind_param('sssssi', $nombre, $email, $especialidad, $imagenPath, $telefono, $id);
                }
            } else {
                $stmt = $conexion->prepare("UPDATE usuarios SET Nombre_completo = ?, Correo_electronico = ?, especialidad = ?, telefono = ? WHERE id_usuarios = ?");
                if ($stmt) {
                    $stmt->bind_param('ssssi', $nombre, $email, $especialidad, $telefono, $id);
                }
            }

            if ($stmt) {
                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: citas_medico.php?medico_id=' . $id . '&updated=1');
                    exit;
                } else {
                    $error = 'Error al guardar los cambios.';
                }
            } else {
                $error = 'Error en la consulta.';
            }
        }
    }
}

// Cargar datos actuales (seleccionar columnas solo si existen)
$cols = ['id_usuarios', 'Nombre_completo', 'Correo_electronico'];
$colsRes = $conexion->query("SHOW COLUMNS FROM usuarios");
if ($colsRes) {
    while ($r = $colsRes->fetch_assoc()) {
        $f = strtolower($r['Field']);
        if ($f === 'especialidad') $cols[] = 'especialidad';
        if ($f === 'imagen' || $f === 'imagen_url') $cols[] = 'imagen';
        if ($f === 'telefono') $cols[] = 'telefono';
    }
}
$select = implode(', ', array_unique($cols));
$stmt = $conexion->prepare("SELECT $select FROM usuarios WHERE id_usuarios = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();
} else {
    die('No se pudo preparar la consulta.');
}
if (!$user) {
    header('Location: citas_medico.php');
    exit;
}

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Médico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Editar Médico</h5>
                    <?php if (!empty(
                        $error)): ?>
                        <div class="alert alert-danger"><?php echo e($error); ?></div>
                    <?php endif; ?>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                        <div class="row">
                            <div class="col-md-4 text-center mb-3">
                                <?php
                                $imgSrc = '';
                                if (!empty($user['imagen']) && file_exists(__DIR__ . '/' . $user['imagen'])) {
                                    $imgSrc = $user['imagen'];
                                } else {
                                    $nameForAvatar = !empty($user['Nombre_completo']) ? $user['Nombre_completo'] : 'Medico';
                                    $imgSrc = 'https://ui-avatars.com/api/?name=' . urlencode($nameForAvatar) . '&background=ffffff&color=198754&bold=false&size=200';
                                }
                                ?>
                                <div class="card p-3">
                                    <img src="<?php echo e($imgSrc); ?>" alt="avatar" class="img-fluid rounded mb-2" style="max-height:120px;object-fit:cover;">
                                    <div class="small text-muted"><?php echo e($user['Nombre_completo']); ?></div>
                                    <div class="fw-light text-success mb-2"><?php echo e($user['especialidad'] ?? 'Médico de la Clínica'); ?></div>
                                    <label class="form-label small mt-2">Cambiar imagen</label>
                                    <input type="file" name="imagen" class="form-control form-control-sm" accept="image/*">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Nombre completo</label>
                                    <input type="text" name="nombre" class="form-control" value="<?php echo e($user['Nombre_completo']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Correo electrónico</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo e($user['Correo_electronico']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Especialidad</label>
                                    <input type="text" name="especialidad" class="form-control" value="<?php echo e($user['especialidad'] ?? ''); ?>" placeholder="Ej: Nutrición General">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Teléfono</label>
                                    <input type="tel" name="telefono" class="form-control" value="<?php echo e($user['telefono'] ?? ''); ?>" placeholder="Ej: 9981234567">
                                </div>
                                <div class="d-flex justify-content-between">
                                    <a href="citas_medico.php?medico_id=<?php echo (int)$id; ?>" class="btn btn-secondary">Cancelar</a>
                                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
