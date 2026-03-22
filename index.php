<?php
// ── Landing page: loads editable carousel banners and service cards from DB ──
require_once __DIR__ . '/db_connection.php';

function e_idx($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Ensure tables exist (safe: CREATE IF NOT EXISTS)
$conexion->query("CREATE TABLE IF NOT EXISTS inicio_banners (
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

$conexion->query("CREATE TABLE IF NOT EXISTS inicio_tarjetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    icono VARCHAR(100) DEFAULT 'fa-star',
    titulo VARCHAR(200) NOT NULL DEFAULT '',
    descripcion TEXT,
    imagen VARCHAR(500),
    enlace VARCHAR(400),
    orden INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conexion->query("CREATE TABLE IF NOT EXISTS inicio_config (
    clave VARCHAR(80) PRIMARY KEY,
    valor TEXT
)");

$carouselInterval = 5000;
$rc = $conexion->query("SELECT valor FROM inicio_config WHERE clave='carousel_interval'");
if ($rc && $rr = $rc->fetch_assoc()) $carouselInterval = max(1000,(int)$rr['valor']);

$bannerSlides = [];
$res = $conexion->query("SELECT * FROM inicio_banners WHERE activo=1 ORDER BY orden ASC, id ASC");
if ($res) { while ($r = $res->fetch_assoc()) $bannerSlides[] = $r; }

$tarjetas = [];
$res = $conexion->query("SELECT * FROM inicio_tarjetas WHERE activo=1 ORDER BY orden ASC, id ASC");
if ($res) { while ($r = $res->fetch_assoc()) $tarjetas[] = $r; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NUTRIVIDA - Tu Salud en Nuestras Manos</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/estilos.css">

    <style>
    /* ══ NUTRIVIDA LANDING PAGE ══ */
    :root { --nv-green:#198754; --nv-dark:#0d5132; --nv-light:#e8f5e9; --nv-bg:#f5faf7; }
    body { font-family:'Poppins','Segoe UI',system-ui,sans-serif; }

    /* ── HERO CAROUSEL ── */
    .nv-hero-carousel { margin-top:56px; position:relative; overflow:hidden; }
    .nv-hero-carousel .carousel-item { min-height:372px; }
    .nv-slide-bg { position:absolute; inset:0; background-size:cover; background-position:center; }
    .nv-slide-overlay { position:absolute; inset:0; background:linear-gradient(105deg,rgba(13,81,50,.82) 0%,rgba(13,81,50,.45) 50%,rgba(0,0,0,.15) 100%); }
    .nv-slide-content { position:relative; z-index:2; height:372px; display:flex; flex-direction:column; justify-content:center; padding:38px 8%; max-width:750px; }
    .nv-slide-title { font-size:clamp(2rem,4vw,3.4rem); font-weight:800; color:#fff; line-height:1.15; text-shadow:0 3px 16px rgba(0,0,0,.25); margin-bottom:16px; }
    .nv-slide-sub { font-size:clamp(.95rem,1.5vw,1.2rem); color:rgba(255,255,255,.9); line-height:1.6; margin-bottom:28px; text-shadow:0 1px 6px rgba(0,0,0,.2); max-width:520px; }
    .nv-slide-btn { display:inline-block; background:#fff; color:var(--nv-dark); font-weight:700; border:none; border-radius:50px; padding:14px 36px; font-size:1rem; text-decoration:none; box-shadow:0 4px 18px rgba(0,0,0,.15); transition:.3s; align-self:flex-start; }
    .nv-slide-btn:hover { background:var(--nv-green); color:#fff; transform:translateY(-3px); box-shadow:0 8px 28px rgba(25,135,84,.35); }
    .nv-hero-carousel .carousel-indicators { bottom:24px; }
    .nv-hero-carousel .carousel-indicators [data-bs-target] { width:32px; height:4px; border-radius:4px; border:none; background:rgba(255,255,255,.45); transition:.3s; }
    .nv-hero-carousel .carousel-indicators .active { background:#fff; width:48px; }
    .nv-hero-carousel .carousel-control-prev-icon,
    .nv-hero-carousel .carousel-control-next-icon { width:44px; height:44px; background-color:rgba(255,255,255,.2); border-radius:50%; background-size:50%; }

    /* ── DEFAULT HERO (no banners) ── */
    .nv-hero-default { margin-top:56px; background:linear-gradient(135deg,var(--nv-light) 0%,#c8e6c9 50%,var(--nv-light) 100%); min-height:372px; display:flex; align-items:center; }
    .nv-hero-title { font-size:clamp(2.2rem,4.5vw,3.8rem); font-weight:800; color:var(--nv-dark); line-height:1.12; margin-bottom:20px; }
    .nv-hero-title span { color:var(--nv-green); }
    .nv-hero-sub { font-size:1.15rem; color:#495057; max-width:480px; line-height:1.7; margin-bottom:32px; }

    /* ── SERVICES SECTION ── */
    .nv-services-section { background:var(--nv-bg); padding:80px 0 90px; }
    .nv-section-title { font-size:clamp(1.6rem,3vw,2.4rem); font-weight:800; color:var(--nv-dark); margin-bottom:10px; }
    .nv-section-sub { font-size:1.05rem; color:#6c757d; max-width:560px; margin:0 auto; }
    .nv-service-card { background:#fff; border-radius:20px; padding:36px 28px; text-align:center; border:1px solid #e9ecef; box-shadow:0 4px 16px rgba(0,0,0,.04); transition:.35s cubic-bezier(.25,.46,.45,.94); height:100%; }
    .nv-service-card:hover { transform:translateY(-8px); box-shadow:0 16px 40px rgba(25,135,84,.12); border-color:#c8e6c9; }
    .nv-service-card.has-image { padding:0; overflow:hidden; }
    .nv-card-img { width:100%; height:220px; overflow:hidden; border-radius:20px 20px 0 0; }
    .nv-card-img img { width:100%; height:100%; object-fit:cover; transition:.4s; }
    .nv-service-card:hover .nv-card-img img { transform:scale(1.06); }
    .has-image .nv-card-title,
    .has-image .nv-card-desc { padding:0 24px; }
    .has-image .nv-card-title { padding-top:20px; }
    .has-image .nv-card-desc { padding-bottom:24px; }
    .nv-card-icon { width:72px; height:72px; border-radius:18px; background:var(--nv-light); display:flex; align-items:center; justify-content:center; margin:0 auto 18px; font-size:1.7rem; color:var(--nv-green); transition:.3s; }
    .nv-service-card:hover .nv-card-icon { background:var(--nv-green); color:#fff; transform:scale(1.08); }
    .nv-card-title { font-size:1.1rem; font-weight:700; color:var(--nv-dark); margin-bottom:10px; }
    .nv-card-desc { font-size:.9rem; color:#6c757d; line-height:1.6; margin-bottom:0; }

    /* ── CTA SECTION ── */
    .nv-cta-section { background:linear-gradient(135deg,var(--nv-dark) 0%,var(--nv-green) 100%); padding:80px 0; position:relative; overflow:hidden; }
    .nv-cta-section::before { content:''; position:absolute; width:400px; height:400px; border-radius:50%; background:rgba(255,255,255,.04); top:-100px; right:-100px; }
    .nv-cta-section::after { content:''; position:absolute; width:250px; height:250px; border-radius:50%; background:rgba(255,255,255,.03); bottom:-80px; left:-60px; }
    .nv-cta-title { font-size:clamp(1.8rem,3vw,2.6rem); font-weight:800; color:#fff; margin-bottom:16px; }
    .nv-cta-sub { font-size:1.1rem; color:rgba(255,255,255,.85); max-width:520px; margin:0 auto 32px; }
    .nv-cta-btn { background:#fff; color:var(--nv-dark); font-weight:700; border:none; border-radius:50px; padding:15px 40px; font-size:1.05rem; transition:.3s; box-shadow:0 4px 20px rgba(0,0,0,.15); }
    .nv-cta-btn:hover { background:var(--nv-light); transform:translateY(-3px); box-shadow:0 8px 32px rgba(0,0,0,.2); color:var(--nv-dark); }

    /* ── FOOTER ── */
    .nv-footer { background:#1a1a2e; padding:50px 0 20px; }
    .nv-footer h5 { font-weight:700; margin-bottom:16px; color:#fff; font-size:1.05rem; }
    .nv-footer p, .nv-footer a { color:rgba(255,255,255,.7); font-size:.9rem; }
    .nv-footer a:hover { color:#fff; }
    .nv-footer-brand { font-size:1.3rem; font-weight:800; color:#fff; }
    .nv-footer-social a { width:38px; height:38px; border-radius:50%; background:rgba(255,255,255,.1); display:inline-flex; align-items:center; justify-content:center; color:#fff; margin-right:8px; transition:.3s; font-size:.9rem; text-decoration:none; }
    .nv-footer-social a:hover { background:var(--nv-green); transform:translateY(-2px); }

    @media(max-width:768px){
        .nv-hero-carousel .carousel-item,
        .nv-slide-content { min-height:279px; height:279px; }
        .nv-slide-content { padding:28px 6%; }
        .nv-hero-default { min-height:279px; }
        .nv-services-section { padding:50px 0 60px; }
    }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container">
            <!-- Brand in navbar (moved from hero) -->
            <a class="navbar-brand d-flex align-items-center me-3 text-decoration-none" href="index.php" style="gap:10px">
                <span style="width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;background:linear-gradient(135deg,#198754,#146c43);color:#fff;font-weight:700">
                    <i class="fas fa-leaf" style="font-size:16px"></i>
                </span>
                <span class="fw-bold" style="color:#198754;">NUTRIVIDA</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#servicios">Servicios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contacto">Contacto</a>
                    </li>
                </ul>
            </div>
            <!-- Login button -->
            <button class="btn btn-outline-success ms-3" id="loginBtn" data-bs-toggle="modal" data-bs-target="#loginModal">
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </button>
        </div>
    </nav>

    <?php if (!empty($bannerSlides)): ?>
    <!-- ══ HERO CAROUSEL ══ -->
    <section class="nv-hero-carousel">
        <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="<?= $carouselInterval; ?>">
            <?php if (count($bannerSlides) > 1): ?>
            <div class="carousel-indicators">
                <?php foreach ($bannerSlides as $i => $sl): ?>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $i; ?>"
                    <?= $i===0 ? 'class="active" aria-current="true"' : ''; ?>></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="carousel-inner">
                <?php foreach ($bannerSlides as $i => $sl):
                    $imgSrc = ($sl['imagen'] && file_exists(__DIR__.'/'.$sl['imagen'])) ? $sl['imagen'] : null;
                ?>
                <div class="carousel-item <?= $i===0?'active':''; ?>">
                    <?php if ($imgSrc): ?>
                    <div class="nv-slide-bg" style="background-image:url('<?= e_idx($imgSrc); ?>');"></div>
                    <?php else: ?>
                    <div class="nv-slide-bg" style="background:<?= e_idx($sl['bg_color'] ?? '#198754'); ?>;"></div>
                    <?php endif; ?>
                    <div class="nv-slide-overlay"></div>
                    <div class="nv-slide-content">
                        <h1 class="nv-slide-title"><?= e_idx($sl['titulo']); ?></h1>
                        <?php if ($sl['subtitulo']): ?>
                        <p class="nv-slide-sub"><?= e_idx($sl['subtitulo']); ?></p>
                        <?php endif; ?>
                        <?php if ($sl['btn_texto']): ?>
                        <a href="<?= e_idx($sl['btn_link'] ?: '#loginModal'); ?>" class="nv-slide-btn"
                           <?= (!$sl['btn_link'] || $sl['btn_link']==='#loginModal') ? 'data-bs-toggle="modal" data-bs-target="#loginModal"' : ''; ?>>
                            <?= e_idx($sl['btn_texto']); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($bannerSlides) > 1): ?>
            <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
            </button>
            <?php endif; ?>
        </div>
    </section>
    <?php else: ?>
    <!-- Default hero when no banners configured -->
    <section class="nv-hero-default">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="nv-hero-title">Nutrición<br>Personalizada<br><span>Vanguardista</span></h1>
                    <p class="nv-hero-sub">Tu salud es nuestra prioridad. Programas nutricionales diseñados a tu medida por profesionales expertos.</p>
                    <button class="nv-slide-btn" data-bs-toggle="modal" data-bs-target="#loginModal">
                        <i class="fas fa-calendar-plus me-2"></i>Agendar Consulta
                    </button>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>



    <!-- ══ TARJETAS DE SERVICIOS ══ -->
    <section id="servicios" class="nv-services-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="nv-section-title">Nuestros Servicios</h2>
                <p class="nv-section-sub">Soluciones nutricionales completas para tu bienestar</p>
            </div>

            <?php if (!empty($tarjetas)): ?>
            <div class="row g-4">
                <?php foreach ($tarjetas as $t): ?>
                <div class="col-md-6 col-lg-4">
                    <?php if (!empty($t['enlace'])): ?>
                    <a href="<?= e_idx($t['enlace']); ?>" class="text-decoration-none">
                    <?php endif; ?>
                    <div class="nv-service-card <?= (!empty($t['imagen']) && file_exists(__DIR__.'/'.$t['imagen'])) ? 'has-image' : ''; ?>">
                        <?php if (!empty($t['imagen']) && file_exists(__DIR__.'/'.$t['imagen'])): ?>
                        <div class="nv-card-img">
                            <img src="<?= e_idx($t['imagen']); ?>" alt="<?= e_idx($t['titulo']); ?>" loading="lazy">
                        </div>
                        <?php else: ?>
                        <div class="nv-card-icon">
                            <i class="fas <?= e_idx($t['icono'] ?: 'fa-star'); ?>"></i>
                        </div>
                        <?php endif; ?>
                        <h5 class="nv-card-title"><?= e_idx($t['titulo']); ?></h5>
                        <?php if (!empty($t['descripcion'])): ?>
                        <p class="nv-card-desc"><?= e_idx($t['descripcion']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($t['enlace'])): ?></a><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <!-- Fallback: static services when no cards configured in admin -->
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="nv-service-card">
                        <div class="nv-card-icon"><i class="fas fa-utensils"></i></div>
                        <h5 class="nv-card-title">Planes Alimenticios</h5>
                        <p class="nv-card-desc">Diseñamos dietas personalizadas según tus necesidades nutricionales, preferencias y objetivos de salud.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="nv-service-card">
                        <div class="nv-card-icon"><i class="fas fa-weight"></i></div>
                        <h5 class="nv-card-title">Control de Peso</h5>
                        <p class="nv-card-desc">Programas especializados para pérdida, ganancia o mantenimiento de peso de forma saludable.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="nv-service-card">
                        <div class="nv-card-icon"><i class="fas fa-heartbeat"></i></div>
                        <h5 class="nv-card-title">Nutrición Clínica</h5>
                        <p class="nv-card-desc">Apoyo nutricional para enfermedades crónicas, alergias alimentarias y condiciones especiales.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="nv-service-card">
                        <div class="nv-card-icon"><i class="fas fa-running"></i></div>
                        <h5 class="nv-card-title">Deporte y Fitness</h5>
                        <p class="nv-card-desc">Nutrición deportiva para atletas y personas activas que buscan optimizar su rendimiento.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="nv-service-card">
                        <div class="nv-card-icon"><i class="fas fa-baby"></i></div>
                        <h5 class="nv-card-title">Nutrición Infantil</h5>
                        <p class="nv-card-desc">Asesoramiento para el crecimiento saludable de niños y adolescentes.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="nv-service-card">
                        <div class="nv-card-icon"><i class="fas fa-users"></i></div>
                        <h5 class="nv-card-title">Consultas Familiares</h5>
                        <p class="nv-card-desc">Planes nutricionales para toda la familia, adaptados a diferentes edades y necesidades.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ══ CTA SECTION ══ -->
    <section id="contacto" class="nv-cta-section">
        <div class="container text-center position-relative" style="z-index:2;">
            <h2 class="nv-cta-title">¿Listo para Empezar?</h2>
            <p class="nv-cta-sub">
                Agenda tu consulta inicial y comienza tu camino hacia una vida más saludable.
            </p>
            <button class="nv-cta-btn" data-bs-toggle="modal" data-bs-target="#loginModal">
                <i class="fas fa-calendar-plus me-2"></i>Agendar Cita
            </button>
        </div>
    </section>

    <!-- ══ FOOTER ══ -->
    <footer class="nv-footer text-white">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="nv-footer-brand mb-3">
                        <i class="fas fa-leaf me-2" style="color:var(--nv-green);"></i>NUTRIVIDA
                    </div>
                    <p>Tu salud es nuestra prioridad. Expertos en nutrición personalizada.</p>
                    <div class="nv-footer-social mt-3">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
                <div class="col-md-4">
                    <h5>Enlaces Rápidos</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#servicios"><i class="fas fa-chevron-right me-2" style="font-size:.7rem;"></i>Servicios</a></li>
                        <li class="mb-2"><a href="#contacto"><i class="fas fa-chevron-right me-2" style="font-size:.7rem;"></i>Contacto</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contacto</h5>
                    <p class="mb-2"><i class="fas fa-phone me-2" style="color:var(--nv-green);"></i>(504) 8896-8963</p>
                    <p class="mb-2"><i class="fas fa-envelope me-2" style="color:var(--nv-green);"></i>info@nutri.hn</p>
                    <p class="mb-2"><i class="fas fa-map-marker-alt me-2" style="color:var(--nv-green);"></i>Ciudad Universitaria</p>
                </div>
            </div>
            <hr style="border-color:rgba(255,255,255,.1);">
            <div class="text-center">
                <p class="mb-0" style="font-size:.85rem;">&copy; 2026 NUTRIVIDA. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-sign-in-alt me-2"></i>Acceder al Sistema
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Alerts -->
                    <?php if (isset($_GET['ok'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_GET['ok'], ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php elseif (isset($_GET['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs nav-justified" id="loginTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab" aria-controls="login" aria-selected="true">
                                <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab" aria-controls="register" aria-selected="false">
                                <i class="fas fa-user-plus me-2"></i>Registrarse
                            </button>
                        </li>
                    </ul>

                    <!-- Tab content -->
                    <div class="tab-content p-4" id="loginTabsContent">
                        <!-- Login Tab -->
                        <div class="tab-pane fade show active" id="login" role="tabpanel" aria-labelledby="login-tab">
                            <div class="text-center mb-4">
                                <i class="fas fa-leaf text-success fa-3x mb-3"></i>
                                <h4 class="text-success fw-bold">Bienvenido de vuelta</h4>
                                <p class="text-muted">Inicia sesión en tu cuenta de nutricionista</p>
                            </div>
                            <form action="Login.php" method="POST">
                                <div class="mb-3">
                                    <label for="loginEmail" class="form-label fw-semibold">
                                        <i class="fas fa-envelope me-2 text-success"></i>Correo Electrónico
                                    </label>
                     <input type="email" class="form-control" id="loginEmail" name="Correo_electronico" placeholder="tu@email.com" required>
                                    
                                </div>
                                <div class="mb-3">
                                    <label for="loginPassword" class="form-label fw-semibold">
                                        <i class="fas fa-lock me-2 text-success"></i>Contraseña
                                    </label>
                                    <input type="password" class="form-control" id="loginPassword" name="contrasena" placeholder="Tu contraseña" required>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success fw-bold">
                                        <i class="fas fa-sign-in-alt me-2"></i>Entrar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Register Tab -->
                        <div class="tab-pane fade" id="register" role="tabpanel" aria-labelledby="register-tab">
                            <div class="text-center mb-4">
                                <i class="fas fa-heartbeat text-success fa-3x mb-3"></i>
                                <h4 class="text-success fw-bold">Únete a nosotros</h4>
                                <p class="text-muted">Regístrate para acceder a tu panel nutricional</p>
                            </div>
                            <form action="Login.php" method="POST">
                                <div id="registerAlert" class="alert alert-warning d-none" role="alert"></div>
                                <div class="mb-3">
                                    <label for="registerName" class="form-label fw-semibold">
                                        <i class="fas fa-user me-2 text-success"></i>Nombre Completo
                                    </label>
                                    <input type="text" class="form-control" id="registerName" name="nombre_completo" placeholder="Tu nombre completo" required>
                                </div>
                                <div class="mb-3">
                                    <label for="registerSexo" class="form-label fw-semibold">
                                        <i class="fas fa-venus-mars me-2 text-success"></i>Sexo
                                    </label>
                                    <select class="form-select" id="registerSexo" name="sexo" required>
                                        <option value="">Seleccionar</option>
<option value="M">Hombre</option>
<option value="F">Mujer</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="registerEmail" class="form-label fw-semibold">
                                        <i class="fas fa-envelope me-2 text-success"></i>Correo Electrónico
                                    </label>
                                    <input type="email" class="form-control" id="registerEmail" name="Correo_electronico" placeholder="tu@email.com" required>
                                </div>
                                <div class="mb-3">
                                    <label for="registerUser" class="form-label fw-semibold">
                                        <i class="fas fa-user-tag me-2 text-success"></i>Usuario
                                    </label>
                                    <input type="text" class="form-control" id="registerUser" name="Usuario" placeholder="Tu usuario" required>
                                </div>
                                <div class="mb-3">
                                    <label for="registerPassword" class="form-label fw-semibold">
                                        <i class="fas fa-lock me-2 text-success"></i>Contraseña
                                    </label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control" id="registerPassword" name="contrasena" placeholder="Tu contraseña" required>
                                        <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y" style="z-index: 10; text-decoration: none;" onclick="togglePasswordVisibility('registerPassword', this)">
                                            <i class="fas fa-eye text-muted"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="registerPasswordConfirm" class="form-label fw-semibold">
                                        <i class="fas fa-lock me-2 text-success"></i>Confirmar Contraseña
                                    </label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control" id="registerPasswordConfirm" name="contrasena_confirm" placeholder="Repite tu contraseña" required>
                                        <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y" style="z-index: 10; text-decoration: none;" onclick="togglePasswordVisibility('registerPasswordConfirm', this)">
                                            <i class="fas fa-eye text-muted"></i>
                                        </button>
                                    </div>
                                    <div id="passwordHelp" class="form-text text-danger d-none">Las contraseñas no coinciden</div>
                                    <div id="registerErrors" class="alert alert-danger d-none mt-2" role="alert"></div>
                                    <div id="registerRules" class="form-text text-muted mt-2">
                                        Requisitos: mínimo 8 caracteres, al menos una letra mayúscula, un número y un símbolo.
                                    </div>
                                </div>
                                <input type="hidden" name="origen" value="index">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success fw-bold">
                                        <i class="fas fa-user-plus me-2"></i>Regístrarse
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
    <script>
        // Toggle password visibility
        function togglePasswordVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

    document.addEventListener('DOMContentLoaded', function () {
        const pass = document.getElementById('registerPassword');
        const pass2 = document.getElementById('registerPasswordConfirm');
        const form = pass.closest('form');
        const help = document.getElementById('passwordHelp');
        const registerErrors = document.getElementById('registerErrors');
        const registerAlert = document.getElementById('registerAlert');

        function getStrengthErrors(v) {
            const errors = [];
            if (v.length < 8) errors.push('La contraseña debe tener al menos 8 caracteres.');
            if (!/[A-Z]/.test(v)) errors.push('Incluir al menos una letra mayúscula.');
            if (!/[0-9]/.test(v)) errors.push('Incluir al menos un número.');
            if (!/[^A-Za-z0-9]/.test(v)) errors.push('Incluir al menos un símbolo (ej: !@#$%).');
            return errors;
        }

        function validate() {
            const errors = [];
            const v = pass.value || '';

            // Match check
            if (pass2.value && v !== pass2.value) {
                help.classList.remove('d-none');
                pass2.classList.add('is-invalid');
                errors.push('Las contraseñas no coinciden.');
            } else {
                help.classList.add('d-none');
                pass2.classList.remove('is-invalid');
            }

            // Strength checks (only if user typed something)
            if (v.length > 0) {
                const s = getStrengthErrors(v);
                errors.push(...s);
            }

            if (errors.length) {
                registerErrors.innerHTML = '<ul class="mb-0"><li>' + errors.join('</li><li>') + '</li></ul>';
                registerErrors.classList.remove('d-none');
            } else {
                registerErrors.classList.add('d-none');
                registerErrors.innerHTML = '';
            }

            return errors.length === 0;
        }

        pass.addEventListener('input', function(){ validate(); if (registerAlert) registerAlert.classList.add('d-none'); });
        pass2.addEventListener('input', function(){ validate(); if (registerAlert) registerAlert.classList.add('d-none'); });
        form.addEventListener('submit', function (e) {
            e.preventDefault(); // take control so button state won't be left in spinner
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalHtml = submitBtn ? submitBtn.getAttribute('data-original-html') : null;

            // Ensure we have stored the original HTML to restore later
            if (submitBtn && !originalHtml) {
                submitBtn.setAttribute('data-original-html', submitBtn.innerHTML);
            }

            if (!validate()) {
                // Validation failed: show clear alert and restore UI so user can try again
                if (registerAlert) {
                    registerAlert.innerHTML = 'La contraseña no cumple los requisitos. Asegúrese de que tenga al menos 8 caracteres, una letra mayúscula, un número y un carácter especial.';
                    registerAlert.classList.remove('d-none');
                }
                // Re-enable submit button and restore its label
                if (submitBtn) {
                    submitBtn.disabled = false;
                    const orig = submitBtn.getAttribute('data-original-html') || '<i class="fas fa-user-plus me-2"></i>Regístrarse';
                    submitBtn.innerHTML = orig;
                }
                // Ensure password fields are enabled and focused for retry
                pass.removeAttribute('disabled');
                pass2.removeAttribute('disabled');
                pass.focus();
                // Auto-hide alert after a few seconds
                if (registerAlert) { setTimeout(() => registerAlert.classList.add('d-none'), 6000); }
                return;
            }

            // Passed client validation: show spinner and submit programmatically
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
                submitBtn.disabled = true;
            }
            // small timeout to allow UI update before navigating
            setTimeout(function(){ form.submit(); }, 50);
        });

            // Check URL parameters for modal and tab
            const urlParams = new URLSearchParams(window.location.search);
            const error = urlParams.get('error');
            const ok = urlParams.get('ok');
            const tab = urlParams.get('tab');

            if (error || ok) {
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('loginModal'));
                modal.show();

                // Switch to specific tab if specified
                if (tab) {
                    const tabTrigger = document.querySelector(`#${tab}-tab`);
                    if (tabTrigger) {
                        const tabInstance = new bootstrap.Tab(tabTrigger);
                        tabInstance.show();
                    }
                }
            }
        });
    </script>
</body>
</html>
