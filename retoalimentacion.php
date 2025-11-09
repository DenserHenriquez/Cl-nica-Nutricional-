<?php
session_start();
if (!isset($_SESSION['id_usuarios'])) { header('Location: index.php'); exit; }
require_once __DIR__ . '/db_connection.php';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$nutriId = (int)($_SESSION['id_usuarios'] ?? 0);

// Crear tabla de retroalimentación si no existe
$conexion->query("CREATE TABLE IF NOT EXISTS retroalimentacion (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_pacientes INT NOT NULL,
  id_nutricionista INT NOT NULL,
  comentario TEXT NOT NULL,
  notificar TINYINT(1) DEFAULT 0,
  creado_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handler AJAX: detalles para modal
if (isset($_GET['action']) && $_GET['action']==='details') {
    header('Content-Type: application/json');
    $pid = (int)($_GET['id'] ?? 0);
    if ($pid <= 0) { echo json_encode(['ok'=>false,'error'=>'Paciente inválido']); exit; }

    $paciente = null;
    if ($st = $conexion->prepare('SELECT id_pacientes, nombre_completo, DNI, edad, telefono FROM pacientes WHERE id_pacientes=? LIMIT 1')){
        $st->bind_param('i',$pid); $st->execute(); $r=$st->get_result(); $paciente = $r->fetch_assoc(); $st->close();
    }
    $exp = [];
    if ($st = $conexion->prepare('SELECT fecha_registro, peso, IMC FROM expediente WHERE id_pacientes=? ORDER BY fecha_registro DESC LIMIT 6')){
        $st->bind_param('i',$pid); $st->execute(); $r=$st->get_result(); while($row=$r->fetch_assoc()) $exp[]=$row; $st->close();
    }
    $ali = [];
    if ($st = $conexion->prepare('SELECT fecha, hora, tipo_comida, descripcion, foto_path FROM alimentos_registro WHERE paciente_id=? ORDER BY fecha DESC, hora DESC LIMIT 8')){
        $st->bind_param('i',$pid); $st->execute(); $r=$st->get_result(); while($row=$r->fetch_assoc()) $ali[]=$row; $st->close();
    } else if ($st = $conexion->prepare('SELECT fecha, hora, tipo_comida, descripcion, foto_path FROM alimentos_registro WHERE id_pacientes=? ORDER BY fecha DESC, hora DESC LIMIT 8')){
        $st->bind_param('i',$pid); $st->execute(); $r=$st->get_result(); while($row=$r->fetch_assoc()) $ali[]=$row; $st->close();
    }
    $notes = [];
    if ($st = $conexion->prepare('SELECT r.comentario, r.notificar, r.creado_at, u.Nombre_completo AS nutricionista FROM retroalimentacion r LEFT JOIN usuarios u ON u.id_usuarios = r.id_nutricionista WHERE r.id_pacientes=? ORDER BY r.creado_at DESC LIMIT 10')){
        $st->bind_param('i',$pid); $st->execute(); $r=$st->get_result(); while($row=$r->fetch_assoc()) $notes[]=$row; $st->close();
    }
    echo json_encode(['ok'=>true,'paciente'=>$paciente,'expediente'=>$exp,'alimentos'=>$ali,'comentarios'=>$notes], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    exit;
}

// Handler AJAX: guardar comentario
if (isset($_POST['action']) && $_POST['action']==='comment') {
    header('Content-Type: application/json');
    $pid = (int)($_POST['id_pacientes'] ?? 0);
    $coment = trim($_POST['comentario'] ?? '');
    $notificar = isset($_POST['notificar']) ? 1 : 0;
    if ($pid<=0 || $coment==='') { echo json_encode(['ok'=>false,'error'=>'Datos inválidos']); exit; }
    if ($st = $conexion->prepare('INSERT INTO retroalimentacion (id_pacientes, id_nutricionista, comentario, notificar) VALUES (?,?,?,?)')){
        $st->bind_param('iisi',$pid,$nutriId,$coment,$notificar);
        if ($st->execute()) {
            // Notificación si hay tabla notificaciones
            $resTab = $conexion->query("SHOW TABLES LIKE 'notificaciones'");
            if ($notificar && $resTab && $resTab->num_rows) {
                $sqlN = "INSERT INTO notificaciones (id_usuario_destino, tipo, mensaje, creado_at)
                         SELECT id_usuarios, 'retroalimentacion', CONCAT('Nueva retroalimentación: ', LEFT(?,200)), NOW()
                         FROM pacientes WHERE id_pacientes = ? LIMIT 1";
                if ($stN = $conexion->prepare($sqlN)) { $stN->bind_param('si',$coment,$pid); $stN->execute(); $stN->close(); }
            }
            echo json_encode(['ok'=>true]);
        } else { echo json_encode(['ok'=>false,'error'=>$st->error]); }
        $st->close();
    } else { echo json_encode(['ok'=>false,'error'=>$conexion->error]); }
    exit;
}

// Consulta para tarjetas: último registro de alimentos por paciente
$cards = [];
$sqlLast = "SELECT p.id_pacientes, p.nombre_completo,
                   ar.fecha, ar.hora, ar.descripcion, ar.foto_path
            FROM pacientes p
            LEFT JOIN (
              SELECT ar1.* FROM alimentos_registro ar1
              JOIN (
                SELECT paciente_id, MAX(CONCAT(fecha,' ',hora)) AS maxdt
                FROM alimentos_registro GROUP BY paciente_id
              ) t ON t.paciente_id = ar1.paciente_id AND CONCAT(ar1.fecha,' ',ar1.hora)=t.maxdt
            ) ar ON ar.paciente_id = p.id_pacientes
            ORDER BY ar.fecha DESC, ar.hora DESC, p.nombre_completo ASC";
if ($res = $conexion->query($sqlLast)) {
    while ($row = $res->fetch_assoc()) $cards[] = $row;
    $res->close();
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Retroalimentación | Actividad reciente</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <style>
    body{background:#f5f7fb}
    .content{padding:22px; max-width:1200px; margin:0 auto}
    .section-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
    .cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px}
    .cardx{background:#fff;border:1px solid #e9eef6;border-radius:12px;overflow:hidden;box-shadow:0 4px 14px rgba(13,110,253,.06)}
    .cardx img{width:100%;height:140px;object-fit:cover}
    .cardx .info{padding:10px}
    .cardx .name{font-weight:700}
    .cardx .meta{color:#6c757d;font-size:.85rem}
    .btn-circle{width:36px;height:36px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%}
  </style>
</head>
<body>
  <main class="content">
    <div class="section-title">
      <h4 class="mb-0">Actividad reciente de los pacientes</h4>
      <a class="btn btn-sm btn-outline-primary" href="retroalimentacion.php">Vista clásica</a>
    </div>
    <?php if (empty($cards)): ?>
      <div class="alert alert-secondary">No hay actividad reciente.</div>
    <?php else: ?>
      <div class="cards">
        <?php foreach ($cards as $c): $pid=(int)$c['id_pacientes']; ?>
          <div class="cardx">
            <?php if (!empty($c['foto_path']) && file_exists($c['foto_path'])): ?>
              <img src="<?= h($c['foto_path']) ?>" alt="alimento" />
            <?php else: ?>
              <img src="https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=800&q=80" alt="placeholder" />
            <?php endif; ?>
            <div class="info">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="name"><?= h($c['nombre_completo'] ?? 'Paciente') ?></div>
                  <div class="meta"><i class="bi bi-calendar3 me-1"></i><?= h($c['fecha'] ?? '') ?> <?= h($c['hora'] ?? '') ?></div>
                </div>
                <button class="btn btn-primary btn-circle" title="Comentar" onclick="openModal(<?= $pid ?>, '<?= h(addslashes($c['nombre_completo'])) ?>')"><i class="bi bi-chat-dots"></i></button>
              </div>
              <?php if (!empty($c['descripcion'])): ?><div class="mt-2" style="color:#495057; font-size:.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">"<?= h($c['descripcion']) ?>"</div><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

<!-- Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-heart me-2"></i><span id="mdName">Paciente</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="mdData" class="mb-3 text-muted"></div>
        <div class="row g-3">
          <div class="col-md-6">
            <h6>Últimas medidas</h6>
            <ul id="mdExp" class="list-group list-group-flush"></ul>
          </div>
          <div class="col-md-6">
            <h6>Últimas comidas</h6>
            <div id="mdAli" class="d-flex flex-wrap gap-2"></div>
          </div>
        </div>
        <hr/>
        <form id="mdForm">
          <input type="hidden" name="action" value="comment" />
          <input type="hidden" name="id_pacientes" id="mdPid" />
          <div class="mb-2"><textarea name="comentario" class="form-control" rows="3" placeholder="Escribe recomendaciones o comentarios..."></textarea></div>
          <div class="d-flex justify-content-between align-items-center">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="notificar" value="1" /> Notificar al paciente
            </label>
            <button class="btn btn-primary" type="submit"><i class="bi bi-send me-1"></i>Guardar</button>
          </div>
          <div id="mdMsg" class="mt-2"></div>
        </form>
        <hr/>
        <h6>Historial de comentarios</h6>
        <div id="mdNotes"></div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let bsModal;

function clearModalContent(){
  document.getElementById('mdExp').innerHTML = '';
  document.getElementById('mdAli').innerHTML = '';
  document.getElementById('mdNotes').innerHTML = '';
}

function loadDetails(pid){
  clearModalContent();
  fetch('retoalimentacion.php?action=details&id='+pid)
    .then(r=>r.json())
    .then(d=>{
      if(!d.ok) throw new Error(d.error||'Error');
      const p = d.paciente || {};
      document.getElementById('mdData').textContent = `DNI: ${p.DNI||''} • Edad: ${p.edad||''} • Tel: ${p.telefono||''}`;
      // Medidas
      const exp = d.expediente||[]; const ul = document.getElementById('mdExp');
      if(exp.length===0){ ul.innerHTML = '<li class="list-group-item text-muted">Sin datos</li>'; }
      exp.forEach(e=>{
        const li = document.createElement('li'); li.className='list-group-item';
        li.textContent = `${e.fecha_registro||''} • Peso: ${e.peso||''} kg • IMC: ${e.IMC||''}`; ul.appendChild(li);
      });
      // Alimentos
      const ali = d.alimentos||[]; const wrap=document.getElementById('mdAli');
      if(ali.length===0){ wrap.innerHTML = '<div class="text-muted">Sin registros</div>'; }
      ali.forEach(a=>{
        const img = document.createElement('img'); img.style.width='80px'; img.style.height='80px'; img.style.objectFit='cover'; img.style.borderRadius='8px'; img.style.border='1px solid #e9eef6';
        img.alt='foto'; img.src = (a.foto_path||'').length>0 ? a.foto_path : 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=200&q=60';
        wrap.appendChild(img);
      });
      // Comentarios
      const notes = d.comentarios||[]; const box=document.getElementById('mdNotes');
      if(notes.length===0){ box.innerHTML = '<div class="text-muted">Sin comentarios</div>'; }
      notes.forEach(n=>{
        const div=document.createElement('div'); div.className='border-top pt-2 mt-2';
        div.innerHTML = `<div style='font-size:.9rem;color:#6c757d'>${n.nutricionista||'Nutricionista'} • ${n.creado_at||''} ${n.notificar?'<span class=\'ms-2 text-primary\'>• Notificado</span>':''}</div><div>${(n.comentario||'').replaceAll('\n','<br/>')}</div>`;
        box.appendChild(div);
      });
    })
    .catch(err=>{ document.getElementById('mdMsg').innerHTML = `<div class='alert alert-danger'>${err.message}</div>`; });
}

function openModal(pid, name){
  document.getElementById('mdName').textContent = name;
  document.getElementById('mdPid').value = pid;
  document.getElementById('mdMsg').textContent = '';
  document.querySelector('#mdForm textarea').value = '';
  loadDetails(pid);
  if(!bsModal){ bsModal = new bootstrap.Modal(document.getElementById('feedbackModal')); }
  bsModal.show();
}

// Envío de comentario vía AJAX
const mdForm = document.getElementById('mdForm');
mdForm.addEventListener('submit', function(ev){
  ev.preventDefault();
  const fd = new FormData(mdForm);
  fetch('retoalimentacion.php', { method:'POST', body:fd })
    .then(r=>r.json())
    .then(d=>{
      if(!d.ok) throw new Error(d.error||'Error');
      document.getElementById('mdMsg').innerHTML = '<div class="alert alert-success py-1">Comentario guardado</div>';
      // Limpiar textarea y recargar SOLO el historial y datos sin reinstanciar modal
      document.querySelector('#mdForm textarea').value = '';
      loadDetails(document.getElementById('mdPid').value);
    })
    .catch(err=>{ document.getElementById('mdMsg').innerHTML = `<div class='alert alert-danger py-1'>${err.message}</div>`; });
});
</script>
</body>
</html>
