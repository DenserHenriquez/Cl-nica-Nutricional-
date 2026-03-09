<?php
// inicio.php - Dashboard inicial sensible al rol, sin barra superior (ya está en Menuprincipal)
// Muestra métricas básicas y botones de acciones rápidas según rol.

session_start();
if (!isset($_SESSION['id_usuarios'])) { header('Location: index.php'); exit; }
require_once __DIR__ . '/db_connection.php';

$userId   = (int)($_SESSION['id_usuarios'] ?? 0);
$userName = $_SESSION['nombre'] ?? 'Usuario';
$userRole = $_SESSION['rol'] ?? 'Paciente';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// MÉTRICAS GENERALES PARA MÉDICO / ADMIN
$totalPacientes = null;
$citasHoy = null;
$evolucionesRegistradas = null; // global (expediente total filas) para médico/admin
$totalMedicos = null;
$citasPendientes = null;
$pacientesActivos = null;
// Arrays para gráficos (12 meses)
$citasPorMes      = array_fill(0, 12, 0);
$evolucionesPorMes = array_fill(0, 12, 0);
$pacientesPorMes  = array_fill(0, 12, 0);
$citasPorEstado   = ['pendiente'=>0,'confirmada'=>0,'cancelada'=>0,'completada'=>0];

// MÉTRICAS PERSONALES PARA PACIENTE
$miEvoluciones = null; $misEjercicios = null; $misAlimentos = null; $misCitasConfirmadas = null;
// Datos enriquecidos paciente
$pesoActual = null; $pesoPrevio = null; $pesoCambio = null; $pesoHistorial = [];
$diasEjercicioSemana = 0; $minutosMes = 0;
$alimentosMes = 0; $tiposComida = ['desayuno'=>0,'almuerzo'=>0,'cena'=>0,'snack'=>0];
$proximaCita = null;
$ultimaCitaConfirmada = null;

$hoy = date('Y-m-d');

try {
    if ($userRole === 'Medico' || $userRole === 'Administrador') {
        // Total pacientes
        if ($res = $conexion->query('SELECT COUNT(*) AS c FROM pacientes')) {
            $row = $res->fetch_assoc(); $totalPacientes = (int)$row['c'];
        }
        // Citas hoy (todas confirmadas hoy)
        if ($stmt = $conexion->prepare("SELECT COUNT(*) AS c FROM citas WHERE fecha = ?")) {
            $stmt->bind_param('s', $hoy); $stmt->execute(); $r = $stmt->get_result()->fetch_assoc(); $citasHoy = (int)$r['c']; $stmt->close();
        }
        // Evoluciones (registros en expediente)
        if ($res = $conexion->query('SELECT COUNT(*) AS c FROM expediente')) { $evolucionesRegistradas = (int)$res->fetch_assoc()['c']; }
        // Total médicos
        if ($res = $conexion->query("SELECT COUNT(*) AS c FROM usuarios WHERE Rol='Medico'")) { $totalMedicos = (int)$res->fetch_assoc()['c']; }
        // Citas pendientes
        if ($res = $conexion->query("SELECT COUNT(*) AS c FROM citas WHERE estado='pendiente'")) { $citasPendientes = (int)$res->fetch_assoc()['c']; }
        // Pacientes activos (columna Estado o activo)
        $resPa = $conexion->query("SELECT COUNT(*) AS c FROM pacientes WHERE Estado='activo'");
        if (!$resPa) $resPa = $conexion->query("SELECT COUNT(*) AS c FROM pacientes WHERE activo=1");
        if (!$resPa) $resPa = $conexion->query("SELECT COUNT(*) AS c FROM pacientes");
        if ($resPa) { $pacientesActivos = (int)$resPa->fetch_assoc()['c']; }
        // Citas por mes (año actual)
        $anio = date('Y');
        if ($res = $conexion->query("SELECT MONTH(fecha) AS mes, COUNT(*) AS total FROM citas WHERE YEAR(fecha)='$anio' GROUP BY MONTH(fecha)")) {
            while ($row = $res->fetch_assoc()) { $citasPorMes[(int)$row['mes']-1] = (int)$row['total']; }
        }
        // Citas por estado
        if ($res = $conexion->query("SELECT estado, COUNT(*) AS total FROM citas GROUP BY estado")) {
            while ($row = $res->fetch_assoc()) { if (isset($citasPorEstado[$row['estado']])) { $citasPorEstado[$row['estado']] = (int)$row['total']; } }
        }
        // Evoluciones por mes (año actual) - intenta columna fecha o fecha_registro
        $resEv = $conexion->query("SELECT MONTH(fecha) AS mes, COUNT(*) AS total FROM expediente WHERE YEAR(fecha)='$anio' GROUP BY MONTH(fecha)");
        if (!$resEv) $resEv = $conexion->query("SELECT MONTH(fecha_registro) AS mes, COUNT(*) AS total FROM expediente WHERE YEAR(fecha_registro)='$anio' GROUP BY MONTH(fecha_registro)");
        if (!$resEv) $resEv = $conexion->query("SELECT MONTH(created_at) AS mes, COUNT(*) AS total FROM expediente WHERE YEAR(created_at)='$anio' GROUP BY MONTH(created_at)");
        if ($resEv) { while ($row = $resEv->fetch_assoc()) { $evolucionesPorMes[(int)$row['mes']-1] = (int)$row['total']; } }
        // Pacientes registrados por mes (año actual)
        $resPm = $conexion->query("SELECT MONTH(fecha_registro) AS mes, COUNT(*) AS total FROM pacientes WHERE YEAR(fecha_registro)='$anio' GROUP BY MONTH(fecha_registro)");
        if (!$resPm) $resPm = $conexion->query("SELECT MONTH(created_at) AS mes, COUNT(*) AS total FROM pacientes WHERE YEAR(created_at)='$anio' GROUP BY MONTH(created_at)");
        if ($resPm) { while ($row = $resPm->fetch_assoc()) { $pacientesPorMes[(int)$row['mes']-1] = (int)$row['total']; } }
    } else { // P A C I E N T E
        // Hallar id_pacientes asociado
        $idPaciente = 0;
        if ($stmt = $conexion->prepare('SELECT id_pacientes FROM pacientes WHERE id_usuarios = ? LIMIT 1')) {
            $stmt->bind_param('i', $userId); $stmt->execute(); $rp = $stmt->get_result()->fetch_assoc(); if ($rp) { $idPaciente = (int)$rp['id_pacientes']; } $stmt->close();
        }
        if ($idPaciente > 0) {
            $anioActual = (int)date('Y'); $mesActual = (int)date('m');
            // Evoluciones (count)
            if ($stmt = $conexion->prepare('SELECT COUNT(*) AS c FROM expediente WHERE id_pacientes = ?')) { $stmt->bind_param('i',$idPaciente); $stmt->execute(); $miEvoluciones = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close(); }
            // Ultimos 6 pesos para sparkline
            if ($stmt = $conexion->prepare('SELECT peso, fecha_registro FROM expediente WHERE id_pacientes = ? AND peso IS NOT NULL ORDER BY fecha_registro DESC LIMIT 6')) {
                $stmt->bind_param('i',$idPaciente); $stmt->execute(); $resW = $stmt->get_result(); $wi=0;
                while($r=$resW->fetch_assoc()) { if($wi===0) $pesoActual=(float)$r['peso']; elseif($wi===1) $pesoPrevio=(float)$r['peso']; $pesoHistorial[]=(float)$r['peso']; $wi++; } $stmt->close();
                $pesoHistorial = array_reverse($pesoHistorial);
                if($pesoActual!==null && $pesoPrevio!==null) $pesoCambio = round($pesoActual-$pesoPrevio,1);
            }
            // Ejercicios
            $inicioSemana = date('Y-m-d', strtotime('monday this week'));
            if ($stmt = $conexion->prepare('SELECT COUNT(DISTINCT fecha) AS dias FROM ejercicios WHERE paciente_id = ? AND fecha BETWEEN ? AND ?')) { $stmt->bind_param('iss',$idPaciente,$inicioSemana,$hoy); $stmt->execute(); $diasEjercicioSemana=(int)$stmt->get_result()->fetch_assoc()['dias']; $stmt->close(); }
            if ($stmt = $conexion->prepare('SELECT COALESCE(SUM(tiempo),0) AS mins FROM ejercicios WHERE paciente_id = ? AND YEAR(fecha)=? AND MONTH(fecha)=?')) { $stmt->bind_param('iii',$idPaciente,$anioActual,$mesActual); $stmt->execute(); $minutosMes=(int)$stmt->get_result()->fetch_assoc()['mins']; $misEjercicios=$minutosMes; $stmt->close(); }
            // Alimentos este mes
            if ($stmt = $conexion->prepare('SELECT tipo_comida, COUNT(*) AS c FROM alimentos_registro WHERE id_pacientes=? AND YEAR(fecha)=? AND MONTH(fecha)=? GROUP BY tipo_comida')) {
                $stmt->bind_param('iii',$idPaciente,$anioActual,$mesActual); $stmt->execute(); $resT=$stmt->get_result();
                while($r=$resT->fetch_assoc()){ $t=strtolower($r['tipo_comida']); if(isset($tiposComida[$t])) $tiposComida[$t]=(int)$r['c']; $alimentosMes+=(int)$r['c']; } $stmt->close();
            }
            $misAlimentos = $alimentosMes;
            // Proxima cita
            if ($stmt = $conexion->prepare("SELECT c.fecha, c.hora, c.motivo, c.estado, COALESCE(u.Nombre_completo,'Medico') AS medico FROM citas c LEFT JOIN usuarios u ON c.medico_id=u.id_usuarios WHERE c.paciente_id=? AND c.fecha>=? AND c.estado IN ('confirmada','pendiente') ORDER BY c.fecha ASC, c.hora ASC LIMIT 1")) {
                $stmt->bind_param('is',$idPaciente,$hoy); $stmt->execute(); $proximaCita=$stmt->get_result()->fetch_assoc(); $stmt->close();
            }
            if ($stmt = $conexion->prepare("SELECT COUNT(*) AS c FROM citas WHERE paciente_id=? AND estado='confirmada'")) { $stmt->bind_param('i',$idPaciente); $stmt->execute(); $misCitasConfirmadas=(int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close(); }
            // Última cita confirmada (más reciente, sin importar si es pasada o futura)
            if ($stmt = $conexion->prepare("SELECT c.fecha, c.hora, c.motivo, c.estado, COALESCE(u.Nombre_completo,'Médico') AS medico FROM citas c LEFT JOIN usuarios u ON c.medico_id=u.id_usuarios WHERE c.paciente_id=? AND c.estado='confirmada' ORDER BY c.fecha DESC, c.hora DESC LIMIT 1")) { $stmt->bind_param('i',$idPaciente); $stmt->execute(); $ultimaCitaConfirmada=$stmt->get_result()->fetch_assoc(); $stmt->close(); }
        }
    }
} catch (Throwable $ex) {
    // Si algo falla dejamos valores nulos y mostramos placeholder
}

// CARRUSEL DE BANNERS PACIENTE (tabla independiente: pac_banners)
$bannerSlides = [];
$carouselInterval = 5000;
if ($userRole === 'Paciente') {
    try {
        // Tabla separada del carrusel de la landing page
        $conexion->query("CREATE TABLE IF NOT EXISTS pac_banners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titulo VARCHAR(200) NOT NULL DEFAULT '',
            subtitulo TEXT,
            btn_texto VARCHAR(100),
            btn_link VARCHAR(400),
            imagen VARCHAR(500),
            bg_color VARCHAR(50) DEFAULT '#198754',
            orden INT DEFAULT 0,
            activo TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $res = $conexion->query("SELECT * FROM pac_banners WHERE activo=1 ORDER BY orden ASC, id ASC");
        if ($res) { while ($r = $res->fetch_assoc()) $bannerSlides[] = $r; }
        $rc = $conexion->query("SELECT valor FROM inicio_config WHERE clave='pac_carousel_interval'");
        if (!$rc || !($rr = $rc->fetch_assoc())) {
            $rc = $conexion->query("SELECT valor FROM inicio_config WHERE clave='carousel_interval'");
            if ($rc && $rr = $rc->fetch_assoc()) $carouselInterval = max(1000,(int)$rr['valor']);
        } else { $carouselInterval = max(1000,(int)$rr['valor']); }
    } catch (Throwable $ex2) { /* tabla aún no existe */ }
}

// BOTONES DE ACCIONES SEGÚN ROL
$actions = [];
if ($userRole === 'Administrador') {
    $actions = [
        ['href'=>'Actualizar_perfil.php','icon'=>'bi-person-circle','text'=>'Actualizar Perfil','desc'=>'Modifica tu información personal y credenciales de acceso'],
        ['href'=>'Activar_desactivar_paciente.php','icon'=>'bi-toggle-on','text'=>'Usuario','desc'=>'Activa o desactiva el acceso de pacientes al sistema'],
        ['href'=>'panelevolucionpaciente.php','icon'=>'bi-graph-up','text'=>'Panel de Evolución','desc'=>'Visualiza el progreso y evolución de los pacientes'],
        ['href'=>'Busqueda_avanzada.php','icon'=>'bi-search','text'=>'Búsqueda Avanzada','desc'=>'Busca pacientes por múltiples criterios y filtros'],
        ['href'=>'citas_medico.php','icon'=>'bi-calendar-event','text'=>'Citas Médicas','desc'=>'Gestiona y programa citas con los pacientes'],
        ['href'=>'Disponibilidad_citas.php','icon'=>'bi-clock','text'=>'Disponibilidad de Citas','desc'=>'Configura horarios disponibles para consultas'],
        ['href'=>'Registropacientes.php','icon'=>'bi-person-plus','text'=>'Registro de Pacientes','desc'=>'Añade nuevos pacientes al sistema de la clínica'],
        
        ['href'=>'Clasificacion_alimentos.php','icon'=>'bi-list-check','text'=>'Clasificación de Alimentos','desc'=>'Organiza y categoriza los alimentos por grupos'],
        ['href'=>'Crear_Receta.php','icon'=>'bi-receipt','text'=>'Crear Receta','desc'=>'Genera nuevas recetas nutricionales personalizadas'],
        ['href'=>'Gestion_Receta.php','icon'=>'bi-journal-text','text'=>'Gestión de Recetas','desc'=>'Administra y edita las recetas existentes'],
        ['href'=>'retroalimentacion1.php','icon'=>'bi-chat-dots','text'=>'Retroalimentación','desc'=>'Envía y recibe comentarios sobre el tratamiento'],
        ['href'=>'edicioninicio.php','icon'=>'bi-images','text'=>'Edición Inicio','desc'=>'Gestiona los banners del carrusel que ven los pacientes al iniciar sesión']
    ];
} elseif ($userRole === 'Medico') {
    $actions = [
        ['href'=>'Actualizar_perfil.php','icon'=>'bi-person-circle','text'=>'Actualizar Perfil','desc'=>'Modifica tu información personal y credenciales de acceso'],
        ['href'=>'Activar_desactivar_paciente.php','icon'=>'bi-toggle-on','text'=>'Usuario','desc'=>'Activa o desactiva el acceso de pacientes al sistema'],
        ['href'=>'panelevolucionpaciente.php','icon'=>'bi-graph-up','text'=>'Panel de Evolución','desc'=>'Visualiza el progreso y evolución de los pacientes'],
        ['href'=>'Busqueda_avanzada.php','icon'=>'bi-search','text'=>'Búsqueda Avanzada','desc'=>'Busca pacientes por múltiples criterios y filtros'],
        ['href'=>'citas_medico.php','icon'=>'bi-calendar-event','text'=>'Citas Médicas','desc'=>'Gestiona y programa citas con los pacientes'],
        ['href'=>'Disponibilidad_citas.php','icon'=>'bi-clock','text'=>'Disponibilidad de Citas','desc'=>'Configura horarios disponibles para consultas'],
        ['href'=>'Registropacientes.php','icon'=>'bi-person-plus','text'=>'Registro de Pacientes','desc'=>'Añade nuevos pacientes al sistema de la clínica'],
        
        ['href'=>'Clasificacion_alimentos.php','icon'=>'bi-list-check','text'=>'Clasificación de Alimentos','desc'=>'Organiza y categoriza los alimentos por grupos'],
        ['href'=>'Crear_Receta.php','icon'=>'bi-receipt','text'=>'Crear Receta','desc'=>'Genera nuevas recetas nutricionales personalizadas'],
        ['href'=>'Gestion_Receta.php','icon'=>'bi-journal-text','text'=>'Gestión de Recetas','desc'=>'Administra y edita las recetas existentes'],
        
        ['href'=>'retroalimentacion1.php','icon'=>'bi-chat-dots','text'=>'Retroalimentación','desc'=>'Envía y recibe comentarios sobre el tratamiento']
    ];
} else { // Paciente
    $actions = [
        ['href'=>'Disponibilidad_citas.php','icon'=>'bi-clock','text'=>'Disponibilidad de Citas','desc'=>'Consulta horarios disponibles y agenda tu cita'],
        ['href'=>'Actualizar_perfil.php','icon'=>'bi-person-circle','text'=>'Actualizar Perfil','desc'=>'Actualiza tu información personal y de contacto'],
        ['href'=>'panelevolucionpaciente.php','icon'=>'bi-graph-up','text'=>'Panel de Evolución','desc'=>'Revisa tu progreso y evolución nutricional'],
        ['href'=>'Resgistro_Alimentos.php','icon'=>'bi-apple','text'=>'Registro de Alimentos','desc'=>'Registra los alimentos que consumes diariamente'],
        ['href'=>'Gestion_Receta.php','icon'=>'bi-journal-text','text'=>'Gestión de Recetas','desc'=>'Consulta tus recetas nutricionales personalizadas'],
        
        ['href'=>'retroalimentacion1.php','icon'=>'bi-chat-dots','text'=>'Retroalimentación','desc'=>'Comunícate con tu nutricionista sobre el tratamiento']
    ];
}

// Texto de cabecera según rol
$headerTitle = 'Bienvenido a la Clínica Nutricional!';
if ($userRole === 'Medico') { $headerTitle = '¡Bienvenido Doctor!'; }
elseif ($userRole === 'Administrador') { $headerTitle = 'Panel Administrador'; }
elseif ($userRole === 'Paciente') { $headerTitle = '¡Bienvenido!'; }

$subTitle = 'Aquí puedes ver un resumen rápido de tu gestión.';
if ($userRole === 'Paciente') { $subTitle = 'Resumen de tu progreso y accesos rápidos.'; }
if ($userRole === 'Medico') { $subTitle = '¿Qué haremos hoy Doctor?'; }
if ($userRole === 'Administrador') { $subTitle = 'Control total del sistema.'; }

// Tip nutricional aleatorio (incluye el original + 5 nuevos)
$tips = [
    'Hidrátate adecuadamente: Tomá al menos 6 a 8 vasos de agua al día. La deshidratación puede causar fatiga, dolores de cabeza y hasta hambre falsa.',
    'Incluye vegetales en cada comida: Los vegetales aportan fibra, vitaminas y antioxidantes. Trata de llenar la mitad del plato con verduras de varios colores.',
    'Come proteína en cada comida: La proteína ayuda a mantener la masa muscular y da sensación de saciedad. Buenas opciones: pollo, pescado, huevos, frijoles, tofu o yogurt griego.',
    'Reduce los azúcares añadidos: Evita las bebidas azucaradas, galletas y snacks procesados. Preferí frutas enteras, que además aportan fibra.',
    'Planifica tus comidas: Preparar tus alimentos con anticipación evita comer “lo primero que encontrés”. Planifica al menos 2 días a la semana para cocinar y organizar porciones.',
    'Incluye al menos 5 porciones de frutas y verduras al día para mejorar tu salud digestiva y fortalecer tu sistema inmunológico.'
];
$tipNutricional = $tips[array_rand($tips)];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Inicio</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    body { background:#f8fafc; font-family:'Segoe UI',system-ui,sans-serif; padding:20px; }
    /* Metric icon badge */
    .metric-icon { width:48px; height:48px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; margin-bottom:10px; }
    .metric-icon.green  { background:#e8f5e9; color:#198754; }
    .metric-icon.blue   { background:#e3f2fd; color:#0d6efd; }
    .metric-icon.orange { background:#fff3e0; color:#fd7e14; }
    .metric-icon.purple { background:#f3e5f5; color:#9c27b0; }
    .metric-icon.teal   { background:#e0f7fa; color:#0097a7; }
    .metric-icon.red    { background:#ffebee; color:#e53935; }
    .metric-trend { font-size:0.78rem; color:#6c757d; margin-top:4px; }
    /* Chart containers */
    .chart-card { background:#fff; border:1px solid #e9ecef; border-radius:18px; padding:20px 22px; box-shadow:0 4px 12px rgba(0,0,0,0.05); }
    .chart-card h6 { font-weight:700; color:#0d5132; margin-bottom:16px; font-size:1rem; }
    .chart-wrap { position:relative; }
    .hero-title { font-size:clamp(1.8rem,2.6vw + 1rem,2.6rem); font-weight:700; color:#0d5132; }
    .hero-sub { font-size:1.05rem; color:#495057; }
    .metric-card { background:#fff; border:1px solid #e9ecef; border-radius:18px; padding:24px 22px; box-shadow:0 4px 12px rgba(0,0,0,0.05); transition:.25s; }
    .metric-card:hover { transform:translateY(-4px); box-shadow:0 10px 28px rgba(0,0,0,0.08); }
    .metric-value { font-size:2.3rem; font-weight:700; margin:0; color:#0d5132; }
    .metric-label { font-size:0.95rem; font-weight:600; color:#495057; text-transform:uppercase; letter-spacing:.5px; }
    .tip-box { background:linear-gradient(135deg,#e8f5e9,#e3f2fd); border:1px solid #d8e2dc; border-radius:18px; padding:28px 24px; box-shadow:0 4px 14px rgba(25,135,84,0.10); }
    .tip-box h3 { font-size:1.4rem; font-weight:700; color:#198754; }
    .actions-grid { display:grid; gap:20px; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); margin-top:8px; }
    .action-btn { 
        position:relative; 
        display:flex; 
        flex-direction:column; 
        align-items:flex-start; 
        justify-content:flex-start; 
        gap:12px; 
        padding:24px 20px; 
        background:#ffffff; 
        border:1px solid #e9ecef; 
        border-radius:16px; 
        text-decoration:none; 
        color:#0d5132; 
        font-weight:600; 
        font-size:1rem; 
        box-shadow:0 4px 10px rgba(0,0,0,.05); 
        transition:.25s;
        min-height:140px;
    }
    .action-btn-header {
        display:flex;
        align-items:center;
        gap:12px;
        width:100%;
    }
    .action-btn i { font-size:32px; color:#198754; transition:.25s; }
    .action-btn-title {
        font-size:1.1rem;
        font-weight:700;
        color:#0d5132;
        margin:0;
    }
    .action-btn-desc {
        font-size:0.9rem;
        font-weight:400;
        color:#6c757d;
        line-height:1.4;
        margin:0;
        text-align:left;
    }
    .action-btn:hover { 
        background:#198754; 
        color:#fff; 
        transform:translateY(-5px); 
        box-shadow:0 12px 28px rgba(25,135,84,.25); 
        border-color:#198754;
    }
    .action-btn:hover i { color:#fff; transform:scale(1.1); }
    .action-btn:hover .action-btn-title { color:#fff; }
    .action-btn:hover .action-btn-desc { color:#e8f5e9; }
    .section-heading { font-size:1.3rem; font-weight:700; margin:28px 0 12px; color:#146c43; }
    /* === PATIENT RICH CARDS === */
    .pac-card { background:#fff; border:1px solid #e9ecef; border-radius:18px; padding:22px 20px; box-shadow:0 4px 12px rgba(0,0,0,0.05); height:100%; transition:.25s; }
    .pac-card:hover { transform:translateY(-3px); box-shadow:0 10px 24px rgba(0,0,0,0.08); }
    .pac-card-label { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#6c757d; margin-bottom:6px; display:flex; align-items:center; gap:6px; }
    .pac-card-label::after { content:''; flex:1; height:2px; border-radius:2px; }
    .pac-card-label.green::after { background:#198754; }
    .pac-card-label.blue::after  { background:#0d6efd; }
    .pac-card-label.orange::after{ background:#fd7e14; }
    .pac-card-label.teal::after  { background:#0097a7; }
    .pac-big-val { font-size:2.4rem; font-weight:700; color:#0d5132; line-height:1; }
    .pac-sub { font-size:.82rem; color:#6c757d; margin-top:4px; }
    .pac-change-pos { color:#198754; font-weight:600; font-size:.9rem; }
    .pac-change-neg { color:#dc3545; font-weight:600; font-size:.9rem; }
    .pac-sparkline-wrap { height:70px; margin-top:10px; }
    .pac-bar-row { margin-bottom:10px; }
    .pac-bar-label { font-size:.8rem; font-weight:600; color:#333; display:flex; justify-content:space-between; margin-bottom:3px; }
    .pac-progress { height:8px; border-radius:6px; background:#e9ecef; overflow:hidden; }
    .pac-progress-fill { height:100%; border-radius:6px; }
    .cita-date { font-size:1.45rem; font-weight:700; color:#0d5132; line-height:1.2; }
    .cita-doc { display:flex; align-items:center; gap:10px; margin:10px 0; }
    .cita-doc-avatar { width:40px; height:40px; border-radius:50%; background:#e8f5e9; display:flex; align-items:center; justify-content:center; color:#198754; font-size:1.1rem; flex-shrink:0; }
    .cita-badge { display:inline-block; padding:2px 10px; border-radius:20px; font-size:.75rem; font-weight:600; }
    .cita-badge.confirmada { background:#d1f0de; color:#146c43; }
    .cita-badge.pendiente  { background:#fff3e0; color:#e65100; }
    .circle-wrap { position:relative; width:110px; height:110px; margin:0 auto; }
    @media (max-width:600px){ .metric-value { font-size:1.9rem; } .pac-big-val { font-size:1.9rem; } }

    /* ── CAROUSEL DE BANNERS PACIENTE ── */
    .inicio-carousel-wrap {
        margin: -20px -20px 28px -20px;
        border-radius: 0;
        overflow: hidden;
    }
    .inicio-carousel { border-radius: 0; }
    .inicio-carousel .carousel-item {
        height: 289px;
    }
    .inicio-carousel .carousel-item .slide-bg {
        position: absolute; inset: 0;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
    }
    .inicio-carousel .carousel-item .slide-overlay {
        position: absolute; inset: 0;
        background: linear-gradient(90deg, rgba(0,0,0,.55) 0%, rgba(0,0,0,.20) 60%, rgba(0,0,0,.05) 100%);
    }
    .inicio-carousel .slide-content {
        position: relative; z-index: 2;
        height: 289px;
        display: flex; flex-direction: column;
        justify-content: center;
        padding: 34px 60px;
        max-width: 660px;
    }
    .inicio-carousel .slide-title {
        font-size: clamp(1.5rem, 3vw, 2.4rem);
        font-weight: 800;
        color: #fff;
        line-height: 1.2;
        text-shadow: 0 2px 10px rgba(0,0,0,.4);
        margin-bottom: 10px;
    }
    .inicio-carousel .slide-sub {
        font-size: clamp(.9rem, 1.4vw, 1.1rem);
        color: rgba(255,255,255,.88);
        line-height: 1.5;
        margin-bottom: 20px;
        text-shadow: 0 1px 6px rgba(0,0,0,.35);
    }
    .inicio-carousel .slide-btn {
        align-self: flex-start;
        background: #fff;
        color: #0d5132;
        font-weight: 700;
        border: none;
        border-radius: 50px;
        padding: 10px 28px;
        font-size: .95rem;
        text-decoration: none;
        box-shadow: 0 4px 14px rgba(0,0,0,.2);
        transition: .2s;
    }
    .inicio-carousel .slide-btn:hover { background: #198754; color: #fff; transform: translateY(-2px); }
    .inicio-carousel .carousel-indicators [data-bs-target] {
        width: 28px; height: 4px; border-radius: 4px;
        background: rgba(255,255,255,.5);
        border: none;
    }
    .inicio-carousel .carousel-indicators .active { background: #fff; }
    .inicio-carousel .carousel-control-prev-icon,
    .inicio-carousel .carousel-control-next-icon {
        background-color: rgba(0,0,0,.35);
        border-radius: 50%;
        padding: 18px;
        background-size: 50%;
    }
    @media (max-width:600px){
        .inicio-carousel .carousel-item { height: 187px; }
        .inicio-carousel .slide-content { height: 187px; padding: 20px 24px; }
        .inicio-carousel .slide-title { font-size: 1.2rem; }
    }

    /* ── Botón flotante citas pendientes ── */
    .citas-float-btn { position: fixed; top: 16px; right: 16px; z-index: 1050; width: 52px; height: 52px; border-radius: 50%; background: linear-gradient(135deg, #198754 0%, #146c43 100%); border: none; color: #fff; box-shadow: 0 4px 18px rgba(25,135,84,.40); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all .25s ease; font-size: 1.3rem; }
    .citas-float-btn:hover { transform: scale(1.1); box-shadow: 0 6px 24px rgba(20,108,67,.50); }
    .citas-float-badge { position: absolute; top: -5px; right: -5px; background: #dc3545; color: #fff; border-radius: 50%; min-width: 22px; height: 22px; padding: 0 4px; font-size: .72rem; font-weight: 700; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; animation: pulse-citas 1.6s ease-in-out infinite; }
    @keyframes pulse-citas { 0%,100%{transform:scale(1)} 50%{transform:scale(1.25)} }
    .citas-panel { position: fixed; top: 0; right: -420px; width: 400px; max-width: 95vw; height: 100vh; background: #fff; z-index: 1049; box-shadow: -4px 0 28px rgba(0,0,0,.15); display: flex; flex-direction: column; transition: right .3s ease; border-left: 3px solid #198754; }
    .citas-panel.open { right: 0; }
    .citas-panel-header { background: linear-gradient(135deg, #198754, #146c43); color: #fff; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
    .citas-panel-header h6 { margin: 0; font-weight: 700; font-size: 1.05rem; }
    .citas-panel-body { flex: 1; overflow-y: auto; padding: 16px; }
    .cita-card { background: #f8fffe; border: 1px solid #c3e6cb; border-radius: 12px; padding: 14px; margin-bottom: 12px; transition: box-shadow .2s, opacity .3s, max-height .35s; }
    .cita-card:hover { box-shadow: 0 4px 14px rgba(25,135,84,.13); }
    .cita-patient { font-weight: 700; color: #0d5132; font-size: .97rem; }
    .cita-meta { font-size: .84rem; color: #6c757d; margin: 3px 0 8px; }
    .cita-motivo { font-size: .82rem; color: #495057; background: #e8f5e9; border-radius: 6px; padding: 5px 8px; margin-bottom: 10px; }
    .cita-actions { display: flex; gap: 8px; }

</style>
</head>
<body>

<?php if ($userRole === 'Paciente' && !empty($bannerSlides)): ?>
<!-- ══ CARRUSEL DE BANNERS ══ -->
<div class="inicio-carousel-wrap">
    <div id="inicioBannerCarousel" class="carousel slide inicio-carousel" data-bs-ride="carousel" data-bs-interval="<?= $carouselInterval; ?>">
        <!-- Indicadores -->
        <?php if (count($bannerSlides) > 1): ?>
        <div class="carousel-indicators">
            <?php foreach ($bannerSlides as $si => $sb): ?>
            <button type="button" data-bs-target="#inicioBannerCarousel" data-bs-slide-to="<?= $si; ?>"
                <?= $si===0 ? 'class="active" aria-current="true"' : ''; ?>
                aria-label="Banner <?= $si+1; ?>"></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <!-- Slides -->
        <div class="carousel-inner">
            <?php foreach ($bannerSlides as $si => $sb):
                $imgSrc = $sb['imagen'] && file_exists(__DIR__ . '/' . $sb['imagen']) ? $sb['imagen'] : null;
            ?>
            <div class="carousel-item <?= $si===0 ? 'active' : ''; ?>">
                <!-- fondo -->
                <?php if ($imgSrc): ?>
                <div class="slide-bg" style="background-image:url('<?= e($imgSrc); ?>');"></div>
                <?php else: ?>
                <div class="slide-bg" style="background:<?= e($sb['bg_color'] ?: '#198754'); ?>;"></div>
                <?php endif; ?>
                <div class="slide-overlay"></div>
                <!-- contenido -->
                <div class="slide-content">
                    <div class="slide-title"><?= e($sb['titulo']); ?></div>
                    <?php if ($sb['subtitulo']): ?>
                    <div class="slide-sub"><?= e($sb['subtitulo']); ?></div>
                    <?php endif; ?>
                    <?php if ($sb['btn_texto']): ?>
                    <a href="<?= e($sb['btn_link'] ?: '#'); ?>" class="slide-btn" target="main-content">
                        <?= e($sb['btn_texto']); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <!-- Controles (solo si hay más de 1 slide) -->
        <?php if (count($bannerSlides) > 1): ?>
        <button class="carousel-control-prev" type="button" data-bs-target="#inicioBannerCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#inicioBannerCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($userRole === 'Medico'): ?>
<!-- ── Botón flotante citas pendientes (esquina superior derecha) ── -->
<button class="citas-float-btn" id="citasFloatBtn" onclick="toggleCitasPanel()" title="Citas pendientes de confirmar">
    <i class="bi bi-calendar-check"></i>
    <span class="citas-float-badge d-none" id="citasBadge">0</span>
</button>
<!-- ── Panel lateral citas ── -->
<div class="citas-panel" id="citasPanel">
    <div class="citas-panel-header">
        <div>
            <h6><i class="bi bi-calendar-check me-2"></i>Citas Pendientes</h6>
            <small id="citasPanelSub" style="opacity:.85;">Cargando...</small>
        </div>
        <button onclick="toggleCitasPanel()" style="background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;line-height:1;padding:0;"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="citas-panel-body" id="citasPanelBody">
        <div class="text-center py-5 text-muted">
            <i class="bi bi-arrow-clockwise" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
            Cargando citas...
        </div>
    </div>
</div>
<?php endif; ?>

    <div class="container-fluid">
        <div class="mb-5 text-center">
            <h1 class="hero-title mb-3"><?= e($headerTitle); ?></h1>
            <p class="hero-sub mb-0"><?= e($subTitle); ?></p>
        </div>

        <!-- MÉTRICAS -->
        <div class="row g-4 mb-4">
            <?php if ($userRole === 'Medico' || $userRole === 'Administrador'): ?>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="metric-card h-100">
                        <div class="metric-icon green"><i class="bi bi-people-fill"></i></div>
                        <div class="metric-label">Pacientes</div>
                        <p class="metric-value mb-0"><?= e($totalPacientes ?? '—'); ?></p>
                        <div class="metric-trend"><i class="bi bi-person-check"></i> Registrados</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="metric-card h-100">
                        <div class="metric-icon teal"><i class="bi bi-person-check-fill"></i></div>
                        <div class="metric-label">Pac. Activos</div>
                        <p class="metric-value mb-0"><?= e($pacientesActivos ?? '—'); ?></p>
                        <div class="metric-trend"><i class="bi bi-check-circle"></i> En sistema</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="metric-card h-100">
                        <div class="metric-icon blue"><i class="bi bi-calendar-event-fill"></i></div>
                        <div class="metric-label">Citas Hoy</div>
                        <p class="metric-value mb-0"><?= e($citasHoy ?? '—'); ?></p>
                        <div class="metric-trend"><i class="bi bi-calendar-day"></i> <?= date('d/m/Y'); ?></div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="metric-card h-100">
                        <div class="metric-icon orange"><i class="bi bi-hourglass-split"></i></div>
                        <div class="metric-label">Citas Pend.</div>
                        <p class="metric-value mb-0"><?= e($citasPendientes ?? '—'); ?></p>
                        <div class="metric-trend"><i class="bi bi-clock"></i> Por confirmar</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="metric-card h-100">
                        <div class="metric-icon purple"><i class="bi bi-journal-medical"></i></div>
                        <div class="metric-label">Evoluciones</div>
                        <p class="metric-value mb-0"><?= e($evolucionesRegistradas ?? '—'); ?></p>
                        <div class="metric-trend"><i class="bi bi-graph-up"></i> Registradas</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="metric-card h-100">
                        <div class="metric-icon red"><i class="bi bi-person-badge-fill"></i></div>
                        <div class="metric-label">Médicos</div>
                        <p class="metric-value mb-0"><?= e($totalMedicos ?? '—'); ?></p>
                        <div class="metric-trend"><i class="bi bi-hospital"></i> En sistema</div>
                    </div>
                </div>
            <?php else: ?>
                <!-- CARD 1: EVOLUCIONES / PESO -->
                <div class="col-lg-3 col-md-6">
                    <div class="pac-card">
                        <div class="pac-card-label green"><i class="bi bi-activity"></i> Mis Evoluciones</div>
                        <?php if ($pesoActual !== null): ?>
                            <div class="d-flex align-items-end gap-3">
                                <div>
                                    <div class="pac-big-val"><?= number_format($pesoActual,1); ?> <small style="font-size:1rem;font-weight:400;color:#6c757d;">kg</small></div>
                                    <div class="pac-sub">peso actual</div>
                                </div>
                                <?php if ($pesoCambio !== null): ?>
                                <div class="text-end">
                                    <span class="<?= $pesoCambio <= 0 ? 'pac-change-pos' : 'pac-change-neg'; ?>">
                                        <?= $pesoCambio <= 0 ? '↓' : '↑'; ?> <?= abs($pesoCambio); ?> kg
                                    </span>
                                    <div class="pac-sub">este mes</div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="pac-sparkline-wrap"><canvas id="chartPesoSparkline"></canvas></div>
                        <?php else: ?>
                            <div class="pac-big-val"><?= e($miEvoluciones ?? '0'); ?></div>
                            <div class="pac-sub">registros en expediente</div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- CARD 2: EJERCICIOS -->
                <div class="col-lg-3 col-md-6">
                    <div class="pac-card d-flex flex-column align-items-center text-center">
                        <div class="pac-card-label blue w-100"><i class="bi bi-lightning-charge-fill"></i> Mis Ejercicios</div>
                        <div style="position:relative;width:130px;height:130px;margin:12px auto 8px;">
                            <canvas id="chartEjercicioDonut"></canvas>
                            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;">
                                <span style="font-size:2rem;font-weight:700;color:#0d5132;line-height:1;"><?= $diasEjercicioSemana; ?></span>
                                <span style="font-size:.72rem;color:#6c757d;font-weight:600;">días</span>
                            </div>
                        </div>
                        <div style="font-size:.85rem;color:#6c757d;margin-bottom:2px;">Racha Semanal</div>
                        <div style="font-size:1.25rem;font-weight:700;color:#0d6efd;"><?= $diasEjercicioSemana; ?>/7 <span style="font-size:1rem;font-weight:400;color:#333;">días</span></div>
                        <hr style="width:60%;border-color:#e9ecef;margin:10px auto;">
                        <div style="font-size:.82rem;color:#6c757d;">Total este mes</div>
                        <div style="font-size:1.5rem;font-weight:700;color:#0d5132;"><?= $minutosMes; ?> <span style="font-size:.9rem;font-weight:400;color:#6c757d;">min</span></div>
                    </div>
                </div>
                <!-- CARD 3: ALIMENTOS -->
                <div class="col-lg-3 col-md-6">
                    <div class="pac-card">
                        <div class="pac-card-label orange"><i class="bi bi-apple"></i> Mis Alimentos</div>
                        <?php
                        $totalComidas = max(1, $alimentosMes);
                        $comidaDefs = [
                            'desayuno' => ['label'=>'Desayuno', 'color'=>'#fd7e14'],
                            'almuerzo' => ['label'=>'Almuerzo', 'color'=>'#198754'],
                            'cena'     => ['label'=>'Cena',     'color'=>'#0d6efd'],
                            'snack'    => ['label'=>'Snack',    'color'=>'#9c27b0'],
                        ];
                        foreach ($comidaDefs as $k => $cd):
                            $v = $tiposComida[$k] ?? 0;
                            $pct = $alimentosMes > 0 ? round($v/$alimentosMes*100) : 0;
                        ?>
                        <div class="pac-bar-row">
                            <div class="pac-bar-label"><span><?= $cd['label']; ?></span><span style="color:#495057;"><?= $v; ?> registros</span></div>
                            <div class="pac-progress"><div class="pac-progress-fill" style="width:<?= $pct; ?>%;background:<?= $cd['color']; ?>;"></div></div>
                        </div>
                        <?php endforeach; ?>
                        <div class="pac-sub mt-1">Total este mes: <strong><?= $alimentosMes; ?></strong> comidas</div>
                    </div>
                </div>
                <!-- CARD 4: PRÓXIMA CITA -->
                <div class="col-lg-3 col-md-6">
                    <div class="pac-card">
                        <div class="pac-card-label teal"><i class="bi bi-calendar-heart"></i> Mis Citas</div>
                        <?php if ($ultimaCitaConfirmada): ?>
                            <div class="pac-sub mb-1">Última cita confirmada</div>
                            <div class="cita-date"><?= date('d \d\e M Y', strtotime($ultimaCitaConfirmada['fecha'])); ?></div>
                            <div style="font-size:1.1rem;font-weight:600;color:#0d5132;"><?= date('h:i A', strtotime($ultimaCitaConfirmada['hora'])); ?></div>
                            <div class="cita-doc">
                                <div class="cita-doc-avatar"><i class="bi bi-person-fill"></i></div>
                                <div style="font-size:.9rem;font-weight:600;color:#333;"><?= e($ultimaCitaConfirmada['medico']); ?></div>
                            </div>
                            <span class="cita-badge confirmada">Confirmada</span>
                            <div class="mt-3"><a href="Disponibilidad_citas.php" class="btn btn-success btn-sm w-100" target="main-content">Ver detalles</a></div>
                        <?php else: ?>
                            <div class="pac-big-val">0</div>
                            <div class="pac-sub">citas confirmadas</div>
                            <div class="mt-3"><a href="Disponibilidad_citas.php" class="btn btn-outline-success btn-sm w-100" target="main-content">Agendar cita</a></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($userRole === 'Medico' || $userRole === 'Administrador'): ?>
        <!-- GRÁFICOS / ESTADÍSTICAS -->
        <h2 class="section-heading"><i class="bi bi-bar-chart-line me-2"></i>Estadísticas <?= date('Y'); ?></h2>
        <div class="row g-4 mb-5">
            <!-- Citas por mes -->
            <div class="col-lg-8 col-12">
                <div class="chart-card h-100">
                    <h6><i class="bi bi-calendar2-week text-success me-2"></i>Citas por Mes</h6>
                    <div class="chart-wrap" style="height:280px;">
                        <canvas id="chartCitasMes"></canvas>
                    </div>
                </div>
            </div>
            <!-- Distribución estados de citas -->
            <div class="col-lg-4 col-12">
                <div class="chart-card h-100">
                    <h6><i class="bi bi-pie-chart text-primary me-2"></i>Estado de Citas</h6>
                    <div class="chart-wrap" style="height:280px;">
                        <canvas id="chartEstadoCitas"></canvas>
                    </div>
                </div>
            </div>
            <!-- Pacientes registrados por mes -->
            <div class="col-lg-6 col-12">
                <div class="chart-card h-100">
                    <h6><i class="bi bi-person-plus text-info me-2"></i>Pacientes Registrados por Mes</h6>
                    <div class="chart-wrap" style="height:250px;">
                        <canvas id="chartPacientesMes"></canvas>
                    </div>
                </div>
            </div>
            <!-- Evoluciones por mes -->
            <div class="col-lg-6 col-12">
                <div class="chart-card h-100">
                    <h6><i class="bi bi-activity text-warning me-2"></i>Evoluciones por Mes</h6>
                    <div class="chart-wrap" style="height:250px;">
                        <canvas id="chartEvolucionesMes"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- TIP NUTRICIONAL -->
        <div class="tip-box mb-5">
            <div class="d-flex align-items-center justify-content-between gap-3">
                <button id="tipPrev" onclick="changeTip(-1)" title="Tip anterior" style="background:none;border:none;cursor:pointer;color:#198754;font-size:1.3rem;padding:0 4px;flex-shrink:0;"><i class="bi bi-chevron-left"></i></button>
                <div style="flex:1;min-width:0;">
                    <h3>Tip Nutricional del Día</h3>
                    <p class="mb-0" id="tipText">"<?= e($tipNutricional); ?>"</p>
                </div>
                <button id="tipNext" onclick="changeTip(1)" title="Tip siguiente" style="background:none;border:none;cursor:pointer;color:#198754;font-size:1.3rem;padding:0 4px;flex-shrink:0;"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
        <script>
        (function(){
            const tips = <?php echo json_encode(array_values($tips), JSON_UNESCAPED_UNICODE); ?>;
            let idx = tips.indexOf(<?php echo json_encode($tipNutricional, JSON_UNESCAPED_UNICODE); ?>);
            if(idx < 0) idx = 0;
            window.changeTip = function(dir){
                idx = (idx + dir + tips.length) % tips.length;
                document.getElementById('tipText').textContent = '"' + tips[idx] + '"';
            };
        })();
        </script>

        <!-- ACCIONES POR REALIZAR -->
        <h2 class="section-heading">Acciones por Realizar</h2>
        <div class="actions-grid mb-5">
            <?php foreach ($actions as $a): ?>
                <a class="action-btn" href="<?= e($a['href']); ?>" target="main-content">
                    <div class="action-btn-header">
                        <i class="bi <?= e($a['icon']); ?>"></i>
                        <h3 class="action-btn-title"><?= e($a['text']); ?></h3>
                    </div>
                    <p class="action-btn-desc"><?= e($a['desc']); ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Inactivity is handled globally in Menuprincipal.php -->

<?php if ($userRole === 'Medico' || $userRole === 'Administrador'): ?>
<script>
(function(){
    const meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    const gridColor = 'rgba(0,0,0,0.05)';

    // --- Citas por mes (barras) ---
    new Chart(document.getElementById('chartCitasMes'), {
        type: 'bar',
        data: {
            labels: meses,
            datasets: [{
                label: 'Citas',
                data: <?= json_encode(array_values($citasPorMes)); ?>,
                backgroundColor: 'rgba(25,135,84,0.75)',
                borderColor: '#146c43',
                borderWidth: 1,
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: gridColor } },
                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0 } }
            }
        }
    });

    // --- Estado de citas (dona) ---
    const estadoData = <?= json_encode(array_values($citasPorEstado)); ?>;
    const estadoSum  = estadoData.reduce((a,b)=>a+b,0);
    new Chart(document.getElementById('chartEstadoCitas'), {
        type: 'doughnut',
        data: {
            labels: ['Pendiente','Confirmada','Cancelada','Completada'],
            datasets: [{
                data: estadoData,
                backgroundColor: ['#fd7e14','#198754','#dc3545','#0d6efd'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 12, font: { size: 12 } } },
                tooltip: {
                    callbacks: {
                        label: function(ctx){
                            const pct = estadoSum > 0 ? ((ctx.parsed/estadoSum)*100).toFixed(1) : 0;
                            return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;
                        }
                    }
                }
            }
        }
    });

    // --- Pacientes por mes (línea) ---
    new Chart(document.getElementById('chartPacientesMes'), {
        type: 'line',
        data: {
            labels: meses,
            datasets: [{
                label: 'Nuevos pacientes',
                data: <?= json_encode(array_values($pacientesPorMes)); ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.10)',
                borderWidth: 2.5,
                pointBackgroundColor: '#0d6efd',
                pointRadius: 4,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: gridColor } },
                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0 } }
            }
        }
    });

    // --- Evoluciones por mes (barras apiladas / normales) ---
    new Chart(document.getElementById('chartEvolucionesMes'), {
        type: 'bar',
        data: {
            labels: meses,
            datasets: [{
                label: 'Evoluciones',
                data: <?= json_encode(array_values($evolucionesPorMes)); ?>,
                backgroundColor: 'rgba(255,167,38,0.80)',
                borderColor: '#e65100',
                borderWidth: 1,
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: gridColor } },
                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0 } }
            }
        }
    });

})();
</script>
<?php endif; ?>

<?php if ($userRole === 'Paciente' && $pesoActual !== null): ?>
<script>
(function(){
    // Sparkline de peso
    const pesoData = <?php echo json_encode(array_values($pesoHistorial), JSON_NUMERIC_CHECK); ?>;
    const ctxP = document.getElementById('chartPesoSparkline');
    if(ctxP && pesoData.length > 0){
        new Chart(ctxP, {
            type:'line',
            data:{ labels: pesoData.map((_,i)=>''), datasets:[{
                data: pesoData,
                borderColor:'#198754', backgroundColor:'rgba(25,135,84,0.12)',
                borderWidth:2, pointRadius:3, pointBackgroundColor:'#198754',
                fill:true, tension:0.4
            }]},
            options:{ responsive:true, maintainAspectRatio:false,
                plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>' '+ctx.parsed.y+' kg'}}},
                scales:{ x:{display:false}, y:{display:false, beginAtZero:false} }
            }
        });
    }
})();
</script>
<?php endif; ?>

<?php if ($userRole === 'Paciente'): ?>
<script>
(function(){
    const ctxE = document.getElementById('chartEjercicioDonut');
    if(ctxE){
        const dias = <?= (int)$diasEjercicioSemana; ?>;
        new Chart(ctxE, {
            type:'doughnut',
            data:{ datasets:[{
                data:[dias, Math.max(0, 7-dias)],
                backgroundColor:['#0d6efd','#e9ecef'],
                borderWidth:0
            }]},
            options:{ responsive:true, maintainAspectRatio:true, cutout:'70%',
                plugins:{legend:{display:false},tooltip:{enabled:false}}
            }
        });
    }
})();
</script>
<?php endif; ?>
<?php if ($userRole === 'Medico'): ?>
<script>
(function(){
    let panelOpen = false;
    function escH(s){ const d=document.createElement('div'); d.textContent=String(s); return d.innerHTML; }
    function fmtFecha(dateStr){ if(!dateStr) return ''; const p=dateStr.split('-'); return p.length===3 ? p[2]+'/'+p[1]+'/'+p[0] : dateStr; }
    function loadCitas(){
        fetch('api_citas_medico.php?action=pending',{credentials:'same-origin'})
            .then(r=>r.json())
            .then(function(data){
                const badge=document.getElementById('citasBadge');
                const body=document.getElementById('citasPanelBody');
                const sub=document.getElementById('citasPanelSub');
                if(!data||!data.ok){ if(body) body.innerHTML='<p class="text-danger text-center mt-4">Error al cargar citas.</p>'; return; }
                const count=data.count||0;
                if(badge){ if(count>0){ badge.textContent=count>99?'99+':count; badge.classList.remove('d-none'); } else { badge.classList.add('d-none'); } }
                if(sub) sub.textContent=count===0?'Sin citas pendientes':count+' cita'+(count>1?'s':'')+' por confirmar';
                if(!body) return;
                if(count===0){ body.innerHTML='<div class="text-center py-5 text-muted"><i class="bi bi-calendar-check" style="font-size:2.5rem;color:#198754;opacity:.5;display:block;margin-bottom:10px;"></i><p class="mb-1 fw-bold">¡Todo al día!</p><p class="small">No hay citas pendientes de confirmar.</p></div>'; return; }
                let html='';
                data.citas.forEach(function(c){
                    const hora=(c.hora||'').substring(0,5);
                    html+='<div class="cita-card" id="cita-'+c.id+'">';
                    html+='<div class="cita-patient"><i class="bi bi-person-fill me-1"></i>'+escH(c.nombre_completo||'Paciente')+'</div>';
                    html+='<div class="cita-meta"><i class="bi bi-calendar3 me-1"></i>'+escH(fmtFecha(c.fecha))+' &nbsp;·&nbsp; <i class="bi bi-clock me-1"></i>'+escH(hora)+'</div>';
                    if(c.motivo) html+='<div class="cita-motivo"><i class="bi bi-chat-left-text me-1"></i>'+escH(c.motivo)+'</div>';
                    html+='<div class="cita-actions">';
                    html+='<button class="btn btn-success btn-sm flex-fill" onclick="accionCita('+c.id+',\'confirmar\')"><i class="bi bi-check-circle me-1"></i>Confirmar</button>';
                    html+='<button class="btn btn-outline-danger btn-sm flex-fill" onclick="accionCita('+c.id+',\'cancelar\')"><i class="bi bi-x-circle me-1"></i>Cancelar</button>';
                    html+='</div></div>';
                });
                body.innerHTML=html;
            })
            .catch(function(){ const body=document.getElementById('citasPanelBody'); if(body) body.innerHTML='<p class="text-danger text-center mt-4">Error de conexión.</p>'; });
    }
    window.toggleCitasPanel = function(){
        panelOpen=!panelOpen;
        const panel=document.getElementById('citasPanel');
        if(panel) panel.classList.toggle('open',panelOpen);
        if(panelOpen) loadCitas();
    };
    window.accionCita = function(id, action){
        const card=document.getElementById('cita-'+id);
        if(card){ card.style.opacity='0.4'; card.style.pointerEvents='none'; }
        const fd=new FormData(); fd.append('action',action); fd.append('id',id);
        fetch('api_citas_medico.php',{method:'POST',body:fd,credentials:'same-origin'})
            .then(r=>r.json())
            .then(function(data){
                if(data&&data.ok){
                    if(card){
                        card.style.transition='opacity .3s, max-height .35s, margin .35s, padding .35s';
                        card.style.maxHeight=card.scrollHeight+'px';
                        requestAnimationFrame(function(){ card.style.opacity='0'; card.style.maxHeight='0'; card.style.overflow='hidden'; card.style.margin='0'; card.style.padding='0'; });
                        setTimeout(function(){ card.remove(); loadCitas(); },380);
                    } else { loadCitas(); }
                } else { alert('No se pudo actualizar la cita.'); if(card){ card.style.opacity='1'; card.style.pointerEvents=''; } }
            })
            .catch(function(){ alert('Error de conexión.'); if(card){ card.style.opacity='1'; card.style.pointerEvents=''; } });
    };
    loadCitas();
    setInterval(loadCitas, 30000);
})();
</script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
