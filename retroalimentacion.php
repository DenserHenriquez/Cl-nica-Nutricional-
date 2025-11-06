<?php
session_start();
if (!isset($_SESSION['id_usuarios'])) { header('Location: index.php'); exit; }
require_once __DIR__ . '/db_connection.php';
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$nutri_id = (int)($_SESSION['id_usuarios'] ?? 0);
$mensaje = '';
$errores = [];

// Asegurar existencia de tabla simple para comentarios (si no existe)
$createTbl = "CREATE TABLE IF NOT EXISTS retroalimentacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pacientes INT NOT NULL,
    id_nutricionista INT NOT NULL,
    comentario TEXT NOT NULL,
    notificar TINYINT(1) DEFAULT 0,
    creado_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conexion->query($createTbl);

// Lista de pacientes para seleccionar
$pacientes = [];
$res = $conexion->query("SELECT id_pacientes, nombre_completo FROM pacientes ORDER BY nombre_completo ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) $pacientes[] = $r;
    $res->close();
}

$idPaciente = isset($_GET['id']) ? (int)$_GET['id'] : (count($pacientes)>0 ? (int)$pacientes[0]['id_pacientes'] : 0);

// POST: guardar comentario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comentario']) && $idPaciente>0) {
    $coment = trim($_POST['comentario']);
    $notificar = isset($_POST['notificar']) ? 1 : 0;
    if ($coment === '') { $errores[] = 'El comentario no puede estar vacío.'; }
    if (empty($errores)) {
        $stmt = $conexion->prepare("INSERT INTO retroalimentacion (id_pacientes, id_nutricionista, comentario, notificar) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('iisi', $idPaciente, $nutri_id, $coment, $notificar);
            if ($stmt->execute()) {
                $mensaje = 'Retroalimentación guardada correctamente.';
                // Intento simple de notificación: crear registro en tabla notificaciones si existe
                if ($notificar) {
                    $sqlN = "INSERT INTO notificaciones (id_usuario_destino, tipo, mensaje, creado_at) SELECT id_usuarios, 'retroalimentacion', CONCAT('Nueva retroalimentación: ', LEFT(?,200)), NOW() FROM pacientes WHERE id_pacientes = ? LIMIT 1";
                    // usar prepared si la tabla existe; si no, ignorar
                    if ($conexion->query("SHOW TABLES LIKE 'notificaciones'")->num_rows) {
                        $stmtN = $conexion->prepare($sqlN);
                        if ($stmtN) { $stmtN->bind_param('si', $coment, $idPaciente); $stmtN->execute(); $stmtN->close(); }
                    }
                }
            } else {
                $errores[] = 'Error al guardar: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $errores[] = 'Error preparando consulta: ' . $conexion->error;
        }
    }
}

// Cargar datos del paciente seleccionado
$paciente = null;
if ($idPaciente>0) {
    $stmt = $conexion->prepare("SELECT id_pacientes, nombre_completo, DNI, fecha_nacimiento, edad, telefono FROM pacientes WHERE id_pacientes = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $idPaciente);
        $stmt->execute();
        $res = $stmt->get_result();
        $paciente = $res->fetch_assoc();
        $stmt->close();
    }
}

// cargar expediente (medidas) y comentarios históricos
$expediente = [];
if ($idPaciente>0) {
    if ($stmt = $conexion->prepare("SELECT fecha_registro, peso, estatura, IMC, masa_muscular, medicamentos, enfermedades_base FROM expediente WHERE id_pacientes = ? ORDER BY fecha_registro DESC LIMIT 20")) {
        $stmt->bind_param('i', $idPaciente);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $expediente[] = $r;
        $stmt->close();
    }
}

$comentarios = [];
if ($idPaciente>0) {
    if ($stmt = $conexion->prepare("SELECT r.id, r.comentario, r.notificar, r.creado_at, u.Nombre_completo AS nutricionista FROM retroalimentacion r LEFT JOIN usuarios u ON u.id_usuarios = r.id_nutricionista WHERE r.id_pacientes = ? ORDER BY r.creado_at DESC")) {
        $stmt->bind_param('i', $idPaciente);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $comentarios[] = $r;
        $stmt->close();
    }
}

// Buscar fotos subidas en carpeta uploads/pacientes/{id}
$photos = [];
$uploadDir = __DIR__ . '/uploads/pacientes/' . $idPaciente;
if (is_dir($uploadDir)) {
    foreach (scandir($uploadDir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) $photos[] = 'uploads/pacientes/'.$idPaciente.'/'.$f;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Retroalimentación · Clínica Nutricional</title>
<link rel="stylesheet" href="assets/css/estilos.css">
<style>
:root{ --primary-900:#0d47a1; --primary-700:#1565c0; --muted:#6b7280; --white:#fff; }
*{box-sizing:border-box}
body{ font-family:'Segoe UI', Roboto, Arial; margin:0; background:linear-gradient(180deg,#f7fbff,#f3f8ff); color:#0f1724}
/* Topbar (igual que registro paciente) */
.topbar{ position:sticky; top:0; background:linear-gradient(90deg,var(--primary-900),var(--primary-700)); color:var(--white); box-shadow:0 6px 16px rgba(13,71,161,0.12) }
.topbar__inner{ max-width:1200px;margin:0 auto;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;gap:16px }
.brand{display:flex;align-items:center;gap:12px;font-weight:700}
.brand__logo{width:36px;height:36px;border-radius:50%;background:radial-gradient(120% 120% at 20% 20%,#42a5f5,#0d47a1);display:inline-flex;align-items:center;justify-content:center}
.brand__logo svg{width:18px;height:18px;fill:#fff}
.topbar__actions{display:flex;align-items:center;gap:10px}
.user-pill{display:inline-flex;align-items:center;gap:10px;padding:6px 10px;border-radius:999px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.12)}
.container{max-width:1100px;margin:28px auto;padding:20px;background:#fff;border-radius:12px;box-shadow:0 8px 24px rgba(13,71,161,0.06)}
.grid{display:grid;grid-template-columns:360px 1fr;gap:18px}
.card{background:#fbfdff;border:1px solid #e6eefb;padding:16px;border-radius:10px}
.form-row{display:flex;gap:8px;margin-bottom:10px}
.form-row > *{flex:1}
textarea,input,select{width:100%;padding:8px;border:1px solid #e6eefb;border-radius:8px;background:#fff}
.btn{background:var(--primary-700);color:#fff;padding:8px 12px;border-radius:8px;border:none;cursor:pointer}
.btn.ghost{background:#f1f5f9;color:#0f1724;border:1px solid #e6eefb}
.thumb{width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #e6eefb;margin-right:8px}
.meta{font-size:.9rem;color:var(--muted)}
.history-item{border-top:1px dashed #eef3fb;padding-top:12px;margin-top:12px}
.note-meta{font-size:.85rem;color:#475569;margin-bottom:6px}
</style>
</head>
<body>
<header class="topbar" role="banner">
  <div class="topbar__inner">
    <div class="brand" aria-label="Clínica Nutricional">
      <span class="brand__logo" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M10.5 3a1 1 0 0 0-1 1v5H4.5a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h5v5a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-5h5a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1h-5V4a1 1 0 0 0-1-1h-4z"/></svg></span>
      <span class="brand__name">Clínica Nutricional</span>
    </div>
    <div class="topbar__actions">
      <a href="Menuprincipal.php" class="user-pill">← Menú Principal</a>
      <div class="user-pill" title="<?= e($_SESSION['nombre'] ?? ($_SESSION['usuario'] ?? 'Usuario')) ?>">
        <div style="width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.2);display:inline-flex;align-items:center;justify-content:center;font-weight:800;margin-right:6px"><?= e(mb_strtoupper(mb_substr($_SESSION['nombre'] ?? ($_SESSION['usuario'] ?? 'U'),0,1,'UTF-8'))) ?></div>
        <div><?= e($_SESSION['nombre'] ?? ($_SESSION['usuario'] ?? 'Usuario')) ?></div>
      </div>
      <a href="Login.php" class="user-pill">Salir</a>
    </div>
  </div>
</header>

<div class="container">
  <div class="grid">
    <aside class="card">
      <h3>Seleccionar paciente</h3>
      <form method="get" action="">
        <select name="id" onchange="this.form.submit()">
          <?php foreach($pacientes as $p): ?>
            <option value="<?= (int)$p['id_pacientes'] ?>" <?= ((int)$p['id_pacientes'] === $idPaciente)?'selected':''; ?>><?= e($p['nombre_completo']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>

      <?php if($paciente): ?>
        <div style="margin-top:12px">
          <div class="meta"><strong><?= e($paciente['nombre_completo']) ?></strong></div>
          <div class="meta">DNI: <?= e($paciente['DNI']) ?></div>
          <div class="meta">Edad: <?= e($paciente['edad']) ?> • Tel: <?= e($paciente['telefono']) ?></div>
        </div>
      <?php endif; ?>

      <hr style="margin:12px 0;border:none;border-top:1px solid #eef3fb" />

      <h4>Fotos</h4>
      <div style="display:flex;flex-wrap:wrap">
        <?php if($photos): foreach($photos as $ph): ?>
          <a href="<?= e($ph) ?>" target="_blank"><img src="<?= e($ph) ?>" class="thumb" alt="foto"></a>
        <?php endforeach; else: ?>
          <div class="meta">No hay fotos cargadas.</div>
        <?php endif; ?>
      </div>

      <hr style="margin:12px 0;border:none;border-top:1px solid #eef3fb" />
      <h4>Últimas medidas</h4>
      <?php if($expediente): foreach($expediente as $ex): ?>
        <div class="meta" style="margin-bottom:8px"><strong><?= e($ex['fecha_registro']) ?></strong><div style="font-size:.9rem;color:#334155">Peso: <?= e($ex['peso']) ?> kg • IMC: <?= e($ex['IMC']) ?></div></div>
      <?php endforeach; else: ?>
        <div class="meta">Sin registros en expediente.</div>
      <?php endif; ?>
    </aside>

    <section class="card">
      <h3>Dejar retroalimentación</h3>

      <?php if(!empty($errores)): foreach($errores as $er): ?>
        <div style="background:#fff5f5;color:#b91c1c;padding:8px;border-radius:6px;margin-bottom:8px"><?= e($er) ?></div>
      <?php endforeach; endif; ?>
      <?php if($mensaje): ?><div style="background:#f0fdf4;color:#065f46;padding:8px;border-radius:6px;margin-bottom:8px"><?= e($mensaje) ?></div><?php endif; ?>

      <form method="post">
        <div class="form-row">
          <textarea name="comentario" rows="4" placeholder="Escribe recomendaciones, observaciones o plan de acción..."></textarea>
        </div>
        <div class="form-row" style="align-items:center">
          <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="notificar" value="1"> Notificar al paciente</label>
          <div style="margin-left:auto;display:flex;gap:8px">
            <a href="panelevolucionpaciente.php?id=<?= $idPaciente ?>" class="btn ghost">Ver historial paciente</a>
            <button type="submit" class="btn">Guardar</button>
          </div>
        </div>
      </form>

      <hr style="margin:14px 0;border:none;border-top:1px solid #eef3fb" />
      <h4>Historial de retroalimentación</h4>
      <?php if($comentarios): foreach($comentarios as $c): ?>
        <div class="history-item">
          <div class="note-meta"><strong><?= e($c['nutricionista'] ?? 'Nutricionista') ?></strong> • <?= e($c['creado_at']) ?> <?= $c['notificar'] ? '<span style="color:#0d47a1;margin-left:8px">• Notificado</span>' : '' ?></div>
          <div><?= nl2br(e($c['comentario'])) ?></div>
        </div>
      <?php endforeach; else: ?>
        <div class="meta">No hay retroalimentación aún.</div>
      <?php endif; ?>
    </section>
  </div>
</div>

</body>
</html>
<?php $conexion->close(); ?>