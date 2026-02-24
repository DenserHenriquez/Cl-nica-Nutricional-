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

// MÉTRICAS PERSONALES PARA PACIENTE
$miEvoluciones = null; $misEjercicios = null; $misAlimentos = null; $misCitasConfirmadas = null;

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
    } else { // P A C I E N T E
        // Hallar id_pacientes asociado
        $idPaciente = 0;
        if ($stmt = $conexion->prepare('SELECT id_pacientes FROM pacientes WHERE id_usuarios = ? LIMIT 1')) {
            $stmt->bind_param('i', $userId); $stmt->execute(); $rp = $stmt->get_result()->fetch_assoc(); if ($rp) { $idPaciente = (int)$rp['id_pacientes']; } $stmt->close();
        }
        if ($idPaciente > 0) {
            if ($stmt = $conexion->prepare('SELECT COUNT(*) AS c FROM expediente WHERE id_pacientes = ?')) { $stmt->bind_param('i',$idPaciente); $stmt->execute(); $miEvoluciones = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close(); }
            if ($stmt = $conexion->prepare('SELECT COUNT(*) AS c FROM ejercicios WHERE paciente_id = ?')) { if(!$stmt){ $stmt = $conexion->prepare('SELECT COUNT(*) AS c FROM ejercicios WHERE id_pacientes = ?'); } $stmt->bind_param('i',$idPaciente); $stmt->execute(); $misEjercicios = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close(); }
            if ($stmt = $conexion->prepare('SELECT COUNT(*) AS c FROM alimentos_registro WHERE id_pacientes = ?')) { $stmt->bind_param('i',$idPaciente); $stmt->execute(); $misAlimentos = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close(); }
            if ($stmt = $conexion->prepare("SELECT COUNT(*) AS c FROM citas WHERE nombre_completo = ? AND estado='confirmada'")) { $stmt->bind_param('s',$userName); $stmt->execute(); $misCitasConfirmadas = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close(); }
        }
    }
} catch (Throwable $ex) {
    // Si algo falla dejamos valores nulos y mostramos placeholder
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
        ['href'=>'retroalimentacion1.php','icon'=>'bi-chat-dots','text'=>'Retroalimentación','desc'=>'Envía y recibe comentarios sobre el tratamiento']
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
<style>
    body { background:#f8fafc; font-family:'Segoe UI',system-ui,sans-serif; padding:20px; }
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
    @media (max-width:600px){ .metric-value { font-size:1.9rem; } }
    
</style>
</head>
<body>
    <div class="container-fluid">
        <div class="mb-5 text-center">
            <h1 class="hero-title mb-3"><?= e($headerTitle); ?></h1>
            <p class="hero-sub mb-0"><?= e($subTitle); ?></p>
        </div>

        <!-- MÉTRICAS -->
        <div class="row g-4 mb-4">
            <?php if ($userRole === 'Medico' || $userRole === 'Administrador'): ?>
                <div class="col-md-4 col-sm-6">
                    <div class="metric-card h-100">
                        <div class="metric-label">Pacientes Registrados</div>
                        <p class="metric-value mb-0"><?= e($totalPacientes ?? '—'); ?></p>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="metric-card h-100">
                        <div class="metric-label">Citas Hoy</div>
                        <p class="metric-value mb-0"><?= e($citasHoy ?? '—'); ?></p>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="metric-card h-100">
                        <div class="metric-label">Evoluciones Registradas</div>
                        <p class="metric-value mb-0"><?= e($evolucionesRegistradas ?? '—'); ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="col-md-3 col-sm-6">
                    <div class="metric-card h-100">
                        <div class="metric-label">Mis Evoluciones</div>
                        <p class="metric-value mb-0"><?= e($miEvoluciones ?? '—'); ?></p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="metric-card h-100">
                        <div class="metric-label">Mis Ejercicios</div>
                        <p class="metric-value mb-0"><?= e($misEjercicios ?? '—'); ?></p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="metric-card h-100">
                        <div class="metric-label">Mis Alimentos</div>
                        <p class="metric-value mb-0"><?= e($misAlimentos ?? '—'); ?></p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="metric-card h-100">
                        <div class="metric-label">Mis Citas</div>
                        <p class="metric-value mb-0"><?= e($misCitasConfirmadas ?? '—'); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- TIP NUTRICIONAL -->
        <div class="tip-box mb-5">
            <h3>Tip Nutricional del Día</h3>
            <p class="mb-0">“<?= e($tipNutricional); ?>”</p>
        </div>

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
</body>
</html>
