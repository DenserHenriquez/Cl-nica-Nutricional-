<?php
// Seguimiento_ejercicio.php

require_once __DIR__. '/db_connection.php';
session_start();

// ---------------- Utils (migraciones ligeras) ----------------
function db_name(mysqli $cx): string {
    $res = $cx->query("SELECT DATABASE()"); $row = $res->fetch_row(); return $row[0] ?? '';
}
function column_exists(mysqli $cx, string $table, string $col): bool {
    $db = db_name($cx);
    $stmt = $cx->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
    $stmt->bind_param('sss', $db, $table, $col);
    $stmt->execute(); $stmt->bind_result($c); $stmt->fetch(); $stmt->close();
    return (int)$c > 0;
}

// ---------------- Seguridad sesión ----------------
if (!isset($_SESSION['id_usuarios'])) {
    header('Location: index.php'); exit;
}

$errores = [];
$exito = '';

$user_id = (int)$_SESSION['id_usuarios'];

// Obtener id_pacientes del usuario
$stmt = $conexion->prepare("SELECT id_pacientes FROM pacientes WHERE id_usuarios = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$row = $res->fetch_assoc()) {
    header('Location: Menuprincipal.php?error=No eres un paciente registrado.'); exit;
}
$paciente_id = (int)$row['id_pacientes'];
$stmt->close();

// ---------------- Preparación de tabla y columnas ----------------
$conexion->query("CREATE TABLE IF NOT EXISTS ejercicios (
    id_ejercicio INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    fecha DATE NOT NULL,
    tipo_ejercicio VARCHAR(100) NOT NULL,
    tiempo INT NOT NULL COMMENT 'Duración en minutos',
    hora TIME NOT NULL DEFAULT '00:00:00',
    imagen_evidencia VARCHAR(255) DEFAULT NULL,
    notas TEXT NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_paciente_fecha (paciente_id, fecha),
    CONSTRAINT fk_ejercicios_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(id_pacientes) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Asegurar columna hora si faltaba
if (!column_exists($conexion, 'ejercicios', 'hora')) {
    $conexion->query("ALTER TABLE ejercicios ADD COLUMN hora TIME NOT NULL DEFAULT '00:00:00' AFTER tiempo");
}

// Detectar nombre real de la FK en la tabla (paciente_id o id_pacientes)
$FK = column_exists($conexion, 'ejercicios', 'paciente_id') ? 'paciente_id' : (column_exists($conexion, 'ejercicios', 'id_pacientes') ? 'id_pacientes' : 'paciente_id');

// ---------------- Archivos ----------------
$uploadDir = __DIR__ . '/uploads/ejercicios';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
    $errores[] = 'No se pudo crear el directorio para subir imágenes.';
}

// ---------------- POST (crear/eliminar) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!isset($_POST['csrf']) || !isset($_SESSION['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $errores[] = 'Token inválido. Recargue la página.';
    }

    // Eliminar
    if (isset($_POST['delete_id']) && ctype_digit((string)$_POST['delete_id']) && empty($errores)) {
        $delete_id = (int)$_POST['delete_id'];
        $stmtCheck = $conexion->prepare("SELECT imagen_evidencia FROM ejercicios WHERE id_ejercicio=? AND {$FK}=? LIMIT 1");
        $stmtCheck->bind_param('ii', $delete_id, $paciente_id);
        $stmtCheck->execute(); $r = $stmtCheck->get_result();
        if ($rowC = $r->fetch_assoc()) {
            if (!empty($rowC['imagen_evidencia'])) {
                $filePath = __DIR__ . '/' . $rowC['imagen_evidencia'];
                if (is_file($filePath)) @unlink($filePath);
            }
            $stmtDel = $conexion->prepare("DELETE FROM ejercicios WHERE id_ejercicio=? AND {$FK}=?");
            $stmtDel->bind_param('ii', $delete_id, $paciente_id);
            $exito = $stmtDel->execute() ? 'Registro eliminado correctamente.' : 'Error al eliminar el registro.';
            $stmtDel->close();
        } else {
            $errores[] = 'Registro no encontrado o no autorizado.';
        }
        $stmtCheck->close();
    }

    // Crear
    if (!isset($_POST['delete_id'])) {
        $fecha  = trim($_POST['fecha'] ?? '');
        $hora   = trim($_POST['hora'] ?? '');
        $tipo   = trim($_POST['tipo_ejercicio'] ?? '');
        $tiempo = (int)($_POST['tiempo'] ?? 0);
        $notas  = trim($_POST['notas'] ?? '');

        if ($fecha === '') $errores[] = 'La fecha es obligatoria';
        if ($hora === '') $errores[] = 'La hora es obligatoria';
        if ($tipo === '') $errores[] = 'El tipo de ejercicio es obligatorio';
        if ($tiempo <= 0) $errores[] = 'El tiempo debe ser mayor a 0 minutos';
        if ($notas === '') $errores[] = 'Las notas son obligatorias';

        $imagenEvidencia = null;
        if (!empty($_FILES['imagen_evidencia']['name']) && ($_FILES['imagen_evidencia']['error'] !== UPLOAD_ERR_NO_FILE)) {
            $file = $_FILES['imagen_evidencia'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errores[] = 'Error al subir la imagen.';
            } else {
                $maxSize = 3 * 1024 * 1024;
                if ($file['size'] > $maxSize) $errores[] = 'La imagen excede 3MB.';
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($file['tmp_name']);
                $map = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif'];
                if (!isset($map[$mime])) $errores[] = 'Formato inválido. Solo JPG, PNG o GIF.';
                if (empty($errores)) {
                    $name = 'paciente_'.$paciente_id.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'.'.$map[$mime];
                    $dst  = $uploadDir.'/'.$name;
                    if (move_uploaded_file($file['tmp_name'], $dst)) {
                        $imagenEvidencia = 'uploads/ejercicios/'.$name;
                    } else {
                        $errores[] = 'No se pudo guardar la imagen.';
                    }
                }
            }
        }

        if (empty($errores)) {
            $sql = "INSERT INTO ejercicios ({$FK}, fecha, tipo_ejercicio, tiempo, hora, imagen_evidencia, notas)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmtI = $conexion->prepare($sql);
            $stmtI->bind_param('ississs', $paciente_id, $fecha, $tipo, $tiempo, $hora, $imagenEvidencia, $notas);
            if ($stmtI->execute()) {
                $exito = 'Registro guardado correctamente.';
                $_POST = []; // limpiar
            } else {
                $errores[] = 'Error al guardar en BD: '.$stmtI->error;
            }
            $stmtI->close();
        }
    }
}

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

// ---------------- Filtros historial ----------------
$vista = (isset($_GET['vista']) && $_GET['vista']==='semanal') ? 'semanal' : 'diaria';
$hoy = date('Y-m-d');
$fechaFiltro = (isset($_GET['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha'])) ? $_GET['fecha'] : $hoy;

$historial = [];
if ($vista === 'diaria') {
    $sqlH = "SELECT id_ejercicio, fecha, tipo_ejercicio, tiempo, hora, imagen_evidencia, notas
             FROM ejercicios WHERE {$FK}=? AND fecha=? ORDER BY hora ASC, id_ejercicio ASC";
    $stmtH = $conexion->prepare($sqlH);
    $stmtH->bind_param('is', $paciente_id, $fechaFiltro);
    $stmtH->execute(); $r = $stmtH->get_result();
    while ($row = $r->fetch_assoc()) $historial[] = $row;
    $stmtH->close();
} else {
    $ts = strtotime($fechaFiltro);
    $dow = (int)date('N', $ts);
    $ini = date('Y-m-d', strtotime('-'.($dow-1).' days', $ts));
    $fin = date('Y-m-d', strtotime('+'.(7-$dow).' days', $ts));

    $sqlH = "SELECT id_ejercicio, fecha, tipo_ejercicio, tiempo, hora, imagen_evidencia, notas
             FROM ejercicios WHERE {$FK}=? AND fecha BETWEEN ? AND ?
             ORDER BY fecha ASC, hora ASC, id_ejercicio ASC";
    $stmtH = $conexion->prepare($sqlH);
    $stmtH->bind_param('iss', $paciente_id, $ini, $fin);
    $stmtH->execute(); $r = $stmtH->get_result();
    while ($row = $r->fetch_assoc()) $historial[] = $row;
    $stmtH->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Seguimiento de Ejercicios</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    body{background:#f5f7fb}
    .card{border:none;box-shadow:0 2px 8px rgba(0,0,0,.06)}
    .preview{max-height:90px;border-radius:6px;border:1px solid #dee2e6}
  </style>
</head>
<body>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0"><i class="bi bi-activity text-primary me-2"></i>Seguimiento de Ejercicios</h2>
    </div>

    <?php if ($errores): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errores as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
    <?php endif; ?>
    <?php if ($exito): ?>
      <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($exito) ?></div>
    <?php endif; ?>

    <div class="card mb-3">
      <div class="card-header bg-primary text-white"><strong><i class="bi bi-plus-circle me-2"></i>Nuevo Registro</strong></div>
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label"><i class="bi bi-calendar me-1"></i>Fecha</label>
              <input type="date" class="form-control" name="fecha" value="<?= htmlspecialchars($hoy) ?>" required>
            </div>
            <div class="col-md-3">
              <label class="form-label"><i class="bi bi-clock me-1"></i>Hora</label>
              <input type="time" class="form-control" name="hora" required>
            </div>
            <div class="col-md-3">
              <label class="form-label"><i class="bi bi-tag me-1"></i>Tipo</label>
              <select class="form-select" name="tipo_ejercicio" required>
                <option value="">Seleccione...</option>
                <option>Caminata</option><option>Correr</option><option>Natación</option>
                <option>Ciclismo</option><option>Gimnasio</option><option>Yoga</option><option>Otro</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label"><i class="bi bi-stopwatch me-1"></i>Tiempo (min)</label>
              <input type="number" class="form-control" name="tiempo" min="1" required>
            </div>
            <div class="col-12">
              <label class="form-label"><i class="bi bi-card-text me-1"></i>Notas</label>
              <textarea class="form-control" name="notas" rows="3" required></textarea>
            </div>
            <div class="col-12">
              <label class="form-label"><i class="bi bi-camera me-1"></i>Foto (opcional)</label>
              <input type="file" class="form-control" name="imagen_evidencia" accept="image/jpeg,image/png,image/gif">
              <small class="text-muted">JPG, PNG o GIF. Máx 3MB.</small>
            </div>
          </div>
          <div class="mt-3">
            <button class="btn btn-primary"><i class="bi bi-save me-2"></i>Guardar</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header bg-primary text-white"><strong><i class="bi bi-list me-2"></i>Historial <?= $vista==='semanal'?'Semanal':'Diario' ?></strong></div>
      <div class="card-body">
        <form class="row g-3 mb-3" method="get">
          <input type="hidden" name="id" value="<?= (int)$paciente_id ?>">
          <div class="col-md-4">
            <label class="form-label">Vista</label>
            <select class="form-select" name="vista">
              <option value="diaria" <?= $vista==='diaria'?'selected':'' ?>>Diaria</option>
              <option value="semanal" <?= $vista==='semanal'?'selected':'' ?>>Semanal</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Fecha base</label>
            <input type="date" class="form-control" name="fecha" value="<?= htmlspecialchars($fechaFiltro) ?>">
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <button class="btn btn-primary">Aplicar</button>
          </div>
        </form>

        <?php if (!$historial): ?>
          <p class="text-muted mb-0">No hay registros para el periodo seleccionado.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-striped align-middle">
              <thead><tr><?= $vista==='semanal'?'<th>Fecha</th>':'' ?><th>Hora</th><th>Tipo</th><th>Tiempo</th><th>Notas</th><th>Foto</th><th>Acciones</th></tr></thead>
              <tbody>
                <?php foreach ($historial as $row): ?>
                  <tr>
                    <?= $vista==='semanal' ? '<td>'.htmlspecialchars($row['fecha']).'</td>' : '' ?>
                    <td><?= htmlspecialchars(substr($row['hora'],0,5)) ?></td>
                    <td><?= htmlspecialchars(ucfirst($row['tipo_ejercicio'])) ?></td>
                    <td><?= (int)$row['tiempo'] ?> min</td>
                    <td><?= nl2br(htmlspecialchars($row['notas'])) ?></td>
                    <td>
                      <?php if (!empty($row['imagen_evidencia'])): ?>
                        <a href="<?= htmlspecialchars($row['imagen_evidencia']) ?>" target="_blank">
                          <img class="preview" src="<?= htmlspecialchars($row['imagen_evidencia']) ?>" alt="foto">
                        </a>
                      <?php else: ?><span class="text-muted">Sin foto</span><?php endif; ?>
                    </td>
                    <td>
                      <form method="post" onsubmit="return confirm('¿Eliminar este registro?');" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="delete_id" value="<?= (int)$row['id_ejercicio'] ?>">
                        <button class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button>
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
      <div class="card-header bg-primary text-white"><strong><i class="bi bi-images me-2"></i>Galería de Ejercicios Registrados</strong></div>
      <div class="card-body">
        <?php
        $galeria = [];
        $sqlG = "SELECT fecha, tipo_ejercicio, tiempo, hora, imagen_evidencia, notas FROM ejercicios
                 WHERE {$FK}=? AND imagen_evidencia IS NOT NULL ORDER BY fecha DESC, hora DESC";
        $stmtG = $conexion->prepare($sqlG);
        $stmtG->bind_param('i', $paciente_id);
        $stmtG->execute(); $rg = $stmtG->get_result();
        while ($row = $rg->fetch_assoc()) $galeria[] = $row;
        $stmtG->close();
        ?>
        <?php if (!$galeria): ?>
          <p class="text-muted mb-0">No hay fotos de ejercicios registradas aún.</p>
        <?php else: ?>
          <div class="row g-3">
            <?php foreach ($galeria as $g): ?>
              <div class="col-sm-6 col-md-4 col-lg-3">
                <div class="card h-100">
                  <img src="<?= htmlspecialchars($g['imagen_evidencia']) ?>" class="card-img-top" style="height:160px;object-fit:cover" alt="Foto">
                  <div class="card-body p-2">
                    <div class="fw-semibold text-primary"><?= htmlspecialchars($g['tipo_ejercicio']) ?></div>
                    <div class="small text-muted"><?= htmlspecialchars($g['fecha']) ?> · <?= htmlspecialchars(substr($g['hora'],0,5)) ?> · <?= (int)$g['tiempo'] ?> min</div>
                    <div class="small mt-1"><?= nl2br(htmlspecialchars($g['notas'])) ?></div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
<?php $conexion->close(); ?>
