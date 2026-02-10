<?php
// Editar_Receta.php
// Página para editar una receta existente

require_once __DIR__ . '/db_connection.php';
session_start();

if (!isset($_SESSION['id_usuarios'])) {
    header('Location: index.php');
    exit;
}
$user_id = (int)$_SESSION['id_usuarios'];

// Detectar rol
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

// Si no privilegiado, obtener id_pacientes
$paciente_id = 0;
$noRegistrado = false;
if (!$isPrivileged) {
    $stmt = $conexion->prepare("SELECT id_pacientes FROM pacientes WHERE id_usuarios = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($r = $res->fetch_assoc()) { $paciente_id = (int)$r['id_pacientes']; }
        else { $noRegistrado = true; }
        $stmt->close();
    }
}

// CSRF
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

$errors = [];
$success = '';

$receta_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($receta_id <= 0) {
    die('ID de receta inválido.');
}

// Cargar receta
$stmt = $conexion->prepare("SELECT id, id_pacientes, nombre, ingredientes, porciones, instrucciones, nota_nutricional, foto_path FROM recetas WHERE id = ? LIMIT 1");
if (!$stmt) { die('Error preparando consulta: ' . $conexion->error); }
$stmt->bind_param('i', $receta_id);
$stmt->execute();
$res = $stmt->get_result();
$receta = $res->fetch_assoc();
$stmt->close();

if (!$receta) { die('Receta no encontrada.'); }

// Permisos: solo privilegiados o el paciente dueño
$recetaPacienteId = (int)$receta['id_pacientes'];
if (!($isPrivileged || ($paciente_id !== 0 && $paciente_id === $recetaPacienteId))) {
    die('No tienes permisos para editar esta receta.');
}

// Manejo POST (actualizar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        $errors[] = 'Token CSRF inválido.';
    }

    $nombre = trim($_POST['nombre'] ?? '');
    $ingredientes = trim($_POST['ingredientes'] ?? '');
    $porciones = isset($_POST['porciones']) && $_POST['porciones'] !== '' ? (int)$_POST['porciones'] : null;
    $instrucciones = trim($_POST['instrucciones'] ?? '');
    $nota_nutricional = trim($_POST['nota_nutricional'] ?? '');

    if ($nombre === '') $errors[] = 'El nombre es obligatorio.';
    if ($ingredientes === '') $errors[] = 'Los ingredientes son obligatorios.';

    // Procesar imagen si se envía
    $fotoPath = $receta['foto_path'];
    $uploadDir = __DIR__ . '/assets/images/recetas';
    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['foto'];
        if ($file['error'] !== UPLOAD_ERR_OK) { $errors[] = 'Error al subir la imagen.'; }
        else {
            $maxSize = 3 * 1024 * 1024;
            if ($file['size'] > $maxSize) { $errors[] = 'La imagen excede 3MB.'; }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (strpos($mime, 'image/') !== 0) { $errors[] = 'El archivo debe ser una imagen.'; }
            else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext === '') { $ext = 'jpg'; }
                $basename = 'receta_' . $recetaPacienteId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadDir . '/' . $basename;
                if (!move_uploaded_file($file['tmp_name'], $dest)) { $errors[] = 'No se pudo guardar la imagen.'; }
                else {
                    // borrar anterior si existe y está en assets/images/recetas
                    if (!empty($receta['foto_path']) && strpos($receta['foto_path'], 'assets/images/recetas/') === 0) {
                        $old = __DIR__ . '/' . $receta['foto_path'];
                        if (is_file($old)) { @unlink($old); }
                    }
                    $fotoPath = 'assets/images/recetas/' . $basename;
                }
            }
        }
    }

    if (empty($errors)) {
        $sql = "UPDATE recetas SET nombre = ?, ingredientes = ?, porciones = ?, instrucciones = ?, nota_nutricional = ?, foto_path = ? WHERE id = ?";
        if ($stmt = $conexion->prepare($sql)) {
            // porciones puede ser NULL
            $porciones_param = $porciones === null ? null : $porciones;
            $stmt->bind_param('ssisssi', $nombre, $ingredientes, $porciones_param, $instrucciones, $nota_nutricional, $fotoPath, $receta_id);
            if ($stmt->execute()) {
                $success = 'Receta actualizada correctamente.';
                // recargar receta
                $stmt->close();
                $stmt2 = $conexion->prepare("SELECT id, id_pacientes, nombre, ingredientes, porciones, instrucciones, nota_nutricional, foto_path FROM recetas WHERE id = ? LIMIT 1");
                $stmt2->bind_param('i', $receta_id);
                $stmt2->execute();
                $receta = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();
            } else { $errors[] = 'Error al actualizar la receta.'; }
        } else { $errors[] = 'Error preparando la actualización: ' . $conexion->error; }
    }
}

function h($v){ return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Editar Receta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body{ background:#f8f9fa; }
        .header-section{ background: linear-gradient(135deg, #198754 0%, #146c43 100%); color: white; padding: 0.8rem 0; margin-bottom: 1rem; }
        .header-section h1{ font-size:2.2rem; margin:0.15rem 0 0.25rem; }
        .medical-icon{ font-size:1.9rem; margin-bottom:0.35rem; }
        .form-label{ font-weight:600; color:#198754; }
    </style>
</head>
<body>
    <div class="header-section">
        <div class="container text-center">
            <div class="medical-icon"><i class="bi bi-journal-text"></i></div>
            <h1>Editar Receta</h1>
            <p>Modifica los campos de la receta y guarda los cambios.</p>
        </div>
    </div>
    <div class="container mb-5">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e) echo '<li>'.h($e).'</li>'; ?></ul></div>
        <?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?=h($success)?></div><?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?=h($csrf)?>">
                    <input type="hidden" name="id" value="<?= (int)$receta_id ?>">

                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input name="nombre" class="form-control" required value="<?=h($receta['nombre'])?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ingredientes</label>
                        <textarea name="ingredientes" class="form-control" rows="4" required><?=h($receta['ingredientes'])?></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Porciones</label>
                            <input type="number" name="porciones" min="1" class="form-control" value="<?=h($receta['porciones'])?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Nota nutricional</label>
                            <input name="nota_nutricional" class="form-control" value="<?=h($receta['nota_nutricional'])?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Instrucciones</label>
                        <textarea name="instrucciones" class="form-control" rows="4"><?=h($receta['instrucciones'])?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Foto (opcional)</label>
                        <input type="file" name="foto" accept="image/*" class="form-control" onchange="previewImage(event)">
                        <?php if (!empty($receta['foto_path'])): ?>
                            <div class="mt-2">
                                <img id="previewImg" src="<?=h($receta['foto_path'])?>" style="max-width:200px;" />
                            </div>
                        <?php else: ?>
                            <div id="imagePreview" class="mt-2" style="display:none;"><img id="previewImg" style="max-width:200px;"/></div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="Gestion_Receta.php" class="btn btn-secondary">Cancelar</a>
                        <button class="btn btn-primary" type="submit">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function previewImage(e){
        const img = document.getElementById('previewImg');
        img.src = URL.createObjectURL(e.target.files[0]);
        img.onload = ()=> URL.revokeObjectURL(img.src);
        document.getElementById('imagePreview')?.style.display = 'block';
    }
    </script>
</body>
</html>
