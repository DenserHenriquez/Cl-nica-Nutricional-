<?php
session_start();
if (!isset($_SESSION['id_usuarios'])) { header('Location: index.php'); exit; }
require_once __DIR__ . '/db_connection.php';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$userId = (int)($_SESSION['id_usuarios'] ?? 0);
$userRole = $_SESSION['rol'] ?? '';
$noRegistrado = false;
// Obtener id_pacientes del usuario si es Paciente
$currentPatientId = 0;
if ($userRole === 'Paciente') {
	if ($stPid = $conexion->prepare('SELECT id_pacientes FROM pacientes WHERE id_usuarios = ? LIMIT 1')) {
		$stPid->bind_param('i', $userId);
		$stPid->execute();
		$stPid->bind_result($pidDb);
		if ($stPid->fetch()) { $currentPatientId = (int)$pidDb; }
		$stPid->close();
	}
	// Si no existe paciente relacionado, mostrar aviso y ocultar UI (sin redirección)
	if ($currentPatientId <= 0) { $noRegistrado = true; }
}
$nutriId = $userId; // Se mantiene para nutricionista

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
		// Bloquear si es paciente no registrado
		if ($userRole === 'Paciente' && $currentPatientId <= 0) { echo json_encode(['ok'=>false,'error'=>'Paciente no registrado']); exit; }
		$pid = (int)($_GET['id'] ?? 0);
		// Forzar id propio si es paciente
		if ($userRole === 'Paciente') { $pid = $currentPatientId; }
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
		if ($st = $conexion->prepare('SELECT fecha, hora, tipo_comida, descripcion, foto_path FROM alimentos_registro WHERE id_pacientes=? ORDER BY fecha DESC, hora DESC LIMIT 8')){
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
		// Bloquear si es paciente no registrado
		if ($userRole === 'Paciente' && $currentPatientId <= 0) { echo json_encode(['ok'=>false,'error'=>'Paciente no registrado']); exit; }
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

// Consulta para tarjetas:
// - Si nutricionista/administrador: último registro de cada paciente (como antes)
// - Si paciente: todos sus registros (más recientes primero)
$cards = [];
if ($userRole === 'Paciente') {
	if ($st = $conexion->prepare('SELECT ar.id_pacientes, p.nombre_completo, ar.fecha, ar.hora, ar.descripcion, ar.foto_path
								  FROM alimentos_registro ar
								  INNER JOIN pacientes p ON p.id_pacientes = ar.id_pacientes
								  WHERE ar.id_pacientes = ?
								  ORDER BY ar.fecha DESC, ar.hora DESC LIMIT 50')) {
		$st->bind_param('i', $currentPatientId);
		$st->execute();
		$r = $st->get_result();
		while ($row = $r->fetch_assoc()) { $cards[] = $row; }
		$st->close();
	}
} else {
	$sqlLast = "SELECT p.id_pacientes, p.nombre_completo,
						ar.fecha, ar.hora, ar.descripcion, ar.foto_path
					FROM pacientes p
					LEFT JOIN (
						SELECT ar1.* FROM alimentos_registro ar1
						JOIN (
							SELECT id_pacientes, MAX(CONCAT(fecha,' ',hora)) AS maxdt
							FROM alimentos_registro GROUP BY id_pacientes
						) t ON t.id_pacientes = ar1.id_pacientes AND CONCAT(ar1.fecha,' ',ar1.hora)=t.maxdt
					) ar ON ar.id_pacientes = p.id_pacientes
					ORDER BY ar.fecha DESC, ar.hora DESC, p.nombre_completo ASC";
	if ($res = $conexion->query($sqlLast)) {
		while ($row = $res->fetch_assoc()) { $cards[] = $row; }
		$res->close();
	}
}

// Cálculo de inactividad para roles distintos a Paciente
$inactivos = [];
if ($userRole !== 'Paciente') {
	// Detectar FK en ejercicios (paciente_id o id_pacientes)
	$exFk = 'paciente_id';
	$resCol = $conexion->query("SHOW COLUMNS FROM ejercicios LIKE 'paciente_id'");
	if (!$resCol || $resCol->num_rows === 0) { $exFk = 'id_pacientes'; }
	if ($resCol) { $resCol->close(); }

	// Mapas de últimas fechas de actividad
	$lastFoodMap = [];
	if ($resLF = $conexion->query("SELECT id_pacientes, MAX(CONCAT(fecha,' ',hora)) AS last_food_dt FROM alimentos_registro GROUP BY id_pacientes")) {
		while ($r = $resLF->fetch_assoc()) { $lastFoodMap[(int)$r['id_pacientes']] = $r['last_food_dt']; }
		$resLF->close();
	}
	$lastExMap = [];
	if ($resLE = $conexion->query("SELECT {$exFk} AS id_pacientes, MAX(CONCAT(fecha,' ',hora)) AS last_ex_dt FROM ejercicios GROUP BY {$exFk}")) {
		while ($r = $resLE->fetch_assoc()) { $lastExMap[(int)$r['id_pacientes']] = $r['last_ex_dt']; }
		$resLE->close();
	}
	$nowTs = time();
	$thresholdDays = 3; // umbral de inactividad (días)
	foreach ($cards as $idx => $c) {
		$pid = (int)($c['id_pacientes'] ?? 0);
		if ($pid <= 0) continue;
		$lfdt = $lastFoodMap[$pid] ?? null;
		$ledt = $lastExMap[$pid] ?? null;
		$daysFood = is_null($lfdt) ? null : max(0, floor(($nowTs - strtotime($lfdt)) / 86400));
		$daysEx   = is_null($ledt) ? null : max(0, floor(($nowTs - strtotime($ledt)) / 86400));
		$cards[$idx]['last_food_dt'] = $lfdt;
		$cards[$idx]['last_ex_dt'] = $ledt;
		$cards[$idx]['days_food'] = $daysFood;
		$cards[$idx]['days_ex'] = $daysEx;
		$motivos = [];
		if (is_null($lfdt) || $daysFood >= $thresholdDays) {
			$motivos[] = is_null($lfdt) ? 'sin registros de alimentos' : ('sin alimentos hace ' . $daysFood . ' días');
		}
		if (is_null($ledt) || $daysEx >= $thresholdDays) {
			$motivos[] = is_null($ledt) ? 'sin registros de ejercicio' : ('sin ejercicio hace ' . $daysEx . ' días');
		}
		if ($motivos) {
			$inactivos[] = [
				'id_pacientes'    => $pid,
				'nombre_completo' => $c['nombre_completo'] ?? 'Paciente',
				'motivo'          => implode(' • ', $motivos),
				'days_food'       => $daysFood,
				'days_ex'         => $daysEx,
			];
		}
	}
}
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width,initial-scale=1" />
	<?php $tituloPrincipal = ($_SESSION['rol'] ?? '') === 'Paciente' ? 'Gestión de Comidas' : 'Retroalimentación'; ?>
	<title><?= h($tituloPrincipal) ?> | Actividad reciente</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
	<style>
		body{background:#f8f9fa}
		.content{padding:22px; max-width:1200px; margin:0 auto}
		.header-section {
			background: linear-gradient(135deg, #198754 0%, #146c43 100%);
			color: white;
			/* Reduced height: ~60% */
			padding: 0.8rem 0;
			margin-bottom: 1rem;
		}
		.header-section h1 {
			font-size: 2.2rem;
			font-weight: 700;
			margin: 0.15rem 0 0.25rem;
		}
		.header-section p {
			font-size: 1.05rem;
			opacity: 0.95;
			margin: 0;
		}
		.medical-icon {
			font-size: 1.9rem;
			margin-bottom: 0.35rem;
			color: #ffffff;
		}
		.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px}
		.cardx{background:#fff;border:1px solid #e9eef6;border-radius:12px;overflow:hidden;box-shadow:0 4px 14px rgba(13,110,253,.06)}
		.cardx img{width:100%;height:140px;object-fit:cover}
		.cardx .info{padding:10px}
		.cardx .name{font-weight:700}
		.cardx .meta{color:#6c757d;font-size:.85rem}
		.btn-circle{width:36px;height:36px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%}

		/* Instagram-like modal redesign */
		.modal-content{border-radius:14px;}
		.insta-post{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;}
		.insta-post .post-header{display:flex;align-items:center;gap:.75rem;padding-bottom:.5rem;border-bottom:1px solid #e9eef6;}
		.insta-post .avatar{width:44px;height:44px;border-radius:50%;background:#dee2e6;display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:#495057;overflow:hidden;}
		.insta-post .main-img{width:100%;max-height:440px;object-fit:cover;border-radius:12px;box-shadow:0 4px 14px rgba(0,0,0,.08);background:#e9ecef;}
		.insta-post .thumbs{display:flex;flex-wrap:wrap;gap:6px;}
		.insta-post .thumbs img{width:64px;height:64px;object-fit:cover;border-radius:10px;border:1px solid #e9eef6;}
		.insta-post .measure-list{list-style:none;padding-left:0;margin:0;font-size:.8rem;}
		.insta-post .measure-list li{padding:2px 0;border-bottom:1px solid #f1f3f5;}
		.insta-post .comment{font-size:.9rem;line-height:1.3;}
		.insta-post .comment .meta{font-size:.7rem;color:#6c757d;margin-bottom:2px;}
		#mdNotes .comment + .comment{border-top:1px solid #e9eef6;margin-top:.6rem;padding-top:.6rem;}
		.comment-form textarea{resize:none;border-radius:30px;padding:.65rem 1rem;background:#f8f9fa;}
		.comment-form textarea:focus{background:#fff;}
		.comment-form .btn{border-radius:30px;padding:.55rem 1.1rem;}
		@media (min-width:768px){.modal-lg{max-width:760px;}}
		.post-image{position:relative;}
		.nav-arrow{position:absolute;top:50%;transform:translateY(-50%);width:42px;height:42px;border-radius:50%;background:rgba(0,0,0,.35);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1.2rem;transition:.25s;user-select:none;}
		.nav-arrow:hover{background:rgba(0,0,0,.55);} 
		.nav-arrow.prev{left:10px;} .nav-arrow.next{right:10px;}
		.nav-arrow.disabled{opacity:.25;pointer-events:none;}
		.thumbs img{cursor:pointer;transition:.25s;}
		.thumbs img:hover{filter:brightness(.85);} 
		.thumbs img.active{outline:2px solid #198754;}
	</style>
	</head>
<body>
	<!-- Header Section -->
	<div class="header-section">
		<div class="container text-center">
			<div class="medical-icon">
				<i class="bi bi-chat-dots"></i>
			</div>
			<h1><?= ($_SESSION['rol'] ?? '') === 'Paciente' ? 'Gestión de Comidas' : 'Retroalimentación de Pacientes'; ?></h1>
			<p><?= ($_SESSION['rol'] ?? '') === 'Paciente' ? 'El médico gestionará tu alimentación y te brindará retroalimentación personalizada.' : 'Nutricionista | Monitorea y retroalimenta a tus pacientes.'; ?></p>
		</div>
	</div>

	<main class="content">
		<?php if (!($noRegistrado && ($userRole === 'Paciente'))): ?>
		<div class="section-title">
			<h4 class="mb-0"><?= ($_SESSION['rol'] ?? '') === 'Paciente' ? 'Mis Registros de Comidas' : 'Actividad reciente de los pacientes'; ?></h4>
		</div>
		<?php if(($_SESSION['rol'] ?? '') !== 'Paciente' && !empty($inactivos)): ?>
		<div class="alert alert-warning mt-3">
			<i class="bi bi-bell-exclamation me-1"></i>
			Pacientes con inactividad reciente (≥ 3 días):
			<ul class="mb-0 mt-2">
				<?php foreach ($inactivos as $ia): ?>
				<li>
					<strong><?= h($ia['nombre_completo']) ?></strong> — <?= h($ia['motivo']) ?>
					<button class="btn btn-link btn-sm p-0 ms-2" onclick="openModal(<?= (int)$ia['id_pacientes'] ?>, '<?= h(addslashes($ia['nombre_completo'])) ?>')">ver</button>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>
		<?php endif; ?>

		<?php if (!empty($noRegistrado) && ($userRole === 'Paciente')): ?>
			<div class="alert alert-warning">
				<i class="bi bi-exclamation-triangle-fill"></i>
				Paciente nuevo: primero necesitas actualizar tus datos con tu médico tratante. Si aún no estás registrado como paciente en la clínica, ponte en contacto con el personal o tu médico para completar tu registro.
			</div>
		<?php else: ?>
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
									<?php if(($_SESSION['rol'] ?? '') !== 'Paciente'): ?>
									<div class="mt-1">
									<?php if (!isset($c['days_food']) || is_null($c['days_food'])): ?>
									<span class="badge bg-secondary me-1">Alimentos: nunca</span>
									<?php elseif ($c['days_food'] >= 7): ?>
									<span class="badge bg-danger me-1">Alimentos: <?= (int)$c['days_food'] ?>d</span>
									<?php elseif ($c['days_food'] >= 3): ?>
									<span class="badge bg-warning text-dark me-1">Alimentos: <?= (int)$c['days_food'] ?>d</span>
									<?php endif; ?>
									
									<?php if (!isset($c['days_ex']) || is_null($c['days_ex'])): ?>
									<span class="badge bg-secondary me-1">Ejercicio: nunca</span>
									<?php elseif ($c['days_ex'] >= 7): ?>
									<span class="badge bg-danger me-1">Ejercicio: <?= (int)$c['days_ex'] ?>d</span>
									<?php elseif ($c['days_ex'] >= 3): ?>
									<span class="badge bg-warning text-dark me-1">Ejercicio: <?= (int)$c['days_ex'] ?>d</span>
									<?php endif; ?>
									</div>
									<?php endif; ?>
								</div>
								<button class="btn btn-primary btn-circle" title="Comentar" onclick="openModal(<?= $pid ?>, '<?= h(addslashes($c['nombre_completo'])) ?>')"><i class="bi bi-chat-dots"></i></button>
							</div>
							<?php if (!empty($c['descripcion'])): ?><div class="mt-2" style="color:#495057; font-size:.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">"<?= h($c['descripcion']) ?>"</div><?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php endif; ?>
	</main>

<!-- Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title d-flex align-items-center"><i class="bi bi-person-heart me-2"></i><span id="mdName">Paciente</span></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="insta-post">
					<div class="post-header">
						<div class="avatar" id="mdAvatar"><i class="bi bi-person-fill"></i></div>
						<div class="flex-grow-1">
							<div id="mdNameH" class="fw-semibold"></div>
							<div id="mdData" class="small text-muted"></div>
						</div>
					</div>
					<div class="post-image mt-3">
						<div class="nav-arrow prev" id="mdPrev" title="Anterior"><i class="bi bi-chevron-left"></i></div>
						<div class="nav-arrow next" id="mdNext" title="Siguiente"><i class="bi bi-chevron-right"></i></div>
						<img id="mdMainImg" class="main-img" src="https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=800&q=80" alt="actividad" />
					</div>
					<div class="post-meta mt-3">
						<h6 class="mb-1">Últimas medidas</h6>
						<ul id="mdExp" class="measure-list"></ul>
						<h6 class="mt-3 mb-1">Más comidas</h6>
						<div id="mdAli" class="thumbs"></div>
					</div>
					<div class="post-comments mt-4" id="mdNotes"></div>
					<form id="mdForm" class="comment-form d-flex align-items-center mt-3 gap-2">
						<input type="hidden" name="action" value="comment" />
						<input type="hidden" name="id_pacientes" id="mdPid" />
						<textarea name="comentario" class="form-control" rows="1" placeholder="Añade un comentario..."></textarea>
						<button class="btn btn-primary" type="submit"><i class="bi bi-send"></i></button>
					</form>
					<div class="d-flex align-items-center mt-2">
						<label class="form-check small mb-0">
							<input class="form-check-input" type="checkbox" name="notificar" value="1" /> Notificar al paciente
						</label>
						<div id="mdMsg" class="ms-3 flex-grow-1"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let bsModal;
let mdImages = []; // array de rutas de imágenes
let mdIndex = 0;   // índice actual mostrado

function clearModalContent(){
	document.getElementById('mdExp').innerHTML='';
	document.getElementById('mdAli').innerHTML='';
	document.getElementById('mdNotes').innerHTML='';
	document.getElementById('mdMainImg').src='https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=800&q=80';
	mdImages=[]; mdIndex=0; updateArrows();
}

function loadDetails(pid){
	clearModalContent();
	fetch('retroalimentacion1.php?action=details&id='+pid)
		.then(r=>r.json())
		.then(d=>{
			if(!d.ok) throw new Error(d.error||'Error');
			const p = d.paciente || {};
			document.getElementById('mdData').textContent = `DNI: ${p.DNI||''} • Edad: ${p.edad||''} • Tel: ${p.telefono||''}`;
			document.getElementById('mdNameH').textContent = (p.nombre_completo||'Paciente');
			// Medidas
			const exp = d.expediente||[]; const ul = document.getElementById('mdExp');
			if(exp.length===0){ ul.innerHTML = '<li class="text-muted">Sin datos</li>'; }
			exp.forEach(e=>{
				const li=document.createElement('li');
				li.textContent = `${e.fecha_registro||''} • Peso: ${e.peso||''} kg • IMC: ${e.IMC||''}`;
				ul.appendChild(li);
			});
			// Alimentos (carousel)
			const ali = d.alimentos||[]; const thumbs=document.getElementById('mdAli');
			mdImages = ali.map(a=> (a.foto_path||'').length>0 ? a.foto_path : 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=400&q=70');
			if(mdImages.length===0){ thumbs.innerHTML = '<div class="text-muted">Sin registros</div>'; }
			else {
				mdIndex=0; document.getElementById('mdMainImg').src = mdImages[0];
				mdImages.forEach((src,i)=>{
					if(i>0){
						const img=document.createElement('img'); img.alt='comida'; img.src=src; img.dataset.index=i;
						if(i===mdIndex) img.classList.add('active');
						img.addEventListener('click',()=>{ showImage(i); });
						thumbs.appendChild(img);
					}
				});
			}
			updateArrows();
			// Comentarios
			const notes = d.comentarios||[]; const box=document.getElementById('mdNotes');
			if(notes.length===0){ box.innerHTML = '<div class="text-muted">Sé el primero en comentar</div>'; }
			notes.forEach(n=>{
				const div=document.createElement('div'); div.className='comment';
				div.innerHTML = `<div class='meta'>${n.nutricionista||'Nutricionista'} • ${n.creado_at||''} ${n.notificar?'<span class=\'ms-1 text-primary\'>Notificado</span>':''}</div><div>${(n.comentario||'').replaceAll('\n','<br/>')}</div>`;
				box.appendChild(div);
			});
		})
		.catch(err=>{ document.getElementById('mdMsg').innerHTML = `<div class='alert alert-danger'>${err.message}</div>`; });
}

function openModal(pid, name){
	document.getElementById('mdName').textContent = name;
	document.getElementById('mdNameH').textContent = name;
	document.getElementById('mdPid').value = pid;
	document.getElementById('mdMsg').textContent = '';
	document.querySelector('#mdForm textarea').value = '';
	loadDetails(pid);
	if(!bsModal){ bsModal = new bootstrap.Modal(document.getElementById('feedbackModal')); }
	bsModal.show();
}

// Navegación de imágenes
function showImage(i){
	if(i<0 || i>=mdImages.length) return;
	mdIndex=i; document.getElementById('mdMainImg').src=mdImages[mdIndex];
	// actualizar activos thumbnails
	const thumbs=document.querySelectorAll('#mdAli img'); thumbs.forEach(img=>{
		img.classList.toggle('active', parseInt(img.dataset.index,10)===mdIndex);
	});
	updateArrows();
}

function updateArrows(){
	const prev=document.getElementById('mdPrev');
	const next=document.getElementById('mdNext');
	if(!prev||!next) return;
	if(mdImages.length<=1){ prev.style.display='none'; next.style.display='none'; return; }
	prev.style.display=''; next.style.display='';
	prev.classList.toggle('disabled', mdIndex===0);
	next.classList.toggle('disabled', mdIndex===mdImages.length-1);
}

document.addEventListener('click', function(e){
	if(e.target.closest('#mdPrev')){ showImage(mdIndex-1); }
	if(e.target.closest('#mdNext')){ showImage(mdIndex+1); }
});

// Envío de comentario vía AJAX
const mdForm = document.getElementById('mdForm');
mdForm.addEventListener('submit', function(ev){
	ev.preventDefault();
	const fd = new FormData(mdForm);
	fetch('retroalimentacion1.php', { method:'POST', body:fd })
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
