<?php
// Editar_Examen.php
// Permite modificar metadatos de un examen (categoría, nombre, fecha, notas)

require_once __DIR__ . '/db_connection.php';
session_start();

if (!isset($_SESSION['id_usuarios'])) {
    header('Location: index.php');
    exit;
}
$userId = (int)$_SESSION['id_usuarios'];
$role = strtolower($_SESSION['rol'] ?? '');
$isStaff = in_array($role, ['medico', 'administrador']);

// obtener paciente asociado si no es staff
$pacienteId = null;
if (!$isStaff) {
    $stmt = $conexion->prepare('SELECT id_pacientes FROM pacientes WHERE id_usuarios = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $pacienteId = (int)$row['id_pacientes'];
    }
    $stmt->close();
}

// cargar examen
$examId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($examId <= 0) {
    die('ID de examen inválido.');
}

$stmt = $conexion->prepare('SELECT * FROM examenes WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $examId);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    die('Examen no encontrado.');
}

// permiso: el paciente dueño o staff
if (!$isStaff) {
    if ($pacienteId === null || $pacienteId !== (int)$exam['id_pacientes']) {
        die('No tienes permiso para editar este examen.');
    }
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validar campos
    $categoria = trim($_POST['categoria_examen'] ?? '');
    $nombreExamen = trim($_POST['nombre_examen'] ?? '');
    $fechaExamen = trim($_POST['fecha_examen'] ?? '');
    $notas = trim($_POST['notas'] ?? '');

    $validCats = ['Perfil Metabólico', 'Nutrientes y Sangre', 'Función Orgánica', 'Composición Corporal', 'Otros / Especializados'];
    if ($categoria === '' || !in_array($categoria, $validCats)) {
        $errors[] = 'Categoría inválida.';
    }
    if ($nombreExamen === '') {
        $errors[] = 'Nombre del examen obligatorio.';
    }
    if ($fechaExamen === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaExamen)) {
        $errors[] = 'Fecha inválida.';
    }

    if (empty($errors)) {
        $stmt = $conexion->prepare('UPDATE examenes SET categoria=?, nombre_examen=?, fecha_examen=?, notas=? WHERE id=?');
        $stmt->bind_param('ssssi', $categoria, $nombreExamen, $fechaExamen, $notas, $examId);
        if ($stmt->execute()) {
            $success = 'Examen actualizado con éxito.';
            // recargar datos
            $stmt->close();
            $stmt2 = $conexion->prepare('SELECT * FROM examenes WHERE id = ? LIMIT 1');
            $stmt2->bind_param('i', $examId);
            $stmt2->execute();
            $exam = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
        } else {
            $errors[] = 'Error al guardar los cambios.';
        }
    }
}

function h($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Editar Examen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body style="background:#f8f9fa;">
    <div class="container mt-4 mb-5">
        <h1 class="mb-3"><i class="bi bi-pencil-square"></i> Editar Examen</h1>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e) echo '<li>'.h($e).'</li>'; ?></ul></div>
        <?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?=h($success)?></div><?php endif; ?>

        <form method="post" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Categoría *</label>
                <select name="categoria_examen" class="form-select" required>
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($validCats as $c): ?>
                        <option value="<?=h($c)?>" <?= $exam['categoria'] === $c ? 'selected' : '' ?>><?=h($c)?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Nombre del Examen *</label>
                <input type="text" name="nombre_examen" class="form-control" required value="<?=h($exam['nombre_examen'])?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Fecha *</label>
                <input type="date" name="fecha_examen" class="form-control" required value="<?=h($exam['fecha_examen'])?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Notas</label>
                <textarea name="notas" class="form-control" rows="1"><?=h($exam['notas'])?></textarea>
            </div>
            <div class="col-12 d-grid">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Guardar cambios</button>
            </div>
        </form>
    </div>
</body>
</html>