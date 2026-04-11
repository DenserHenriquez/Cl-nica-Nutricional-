<?php
// edicioninicio.php – Administración del carrusel de banners de inicio (solo Administrador)
session_start();
if (!isset($_SESSION['id_usuarios'])) { header('Location: index.php'); exit; }
if ($_SESSION['rol'] !== 'Administrador') { header('Location: inicio.php'); exit; }
require_once __DIR__ . '/db_connection.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ── Crear tabla si no existe ──────────────────────────────────────────────────
$conexion->query("CREATE TABLE IF NOT EXISTS inicio_banners (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    titulo      VARCHAR(200)  NOT NULL DEFAULT '',
    subtitulo   TEXT,
    btn_texto   VARCHAR(100),
    btn_link    VARCHAR(400),
    imagen      VARCHAR(500),
    bg_color    VARCHAR(50)   DEFAULT '#198754',
    orden       INT           DEFAULT 0,
    activo      TINYINT(1)    DEFAULT 1,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
)");
$conexion->query("CREATE TABLE IF NOT EXISTS inicio_config (
    clave   VARCHAR(80) PRIMARY KEY,
    valor   TEXT
)");
// Valor por defecto del intervalo
$conexion->query("INSERT IGNORE INTO inicio_config (clave,valor) VALUES ('carousel_interval','5000')");
// Leer intervalo actual
$carouselInterval = 5000;
$rc = $conexion->query("SELECT valor FROM inicio_config WHERE clave='carousel_interval'");
if ($rc && $rr = $rc->fetch_assoc()) $carouselInterval = max(1000,(int)$rr['valor']);

$uploadDir = __DIR__ . '/assets/images/banners/';
$uploadWeb = 'assets/images/banners/';
$msg = ''; $msgType = 'success';

// ── Tabla de tarjetas ─────────────────────────────────────────────────────────
$conexion->query("CREATE TABLE IF NOT EXISTS inicio_tarjetas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    icono       VARCHAR(100)  DEFAULT 'fa-star',
    titulo      VARCHAR(200)  NOT NULL DEFAULT '',
    descripcion TEXT,
    imagen      VARCHAR(500),
    enlace      VARCHAR(400),
    orden       INT           DEFAULT 0,
    activo      TINYINT(1)    DEFAULT 1,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
)");
$uploadDirTarjetas = __DIR__ . '/assets/images/tarjetas/';
$uploadWebTarjetas = 'assets/images/tarjetas/';
if (!is_dir($uploadDirTarjetas)) @mkdir($uploadDirTarjetas, 0755, true);

// ── Tabla carrusel pacientes (pac_banners) ────────────────────────────────────────
$conexion->query("CREATE TABLE IF NOT EXISTS pac_banners (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    titulo      VARCHAR(200)  NOT NULL DEFAULT '',
    subtitulo   TEXT,
    btn_texto   VARCHAR(100),
    btn_link    VARCHAR(400),
    imagen      VARCHAR(500),
    bg_color    VARCHAR(50)   DEFAULT '#198754',
    orden       INT           DEFAULT 0,
    activo      TINYINT(1)    DEFAULT 1,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
)");
$uploadDirPac = __DIR__ . '/assets/images/pac_banners/';
$uploadWebPac = 'assets/images/pac_banners/';
if (!is_dir($uploadDirPac)) @mkdir($uploadDirPac, 0755, true);
// Config intervalo paciente
$conexion->query("INSERT IGNORE INTO inicio_config (clave,valor) VALUES ('pac_carousel_interval','5000')");
$pacCarouselInterval = 5000;
$rcp = $conexion->query("SELECT valor FROM inicio_config WHERE clave='pac_carousel_interval'");
if ($rcp && $rrp = $rcp->fetch_assoc()) $pacCarouselInterval = max(1000,(int)$rrp['valor']);

$uploadDir = __DIR__ . '/assets/images/banners/';
$uploadWeb = 'assets/images/banners/';

// ── Acciones POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // AGREGAR BANNER
    if ($action === 'add') {
        $titulo    = trim($_POST['titulo']    ?? '');
        $subtitulo = trim($_POST['subtitulo'] ?? '');
        $btn_texto = trim($_POST['btn_texto'] ?? '');
        $btn_link  = trim($_POST['btn_link']  ?? '');
        $bg_color  = trim($_POST['bg_color']  ?? '#198754');
        $orden     = (int)($_POST['orden']    ?? 0);
        $imgPath   = null;

        // Subir imagen si viene
        if (!empty($_FILES['imagen']['name'])) {
            $ext  = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp','avif'];
            if (in_array($ext, $allowed)) {
                $fname = 'banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadDir . $fname)) {
                    $imgPath = $uploadWeb . $fname;
                } else { $msg = 'Error al subir la imagen.'; $msgType='danger'; }
            } else { $msg = 'Formato de imagen no permitido.'; $msgType='danger'; }
        }

        if (!$msg) {
            $stmt = $conexion->prepare("INSERT INTO inicio_banners (titulo,subtitulo,btn_texto,btn_link,imagen,bg_color,orden) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('ssssssi', $titulo, $subtitulo, $btn_texto, $btn_link, $imgPath, $bg_color, $orden);
            if ($stmt->execute()) { $msg = 'Banner agregado correctamente.'; }
            else { $msg = 'Error al guardar el banner.'; $msgType='danger'; }
            $stmt->close();
        }
    }

    // EDITAR BANNER
    if ($action === 'edit') {
        $id        = (int)($_POST['id']        ?? 0);
        $titulo    = trim($_POST['titulo']     ?? '');
        $subtitulo = trim($_POST['subtitulo']  ?? '');
        $btn_texto = trim($_POST['btn_texto']  ?? '');
        $btn_link  = trim($_POST['btn_link']   ?? '');
        $bg_color  = trim($_POST['bg_color']   ?? '#198754');
        $orden     = (int)($_POST['orden']     ?? 0);
        $imgPath   = trim($_POST['img_actual'] ?? '');

        if (!empty($_FILES['imagen']['name'])) {
            $ext  = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp','avif'];
            if (in_array($ext, $allowed)) {
                $fname = 'banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadDir . $fname)) {
                    // borrar imagen anterior
                    if ($imgPath && file_exists(__DIR__ . '/' . $imgPath)) @unlink(__DIR__ . '/' . $imgPath);
                    $imgPath = $uploadWeb . $fname;
                } else { $msg = 'Error al subir la imagen.'; $msgType='danger'; }
            } else { $msg = 'Formato de imagen no permitido.'; $msgType='danger'; }
        }

        if (!$msg) {
            $stmt = $conexion->prepare("UPDATE inicio_banners SET titulo=?,subtitulo=?,btn_texto=?,btn_link=?,imagen=?,bg_color=?,orden=? WHERE id=?");
            $stmt->bind_param('ssssssii', $titulo, $subtitulo, $btn_texto, $btn_link, $imgPath, $bg_color, $orden, $id);
            if ($stmt->execute()) { $msg = 'Banner actualizado.'; }
            else { $msg = 'Error al actualizar.'; $msgType='danger'; }
            $stmt->close();
        }
    }

    // TOGGLE ACTIVO
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conexion->prepare("UPDATE inicio_banners SET activo = NOT activo WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        header('Location: edicioninicio.php'); exit;
    }
    // GUARDAR CONFIGURACIÓN
    if ($action === 'save_config') {
        $interval = max(1000, (int)($_POST['carousel_interval'] ?? 5000));
        $stmt = $conexion->prepare("INSERT INTO inicio_config (clave,valor) VALUES ('carousel_interval',?) ON DUPLICATE KEY UPDATE valor=?");
        $stmt->bind_param('ii', $interval, $interval);
        $stmt->execute();
        $stmt->close();
        $carouselInterval = $interval;
        $msg = 'Configuración guardada.'; $msgType = 'success';
    }

    // ── TARJETAS DE SERVICIOS ──────────────────────────────────────────────
    if ($action === 'add_tarjeta') {
        $titulo      = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $icono       = trim($_POST['icono'] ?? 'fa-star');
        $enlace      = trim($_POST['enlace'] ?? '');
        $orden       = (int)($_POST['orden'] ?? 0);
        $imgPath     = null;

        if (!empty($_FILES['imagen_tarjeta']['name'])) {
            $ext = strtolower(pathinfo($_FILES['imagen_tarjeta']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp','avif'];
            if (in_array($ext, $allowed)) {
                $fname = 'tarjeta_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['imagen_tarjeta']['tmp_name'], $uploadDirTarjetas . $fname)) {
                    $imgPath = $uploadWebTarjetas . $fname;
                } else { $msg = 'Error al subir imagen.'; $msgType='danger'; }
            } else { $msg = 'Formato no permitido.'; $msgType='danger'; }
        }

        if (!$msg) {
            $stmt = $conexion->prepare("INSERT INTO inicio_tarjetas (icono,titulo,descripcion,imagen,enlace,orden) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('sssssi', $icono, $titulo, $descripcion, $imgPath, $enlace, $orden);
            if ($stmt->execute()) { $msg = 'Tarjeta agregada correctamente.'; }
            else { $msg = 'Error al guardar tarjeta.'; $msgType='danger'; }
            $stmt->close();
        }
    }

    if ($action === 'edit_tarjeta') {
        $id          = (int)($_POST['id'] ?? 0);
        $titulo      = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $icono       = trim($_POST['icono'] ?? 'fa-star');
        $enlace      = trim($_POST['enlace'] ?? '');
        $orden       = (int)($_POST['orden'] ?? 0);
        $imgPath     = trim($_POST['img_actual'] ?? '');

        if (!empty($_FILES['imagen_tarjeta']['name'])) {
            $ext = strtolower(pathinfo($_FILES['imagen_tarjeta']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp','avif'];
            if (in_array($ext, $allowed)) {
                $fname = 'tarjeta_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['imagen_tarjeta']['tmp_name'], $uploadDirTarjetas . $fname)) {
                    if ($imgPath && file_exists(__DIR__ . '/' . $imgPath)) @unlink(__DIR__ . '/' . $imgPath);
                    $imgPath = $uploadWebTarjetas . $fname;
                } else { $msg = 'Error al subir imagen.'; $msgType='danger'; }
            } else { $msg = 'Formato no permitido.'; $msgType='danger'; }
        }

        if (!$msg) {
            $stmt = $conexion->prepare("UPDATE inicio_tarjetas SET icono=?,titulo=?,descripcion=?,imagen=?,enlace=?,orden=? WHERE id=?");
            $stmt->bind_param('sssssii', $icono, $titulo, $descripcion, $imgPath, $enlace, $orden, $id);
            if ($stmt->execute()) { $msg = 'Tarjeta actualizada.'; }
            else { $msg = 'Error al actualizar.'; $msgType='danger'; }
            $stmt->close();
        }
    }

    if ($action === 'toggle_tarjeta') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conexion->prepare("UPDATE inicio_tarjetas SET activo = NOT activo WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        header('Location: edicioninicio.php#tarjetas'); exit;
    }

    // ── PAC_BANNERS: carrusel del dashboard de pacientes ─────────────────────
    if ($action === 'add_pac') {
        $titulo    = trim($_POST['titulo']    ?? '');
        $subtitulo = trim($_POST['subtitulo'] ?? '');
        $btn_texto = trim($_POST['btn_texto'] ?? '');
        $btn_link  = trim($_POST['btn_link']  ?? '');
        $bg_color  = trim($_POST['bg_color']  ?? '#198754');
        $orden     = (int)($_POST['orden']    ?? 0);
        $imgPath   = null;
        if (!empty($_FILES['imagen_pac']['name'])) {
            $ext = strtolower(pathinfo($_FILES['imagen_pac']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp','avif'];
            if (in_array($ext, $allowed)) {
                $fname = 'pac_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['imagen_pac']['tmp_name'], $uploadDirPac . $fname)) {
                    $imgPath = $uploadWebPac . $fname;
                } else { $msg = 'Error al subir imagen.'; $msgType='danger'; }
            } else { $msg = 'Formato no permitido.'; $msgType='danger'; }
        }
        if (!$msg) {
            $stmt = $conexion->prepare("INSERT INTO pac_banners (titulo,subtitulo,btn_texto,btn_link,imagen,bg_color,orden) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('ssssssi', $titulo, $subtitulo, $btn_texto, $btn_link, $imgPath, $bg_color, $orden);
            if ($stmt->execute()) { $msg = 'Banner de paciente agregado.'; }
            else { $msg = 'Error al guardar.'; $msgType='danger'; }
            $stmt->close();
        }
    }

    if ($action === 'edit_pac') {
        $id        = (int)($_POST['id']        ?? 0);
        $titulo    = trim($_POST['titulo']     ?? '');
        $subtitulo = trim($_POST['subtitulo']  ?? '');
        $btn_texto = trim($_POST['btn_texto']  ?? '');
        $btn_link  = trim($_POST['btn_link']   ?? '');
        $bg_color  = trim($_POST['bg_color']   ?? '#198754');
        $orden     = (int)($_POST['orden']     ?? 0);
        $imgPath   = trim($_POST['img_actual'] ?? '');
        if (!empty($_FILES['imagen_pac']['name'])) {
            $ext = strtolower(pathinfo($_FILES['imagen_pac']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp','avif'];
            if (in_array($ext, $allowed)) {
                $fname = 'pac_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['imagen_pac']['tmp_name'], $uploadDirPac . $fname)) {
                    if ($imgPath && file_exists(__DIR__ . '/' . $imgPath)) @unlink(__DIR__ . '/' . $imgPath);
                    $imgPath = $uploadWebPac . $fname;
                } else { $msg = 'Error al subir imagen.'; $msgType='danger'; }
            } else { $msg = 'Formato no permitido.'; $msgType='danger'; }
        }
        if (!$msg) {
            $stmt = $conexion->prepare("UPDATE pac_banners SET titulo=?,subtitulo=?,btn_texto=?,btn_link=?,imagen=?,bg_color=?,orden=? WHERE id=?");
            $stmt->bind_param('ssssssii', $titulo, $subtitulo, $btn_texto, $btn_link, $imgPath, $bg_color, $orden, $id);
            if ($stmt->execute()) { $msg = 'Banner de paciente actualizado.'; }
            else { $msg = 'Error al actualizar.'; $msgType='danger'; }
            $stmt->close();
        }
    }

    if ($action === 'toggle_pac') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conexion->prepare("UPDATE pac_banners SET activo = NOT activo WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        header('Location: edicioninicio.php#pac'); exit;
    }

    if ($action === 'save_pac_config') {
        $interval = max(1000, (int)($_POST['pac_carousel_interval'] ?? 5000));
        $stmt = $conexion->prepare("INSERT INTO inicio_config (clave,valor) VALUES ('pac_carousel_interval',?) ON DUPLICATE KEY UPDATE valor=?");
        $stmt->bind_param('ii', $interval, $interval);
        $stmt->execute();
        $stmt->close();
        $pacCarouselInterval = $interval;
        $msg = 'Configuración del carrusel paciente guardada.'; $msgType = 'success';
    }
} // end POST

// ── Acciones GET ──────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id  = (int)$_GET['delete'];
    $stmt = $conexion->prepare("SELECT imagen FROM inicio_banners WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        if ($row['imagen'] && file_exists(__DIR__ . '/' . $row['imagen'])) @unlink(__DIR__ . '/' . $row['imagen']);
    }
    $stmt->close();
    
    $stmt = $conexion->prepare("DELETE FROM inicio_banners WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: edicioninicio.php?ok=deleted'); exit;
}
if (isset($_GET['delete_tarjeta'])) {
    $id = (int)$_GET['delete_tarjeta'];
    $stmt = $conexion->prepare("SELECT imagen FROM inicio_tarjetas WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        if ($row['imagen'] && file_exists(__DIR__ . '/' . $row['imagen'])) @unlink(__DIR__ . '/' . $row['imagen']);
    }
    $stmt->close();
    
    $stmt = $conexion->prepare("DELETE FROM inicio_tarjetas WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: edicioninicio.php?ok=deleted#tarjetas'); exit;
}
if (isset($_GET['delete_pac'])) {
    $id  = (int)$_GET['delete_pac'];
    $stmt = $conexion->prepare("SELECT imagen FROM pac_banners WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        if ($row['imagen'] && file_exists(__DIR__ . '/' . $row['imagen'])) @unlink(__DIR__ . '/' . $row['imagen']);
    }
    $stmt->close();
    
    $stmt = $conexion->prepare("DELETE FROM pac_banners WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: edicioninicio.php?ok=deleted#pac'); exit;
}
if (isset($_GET['ok'])) {
    $msg = $_GET['ok'] === 'deleted' ? 'Elemento eliminado.' : 'Operación exitosa.';
}

// ── Cargar banners ─────────────────────────────────────────────────────────────
$banners = [];
$res = $conexion->query("SELECT * FROM inicio_banners ORDER BY orden ASC, id ASC");
if ($res) { while ($r = $res->fetch_assoc()) $banners[] = $r; }

// Banner en edición
$editBanner = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $stmt = $conexion->prepare("SELECT * FROM inicio_banners WHERE id = ?");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $res2 = $stmt->get_result();
    if ($res2) $editBanner = $res2->fetch_assoc();
    $stmt->close();
}

// ── Cargar tarjetas ────────────────────────────────────────────────────────────
$tarjetas = [];
$resT = $conexion->query("SELECT * FROM inicio_tarjetas ORDER BY orden ASC, id ASC");
if ($resT) { while ($r = $resT->fetch_assoc()) $tarjetas[] = $r; }

$editTarjeta = null;
if (isset($_GET['edit_tarjeta'])) {
    $etid = (int)$_GET['edit_tarjeta'];
    $stmt = $conexion->prepare("SELECT * FROM inicio_tarjetas WHERE id = ?");
    $stmt->bind_param("i", $etid);
    $stmt->execute();
    $res3 = $stmt->get_result();
    if ($res3) $editTarjeta = $res3->fetch_assoc();
    $stmt->close();
}

// ── Cargar pac_banners ──────────────────────────────────────────────────────
$pacBanners = [];
$resPac = $conexion->query("SELECT * FROM pac_banners ORDER BY orden ASC, id ASC");
if ($resPac) { while ($r = $resPac->fetch_assoc()) $pacBanners[] = $r; }

$editPac = null;
if (isset($_GET['edit_pac'])) {
    $epid = (int)$_GET['edit_pac'];
    $stmt = $conexion->prepare("SELECT * FROM pac_banners WHERE id = ?");
    $stmt->bind_param("i", $epid);
    $stmt->execute();
    $res4 = $stmt->get_result();
    if ($res4) $editPac = $res4->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Edición de Inicio – Banners</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body { background:#f8fafc; font-family:'Segoe UI',system-ui,sans-serif; }
    .header-section { background:linear-gradient(135deg,#198754 0%,#146c43 100%); color:white; padding:1.1rem 1.6rem; margin-bottom:1rem; border-radius:12px; }
    .header-section h1 { font-size:2.2rem; font-weight:700; margin:0; line-height:1.3; }
    .header-section p  { font-size:1.05rem; opacity:0.92; margin:0; }
    .medical-icon { font-size:1.9rem; color:#ffffff; }
    .page-header { font-size:1.7rem; font-weight:700; color:#0d5132; margin-bottom:6px; }
    .page-sub    { color:#6c757d; font-size:.97rem; margin-bottom:24px; }
    .banner-card {
        background:#fff; border:1px solid #e9ecef; border-radius:16px;
        overflow:hidden; box-shadow:0 4px 14px rgba(0,0,0,.06);
        transition:.2s;
    }
    .banner-card:hover { box-shadow:0 8px 24px rgba(0,0,0,.10); }
    .banner-preview {
        height:130px; position:relative; display:flex;
        align-items:center; justify-content:center;
        background:#e9ecef; overflow:hidden;
    }
    .banner-preview img { width:100%; height:100%; object-fit:cover; }
    .banner-preview .overlay {
        position:absolute; inset:0;
        background:rgba(0,0,0,.38);
        display:flex; flex-direction:column;
        align-items:center; justify-content:center;
        padding:14px;
        text-align:center;
    }
    .banner-preview .overlay h5 { color:#fff; font-size:1.1rem; font-weight:700; margin:0; text-shadow:0 2px 6px rgba(0,0,0,.5); }
    .banner-preview .overlay small { color:#ffffffcc; font-size:.8rem; margin-top:4px; }
    .banner-preview.no-img { background:#e9ecef; }
    .banner-preview.no-img .placeholder-icon { font-size:3rem; color:#adb5bd; }
    .status-dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
    .status-dot.on  { background:#198754; }
    .status-dot.off { background:#adb5bd; }
    .form-section { background:#fff; border:1px solid #e9ecef; border-radius:16px; padding:18px 20px; box-shadow:0 4px 14px rgba(0,0,0,.05); }
    .form-section h5 { font-weight:700; color:#0d5132; margin-bottom:14px; font-size:1.05rem; }
    .color-swatch { width:36px; height:36px; border-radius:8px; border:2px solid #dee2e6; cursor:pointer; }
    .preview-img-thumb { max-height:90px; border-radius:10px; object-fit:cover; border:1px solid #dee2e6; }
    .order-badge { background:#e8f5e9; color:#0d5132; font-size:.72rem; font-weight:700; padding:2px 8px; border-radius:20px; }
    .empty-state { text-align:center; padding:36px 16px; color:#adb5bd; }
    .empty-state i { font-size:3.5rem; display:block; margin-bottom:12px; }
    /* preview carousel */
    .prev-carousel-wrap { border-radius:14px; overflow:hidden; box-shadow:0 4px 18px rgba(0,0,0,.12); margin-bottom:8px; }
    .prev-carousel .carousel-item { height:170px; }
    .prev-carousel .slide-bg { position:absolute;inset:0;background-size:cover;background-position:center; }
    .prev-carousel .slide-overlay { position:absolute;inset:0;background:linear-gradient(90deg,rgba(0,0,0,.55) 0%,rgba(0,0,0,.1) 100%); }
    .prev-carousel .slide-content { position:relative;z-index:2;height:170px;display:flex;flex-direction:column;justify-content:center;padding:18px 28px;max-width:500px; }
    .prev-carousel .slide-title { font-size:1.3rem;font-weight:800;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,.4);margin-bottom:6px;line-height:1.2; }
    .prev-carousel .slide-sub { font-size:.85rem;color:rgba(255,255,255,.88);margin-bottom:12px;text-shadow:0 1px 4px rgba(0,0,0,.3); }
    .prev-carousel .slide-btn { align-self:flex-start;background:#fff;color:#0d5132;font-weight:700;border:none;border-radius:50px;padding:6px 20px;font-size:.85rem;text-decoration:none; }
    .prev-carousel .carousel-indicators [data-bs-target] { width:20px;height:3px;border-radius:3px;background:rgba(255,255,255,.5);border:none; }
    .prev-carousel .carousel-indicators .active { background:#fff; }
    .config-box { background:#fff;border:1px solid #e9ecef;border-radius:14px;padding:14px 16px;box-shadow:0 2px 8px rgba(0,0,0,.05); }
    /* Tarjetas admin */
    .tarjeta-admin-card { background:#fff; border:1px solid #e9ecef; border-radius:14px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,.05); transition:.2s; }
    .tarjeta-admin-card:hover { box-shadow:0 6px 20px rgba(0,0,0,.08); }
    .tarjeta-admin-img { height:120px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .tarjeta-admin-img img { width:100%; height:100%; object-fit:cover; }
    .tarjeta-admin-icon { width:50px; height:50px; border-radius:12px; background:#e8f5e9; display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:#198754; }
    .section-divider { border:none; border-top:2px dashed #c8e6c9; margin:40px 0 32px; }
    .icon-picker-grid { display:grid; grid-template-columns:repeat(8,1fr); gap:6px; }
    .icon-picker-btn { width:36px; height:36px; border:1px solid #dee2e6; border-radius:8px; background:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:.2s; font-size:.9rem; color:#495057; }
    .icon-picker-btn:hover, .icon-picker-btn.active { background:#e8f5e9; border-color:#198754; color:#198754; }
    /* Tabs */
    .admin-tabs { background:#fff; border-radius:14px; padding:6px; box-shadow:0 2px 10px rgba(0,0,0,.06); margin-bottom:12px; }
    .admin-tabs .nav-link { border:none; border-radius:10px; padding:9px 20px; font-weight:600; font-size:.9rem; color:#6c757d; transition:.2s; }
    .admin-tabs .nav-link:hover { background:#f0faf4; color:#0d5132; }
    .admin-tabs .nav-link.active { background:linear-gradient(135deg,#198754 0%,#146c43 100%); color:#fff !important; box-shadow:0 4px 12px rgba(25,135,84,.3); }
    .admin-tabs .nav-link .badge { font-size:.7rem; vertical-align:middle; }
    .tab-section-desc { color:#6c757d; font-size:.85rem; margin-bottom:10px; padding:0 4px; }
</style>
</head>
<body>

<div class="container-fluid px-4">

    <!-- Header Section -->
    <div class="header-section d-flex align-items-center gap-3" style="margin-top:12px;">
        <div class="medical-icon"><i class="bi bi-images"></i></div>
        <div>
            <h1>Edición de Inicio</h1>
            <p>Gestiona los banners del carrusel y las tarjetas de servicios de la página principal.</p>
        </div>
    </div>

</div>

<div class="container-fluid" style="max-width:1600px;padding:8px 20px 0;">

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?= $msgType==='success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?= e($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <ul class="nav nav-pills admin-tabs mb-0" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="banners-tab" data-bs-toggle="pill" data-bs-target="#banners-pane" type="button" role="tab">
                <i class="bi bi-images me-2"></i>Carrusel Principal
                <span class="badge bg-success ms-1"><?= count($banners); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pac-tab" data-bs-toggle="pill" data-bs-target="#pac-pane" type="button" role="tab">
                <i class="bi bi-person-video3 me-2"></i>Carrusel Pacientes
                <span class="badge bg-warning text-dark ms-1"><?= count($pacBanners); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tarjetas-tab" data-bs-toggle="pill" data-bs-target="#tarjetas-pane" type="button" role="tab">
                <i class="bi bi-grid-3x3-gap-fill me-2"></i>Tarjetas de Servicios
                <span class="badge bg-primary ms-1"><?= count($tarjetas); ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="adminTabsContent">
        <!-- ══════ TAB 1: CARRUSEL PRINCIPAL ══════ -->
        <div class="tab-pane fade show active" id="banners-pane" role="tabpanel">
            <p class="tab-section-desc"><i class="bi bi-info-circle me-1"></i>Banners del carrusel que se muestra en la página de acceso público (index.php).</p>

    <div class="row g-3">

        <!-- ═══ PREVIEW + CONFIG ─ fila superior completa ═══ -->
        <div class="col-12">
            <div class="row g-3">
                <!-- Vista previa del carrusel -->
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-eye-fill text-success"></i>
                        <strong style="color:#0d5132;">Vista previa del carrusel</strong>
                        <span class="badge bg-secondary ms-1" style="font-size:.72rem;">Pacientes verán esto</span>
                    </div>
                    <?php
                    $prevSlides = array_filter($banners, fn($b) => $b['activo']);
                    $prevSlides = array_values($prevSlides);
                    ?>
                    <?php if (empty($prevSlides)): ?>
                    <div class="prev-carousel-wrap d-flex align-items-center justify-content-center" style="height:170px;background:#e9ecef;border-radius:14px;">
                        <div class="text-center text-muted">
                            <i class="bi bi-image" style="font-size:2.5rem;"></i>
                            <p class="mt-2 mb-0 small">No hay banners activos para previsualizar.</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="prev-carousel-wrap">
                        <div id="previewCarousel" class="carousel slide prev-carousel" data-bs-ride="carousel" data-bs-interval="<?= $carouselInterval; ?>">
                            <?php if (count($prevSlides) > 1): ?>
                            <div class="carousel-indicators">
                                <?php foreach ($prevSlides as $pi => $ps): ?>
                                <button type="button" data-bs-target="#previewCarousel" data-bs-slide-to="<?= $pi; ?>"
                                    <?= $pi===0 ? 'class="active" aria-current="true"' : ''; ?>></button>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <div class="carousel-inner">
                                <?php foreach ($prevSlides as $pi => $ps):
                                    $imgSrc = $ps['imagen'] && file_exists(__DIR__.'/'.$ps['imagen']) ? $ps['imagen'] : null;
                                ?>
                                <div class="carousel-item <?= $pi===0?'active':''; ?>">
                                    <?php if ($imgSrc): ?>
                                    <div class="slide-bg" style="background-image:url('<?= htmlspecialchars($imgSrc,ENT_QUOTES); ?>');"></div>
                                    <?php else: ?>
                                    <div class="slide-bg" style="background:<?= htmlspecialchars($ps['bg_color']??'#198754',ENT_QUOTES); ?>;"></div>
                                    <?php endif; ?>
                                    <div class="slide-overlay"></div>
                                    <div class="slide-content">
                                        <div class="slide-title"><?= htmlspecialchars($ps['titulo'],ENT_QUOTES); ?></div>
                                        <?php if ($ps['subtitulo']): ?>
                                        <div class="slide-sub"><?= htmlspecialchars(mb_strimwidth($ps['subtitulo'],0,80,'...'),ENT_QUOTES); ?></div>
                                        <?php endif; ?>
                                        <?php if ($ps['btn_texto']): ?>
                                        <span class="slide-btn"><?= htmlspecialchars($ps['btn_texto'],ENT_QUOTES); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($prevSlides) > 1): ?>
                            <button class="carousel-control-prev" type="button" data-bs-target="#previewCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon"></span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#previewCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon"></span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p class="text-muted small mt-1 mb-0"><i class="bi bi-info-circle me-1"></i>Solo se muestran banners <strong>activos</strong>. Usa las flechas para navegar.</p>
                    <?php endif; ?>
                </div>

                <!-- Configuración del intervalo -->
                <div class="col-lg-4">
                    <div class="config-box h-100">
                        <h6 class="fw-700 mb-3" style="color:#0d5132;"><i class="bi bi-sliders me-2"></i>Configuración del Carrusel</h6>
                        <form method="post">
                            <input type="hidden" name="action" value="save_config">
                            <div class="mb-3">
                                <label class="form-label fw-600" style="font-size:.9rem;">Tiempo entre slides</label>
                                <div class="input-group">
                                    <input type="number" name="carousel_interval"
                                        class="form-control"
                                        value="<?= $carouselInterval; ?>"
                                        min="1000" max="30000" step="500"
                                        required>
                                    <span class="input-group-text">ms</span>
                                </div>
                                <div class="form-text">Ej: 5000 = 5 segundos, 3000 = 3 segundos</div>
                            </div>
                            <!-- Selector rápido -->
                            <div class="mb-3">
                                <label class="form-label fw-600" style="font-size:.9rem;">Selección rápida</label>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php foreach ([3000=>'3s',5000=>'5s',7000=>'7s',10000=>'10s'] as $ms=>$label): ?>
                                    <button type="button" onclick="document.querySelector('[name=carousel_interval]').value=<?= $ms; ?>"
                                        class="btn btn-sm <?= $carouselInterval===$ms ? 'btn-success' : 'btn-outline-secondary'; ?>">
                                        <?= $label; ?>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success w-100 btn-sm">
                                <i class="bi bi-save me-1"></i>Guardar configuración
                            </button>
                        </form>
                        <hr class="my-3">
                        <div class="small text-muted">
                            <i class="bi bi-clock-history me-1"></i>
                            Intervalo actual: <strong><?= number_format($carouselInterval/1000,1); ?> segundos</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ COLUMNA IZQUIERDA: Lista de banners ═══ -->
        <div class="col-lg-7">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="mb-0 fw-700" style="color:#0d5132;">
                    <i class="bi bi-collection me-2"></i>Banners actuales
                    <span class="badge bg-success ms-2"><?= count($banners); ?></span>
                </h5>
                <a href="#formAdd" class="btn btn-success btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Nuevo banner
                </a>
            </div>

            <?php if (empty($banners)): ?>
            <div class="banner-card">
                <div class="empty-state">
                    <i class="bi bi-image-alt"></i>
                    <p class="mb-0 fw-600">No hay banners configurados</p>
                    <p class="text-muted small">Agrega tu primer banner usando el formulario de abajo.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="row g-3">
                <?php foreach ($banners as $b): ?>
                <div class="col-12">
                    <div class="banner-card">
                        <div class="row g-0">
                            <!-- Preview -->
                            <div class="col-md-4">
                                <div class="banner-preview" style="<?= $b['imagen'] ? '' : 'background:'.e($b['bg_color']).';'; ?>">
                                    <?php if ($b['imagen'] && file_exists(__DIR__ . '/' . $b['imagen'])): ?>
                                        <img src="<?= e($b['imagen']); ?>" alt="banner">
                                    <?php else: ?>
                                        <div style="background:<?= e($b['bg_color']); ?>;position:absolute;inset:0;"></div>
                                    <?php endif; ?>
                                    <div class="overlay">
                                        <h5><?= e($b['titulo'] ?: 'Sin título'); ?></h5>
                                        <?php if ($b['subtitulo']): ?><small><?= e(mb_strimwidth($b['subtitulo'],0,60,'…')); ?></small><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <!-- Info -->
                            <div class="col-md-8">
                                <div class="p-3">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="status-dot <?= $b['activo'] ? 'on' : 'off'; ?>"></span>
                                        <span style="font-weight:700;color:#0d5132;font-size:1rem;"><?= e($b['titulo'] ?: 'Sin título'); ?></span>
                                        <span class="order-badge ms-auto">Orden: <?= (int)$b['orden']; ?></span>
                                    </div>
                                    <?php if ($b['subtitulo']): ?>
                                    <p class="text-muted small mb-2" style="line-height:1.4;"><?= e(mb_strimwidth($b['subtitulo'],0,100,'…')); ?></p>
                                    <?php endif; ?>
                                    <?php if ($b['btn_texto']): ?>
                                    <span class="badge" style="background:<?= e($b['bg_color']); ?>;font-size:.75rem;">
                                        <i class="bi bi-cursor-fill me-1"></i><?= e($b['btn_texto']); ?>
                                    </span>
                                    <?php endif; ?>
                                    <div class="d-flex gap-2 mt-3 flex-wrap">
                                        <a href="?edit=<?= $b['id']; ?>#formEdit" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-pencil me-1"></i>Editar
                                        </a>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= $b['id']; ?>">
                                            <button class="btn btn-sm <?= $b['activo'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>">
                                                <i class="bi bi-<?= $b['activo'] ? 'eye-slash' : 'eye'; ?> me-1"></i>
                                                <?= $b['activo'] ? 'Ocultar' : 'Mostrar'; ?>
                                            </button>
                                        </form>
                                        <a href="?delete=<?= $b['id']; ?>"
                                           onclick="return confirm('¿Eliminar este banner?');"
                                           class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-trash me-1"></i>Eliminar
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ═══ COLUMNA DERECHA: Formulario ═══ -->
        <div class="col-lg-5">

            <?php if ($editBanner): ?>
            <!-- FORMULARIO EDITAR -->
            <div class="form-section mb-4" id="formEdit">
                <h5><i class="bi bi-pencil-square me-2 text-primary"></i>Editar Banner</h5>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?= $editBanner['id']; ?>">
                    <input type="hidden" name="img_actual" value="<?= e($editBanner['imagen'] ?? ''); ?>">

                    <div class="mb-3">
                        <label class="form-label fw-600">Título <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" class="form-control" value="<?= e($editBanner['titulo']); ?>" required maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Subtítulo</label>
                        <textarea name="subtitulo" class="form-control" rows="2" maxlength="400"><?= e($editBanner['subtitulo'] ?? ''); ?></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label fw-600">Texto del botón</label>
                            <input type="text" name="btn_texto" class="form-control" value="<?= e($editBanner['btn_texto'] ?? ''); ?>" maxlength="100" placeholder="ej: Ver más">
                        </div>
                        <div class="col">
                            <label class="form-label fw-600">Enlace del botón</label>
                            <input type="text" name="btn_link" class="form-control" value="<?= e($editBanner['btn_link'] ?? ''); ?>" maxlength="400" placeholder="ej: Disponibilidad_citas.php">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label fw-600">Color de fondo</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" name="bg_color" class="form-control form-control-color" value="<?= e($editBanner['bg_color'] ?? '#198754'); ?>" style="width:50px;height:38px;">
                                <span class="text-muted small">Usado cuando no hay imagen</span>
                            </div>
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-600">Orden</label>
                            <input type="number" name="orden" class="form-control" value="<?= (int)$editBanner['orden']; ?>" min="0" max="99">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Cambiar imagen</label>
                        <?php if ($editBanner['imagen'] && file_exists(__DIR__ . '/' . $editBanner['imagen'])): ?>
                        <div class="mb-2"><img src="<?= e($editBanner['imagen']); ?>" class="preview-img-thumb" alt="actual"></div>
                        <?php endif; ?>
                        <input type="file" name="imagen" class="form-control" accept="image/*" onchange="previewImg(this,'prevEdit')">
                        <img id="prevEdit" class="preview-img-thumb mt-2 d-none" alt="preview">
                        <div class="form-text">Déjalo vacío para conservar la imagen actual. JPG, PNG, WebP, AVIF.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-save me-1"></i>Guardar cambios</button>
                        <a href="edicioninicio.php" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- FORMULARIO AGREGAR -->
            <div class="form-section" id="formAdd">
                <h5><i class="bi bi-plus-circle me-2 text-success"></i>Agregar Nuevo Banner</h5>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label fw-600">Título <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" class="form-control" required maxlength="200" placeholder="ej: ¡Tu salud, nuestra prioridad!">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Subtítulo</label>
                        <textarea name="subtitulo" class="form-control" rows="2" maxlength="400" placeholder="Texto descriptivo opcional debajo del título"></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label fw-600">Texto del botón</label>
                            <input type="text" name="btn_texto" class="form-control" maxlength="100" placeholder="ej: Agendar cita">
                        </div>
                        <div class="col">
                            <label class="form-label fw-600">Enlace del botón</label>
                            <input type="text" name="btn_link" class="form-control" maxlength="400" placeholder="ej: Disponibilidad_citas.php">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label fw-600">Color de fondo</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" name="bg_color" class="form-control form-control-color" value="#198754" style="width:50px;height:38px;">
                                <span class="text-muted small">Cuando no hay imagen</span>
                            </div>
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-600">Orden <span class="text-muted small">(0=primero)</span></label>
                            <input type="number" name="orden" class="form-control" value="<?= count($banners); ?>" min="0" max="99">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Imagen del banner</label>
                        <input type="file" name="imagen" class="form-control" accept="image/*" onchange="previewImg(this,'prevAdd')">
                        <img id="prevAdd" class="preview-img-thumb mt-2 d-none" alt="preview">
                        <div class="form-text">JPG, PNG, WebP, AVIF. Recomendado: 1400×500px.</div>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-plus-lg me-1"></i>Agregar Banner
                    </button>
                </form>
            </div>

            <!-- Instrucciones -->
            <div class="mt-3 p-3 rounded-3" style="background:#f0faf4;border:1px solid #c8e6c9;">
                <h6 class="text-success mb-2"><i class="bi bi-info-circle me-1"></i>Consejos</h6>
                <ul class="small text-muted mb-0 ps-3">
                    <li>Imagen recomendada: <strong>1400 × 500 px</strong> para mejor calidad.</li>
                    <li>El campo <em>Orden</em> determina la posición en el carrusel (0 = primero).</li>
                    <li>Si no sube imagen, el color de fondo sólido se usará como fondo.</li>
                    <li>Usa <em>Ocultar</em> para desactivar un banner sin eliminarlo.</li>
                    <li>El botón es opcional; déjalo vacío si no deseas un CTA en el banner.</li>
                </ul>
            </div>
        </div>

    </div>
        </div><!-- end banners-pane -->

        <!-- ══════ TAB 2: CARRUSEL PACIENTES ══════ -->
        <div class="tab-pane fade" id="pac-pane" role="tabpanel">
            <p class="tab-section-desc"><i class="bi bi-info-circle me-1"></i>Banners exclusivos que ven los pacientes al entrar al sistema (independiente del carrusel principal).</p>

    <div class="row g-3">
        <!-- Vista previa + config -->
        <div class="col-12">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-eye-fill text-warning"></i>
                        <strong style="color:#b45309;">Vista previa · Carrusel Pacientes</strong>
                        <span class="badge bg-warning text-dark ms-1" style="font-size:.72rem;">Solo pacientes ven esto</span>
                    </div>
                    <?php
                    $prevPac = array_values(array_filter($pacBanners, fn($b) => $b['activo']));
                    ?>
                    <?php if (empty($prevPac)): ?>
                    <div class="prev-carousel-wrap d-flex align-items-center justify-content-center" style="height:160px;background:#e9ecef;border-radius:14px;">
                        <div class="text-center text-muted">
                            <i class="bi bi-image" style="font-size:2rem;"></i>
                            <p class="mt-2 mb-0 small">Sin banners activos para pacientes.</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="prev-carousel-wrap">
                        <div id="previewPacCarousel" class="carousel slide prev-carousel" data-bs-ride="carousel" data-bs-interval="<?= $pacCarouselInterval; ?>">
                            <?php if (count($prevPac) > 1): ?>
                            <div class="carousel-indicators">
                                <?php foreach ($prevPac as $pi => $ps): ?>
                                <button type="button" data-bs-target="#previewPacCarousel" data-bs-slide-to="<?= $pi; ?>"
                                    <?= $pi===0 ? 'class="active" aria-current="true"' : ''; ?>></button>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <div class="carousel-inner">
                                <?php foreach ($prevPac as $pi => $ps):
                                    $imgSrc = $ps['imagen'] && file_exists(__DIR__.'/'.$ps['imagen']) ? $ps['imagen'] : null;
                                ?>
                                <div class="carousel-item <?= $pi===0?'active':''; ?>">
                                    <?php if ($imgSrc): ?>
                                    <div class="slide-bg" style="background-image:url('<?= e($imgSrc); ?>');"></div>
                                    <?php else: ?>
                                    <div class="slide-bg" style="background:<?= e($ps['bg_color']??'#198754'); ?>;"></div>
                                    <?php endif; ?>
                                    <div class="slide-overlay"></div>
                                    <div class="slide-content">
                                        <div class="slide-title"><?= e($ps['titulo']); ?></div>
                                        <?php if ($ps['subtitulo']): ?><div class="slide-sub"><?= e(mb_strimwidth($ps['subtitulo'],0,80,'...')); ?></div><?php endif; ?>
                                        <?php if ($ps['btn_texto']): ?><span class="slide-btn"><?= e($ps['btn_texto']); ?></span><?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($prevPac) > 1): ?>
                            <button class="carousel-control-prev" type="button" data-bs-target="#previewPacCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
                            <button class="carousel-control-next" type="button" data-bs-target="#previewPacCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-4">
                    <div class="config-box h-100">
                        <h6 class="fw-700 mb-3" style="color:#b45309;"><i class="bi bi-sliders me-2"></i>Configuración</h6>
                        <form method="post">
                            <input type="hidden" name="action" value="save_pac_config">
                            <div class="mb-3">
                                <label class="form-label fw-600" style="font-size:.9rem;">Tiempo entre slides</label>
                                <div class="input-group">
                                    <input type="number" name="pac_carousel_interval" class="form-control"
                                        value="<?= $pacCarouselInterval; ?>" min="1000" max="30000" step="500" required>
                                    <span class="input-group-text">ms</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php foreach ([3000=>'3s',5000=>'5s',7000=>'7s',10000=>'10s'] as $ms=>$label): ?>
                                    <button type="button" onclick="document.querySelector('[name=pac_carousel_interval]').value=<?= $ms; ?>"
                                        class="btn btn-sm <?= $pacCarouselInterval===$ms ? 'btn-warning' : 'btn-outline-secondary'; ?>"><?= $label; ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-warning w-100 btn-sm text-dark fw-bold">
                                <i class="bi bi-save me-1"></i>Guardar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista pac_banners -->
        <div class="col-lg-7">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="mb-0 fw-700" style="color:#b45309;">
                    <i class="bi bi-collection me-2"></i>Banners actuales (pacientes)
                    <span class="badge bg-warning text-dark ms-2"><?= count($pacBanners); ?></span>
                </h5>
                <a href="#formAddPac" class="btn btn-warning btn-sm text-dark fw-bold">
                    <i class="bi bi-plus-lg me-1"></i>Nuevo banner
                </a>
            </div>

            <?php if (empty($pacBanners)): ?>
            <div class="banner-card">
                <div class="empty-state">
                    <i class="bi bi-image-alt"></i>
                    <p class="mb-0 fw-600">Sin banners para pacientes</p>
                    <p class="text-muted small">El carrusel del dashboard no aparecerá si no hay banners activos.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="row g-3">
                <?php foreach ($pacBanners as $b): ?>
                <div class="col-12">
                    <div class="banner-card">
                        <div class="row g-0">
                            <div class="col-md-4">
                                <div class="banner-preview" style="<?= $b['imagen'] ? '' : 'background:'.e($b['bg_color']).';'; ?>">
                                    <?php if ($b['imagen'] && file_exists(__DIR__ . '/' . $b['imagen'])): ?>
                                        <img src="<?= e($b['imagen']); ?>" alt="banner">
                                    <?php else: ?>
                                        <div style="background:<?= e($b['bg_color']); ?>;position:absolute;inset:0;"></div>
                                    <?php endif; ?>
                                    <div class="overlay">
                                        <h5><?= e($b['titulo'] ?: 'Sin título'); ?></h5>
                                        <?php if ($b['subtitulo']): ?><small><?= e(mb_strimwidth($b['subtitulo'],0,60,'…')); ?></small><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="p-3">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="status-dot <?= $b['activo'] ? 'on' : 'off'; ?>"></span>
                                        <span style="font-weight:700;color:#b45309;"><?= e($b['titulo'] ?: 'Sin título'); ?></span>
                                        <span class="order-badge ms-auto" style="background:#fff3e0;color:#b45309;">Orden: <?= (int)$b['orden']; ?></span>
                                    </div>
                                    <?php if ($b['subtitulo']): ?>
                                    <p class="text-muted small mb-2"><?= e(mb_strimwidth($b['subtitulo'],0,100,'…')); ?></p>
                                    <?php endif; ?>
                                    <div class="d-flex gap-2 mt-3 flex-wrap">
                                        <a href="?edit_pac=<?= $b['id']; ?>#formEditPac" class="btn btn-outline-warning btn-sm">
                                            <i class="bi bi-pencil me-1"></i>Editar
                                        </a>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_pac">
                                            <input type="hidden" name="id" value="<?= $b['id']; ?>">
                                            <button class="btn btn-sm <?= $b['activo'] ? 'btn-outline-secondary' : 'btn-outline-success'; ?>">
                                                <i class="bi bi-<?= $b['activo'] ? 'eye-slash' : 'eye'; ?> me-1"></i>
                                                <?= $b['activo'] ? 'Ocultar' : 'Mostrar'; ?>
                                            </button>
                                        </form>
                                        <a href="?delete_pac=<?= $b['id']; ?>"
                                           onclick="return confirm('¿Eliminar este banner de paciente?');"
                                           class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-trash me-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Formularios pac_banners -->
        <div class="col-lg-5">
            <?php if ($editPac): ?>
            <div class="form-section mb-4" id="formEditPac" style="border-color:#ffc107;">
                <h5><i class="bi bi-pencil-square me-2 text-warning"></i>Editar Banner Paciente</h5>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit_pac">
                    <input type="hidden" name="id" value="<?= $editPac['id']; ?>">
                    <input type="hidden" name="img_actual" value="<?= e($editPac['imagen'] ?? ''); ?>">
                    <div class="mb-3">
                        <label class="form-label fw-600">Título <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" class="form-control" value="<?= e($editPac['titulo']); ?>" required maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Subtítulo</label>
                        <textarea name="subtitulo" class="form-control" rows="2" maxlength="400"><?= e($editPac['subtitulo'] ?? ''); ?></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label fw-600">Texto botón</label>
                            <input type="text" name="btn_texto" class="form-control" value="<?= e($editPac['btn_texto'] ?? ''); ?>" maxlength="100">
                        </div>
                        <div class="col">
                            <label class="form-label fw-600">Enlace botón</label>
                            <input type="text" name="btn_link" class="form-control" value="<?= e($editPac['btn_link'] ?? ''); ?>" maxlength="400">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label fw-600">Color fondo</label>
                            <input type="color" name="bg_color" class="form-control form-control-color" value="<?= e($editPac['bg_color'] ?? '#198754'); ?>" style="width:50px;height:38px;">
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-600">Orden</label>
                            <input type="number" name="orden" class="form-control" value="<?= (int)$editPac['orden']; ?>" min="0" max="99">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Cambiar imagen</label>
                        <?php if ($editPac['imagen'] && file_exists(__DIR__ . '/' . $editPac['imagen'])): ?>
                        <div class="mb-2"><img src="<?= e($editPac['imagen']); ?>" class="preview-img-thumb" alt="actual"></div>
                        <?php endif; ?>
                        <input type="file" name="imagen_pac" class="form-control" accept="image/*" onchange="previewImg(this,'prevEditPac')">
                        <img id="prevEditPac" class="preview-img-thumb mt-2 d-none" alt="preview">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-warning flex-fill text-dark fw-bold"><i class="bi bi-save me-1"></i>Guardar</button>
                        <a href="edicioninicio.php#pac" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="form-section" id="formAddPac" style="border-color:#ffc107;">
                <h5><i class="bi bi-plus-circle me-2 text-warning"></i>Agregar Banner para Pacientes</h5>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_pac">
                    <div class="mb-3">
                        <label class="form-label fw-600">Título <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" class="form-control" required maxlength="200" placeholder="ej: ¡Bienvenid@ de vuelta!">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Subtítulo</label>
                        <textarea name="subtitulo" class="form-control" rows="2" maxlength="400" placeholder="Mensaje de bienvenida o motivación"></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label fw-600">Texto botón</label>
                            <input type="text" name="btn_texto" class="form-control" maxlength="100" placeholder="ej: Ver mis citas">
                        </div>
                        <div class="col">
                            <label class="form-label fw-600">Enlace botón</label>
                            <input type="text" name="btn_link" class="form-control" maxlength="400" placeholder="ej: Disponibilidad_citas.php">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label fw-600">Color fondo</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" name="bg_color" class="form-control form-control-color" value="#198754" style="width:50px;height:38px;">
                                <span class="text-muted small">Cuando no hay imagen</span>
                            </div>
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-600">Orden</label>
                            <input type="number" name="orden" class="form-control" value="<?= count($pacBanners); ?>" min="0" max="99">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Imagen del banner</label>
                        <input type="file" name="imagen_pac" class="form-control" accept="image/*" onchange="previewImg(this,'prevAddPac')">
                        <img id="prevAddPac" class="preview-img-thumb mt-2 d-none" alt="preview">
                        <div class="form-text">JPG, PNG, WebP, AVIF. Recomendado: 1400×300px.</div>
                    </div>
                    <button type="submit" class="btn btn-warning w-100 text-dark fw-bold">
                        <i class="bi bi-plus-lg me-1"></i>Agregar Banner Paciente
                    </button>
                </form>
            </div>

            <div class="mt-3 p-3 rounded-3" style="background:#fffbec;border:1px solid #ffe082;">
                <h6 style="color:#b45309;" class="mb-2"><i class="bi bi-info-circle me-1"></i>Diferencia con el carrusel principal</h6>
                <ul class="small text-muted mb-0 ps-3">
                    <li>El <strong>carrusel principal</strong> (naranja) se muestra en la página <em>de acceso público</em> (index.php).</li>
                    <li>El <strong>carrusel de pacientes</strong> (amarillo) se muestra solo cuando el paciente ya inició sesión.</li>
                    <li>Ambos carruseles son completamente independientes y tienen sus propios banners.</li>
                    <li>Imagen recomendada: <strong>1400 × 300 px</strong> (más angosto, dentro del dashboard).</li>
                </ul>
            </div>
        </div>
    </div>
        </div><!-- end pac-pane -->

        <!-- ══════ TAB 3: TARJETAS DE SERVICIOS ══════ -->
        <div class="tab-pane fade" id="tarjetas-pane" role="tabpanel">
            <p class="tab-section-desc"><i class="bi bi-info-circle me-1"></i>Gestiona las tarjetas que se muestran en la sección «Nuestros Servicios» de la página principal.</p>

    <?php
    $iconos = ['fa-utensils','fa-heartbeat','fa-weight','fa-running','fa-baby','fa-users','fa-apple-alt','fa-carrot','fa-seedling','fa-dumbbell','fa-star','fa-leaf','fa-stethoscope','fa-hand-holding-heart','fa-brain','fa-notes-medical'];
    ?>

    <div class="row g-3">
        <!-- Lista de tarjetas -->
        <div class="col-lg-7">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="mb-0 fw-700" style="color:#0d5132;">
                    <i class="bi bi-collection-fill me-2 text-primary"></i>Tarjetas actuales
                    <span class="badge bg-primary ms-2"><?= count($tarjetas); ?></span>
                </h5>
                <a href="#formAddTarjeta" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Nueva tarjeta
                </a>
            </div>

            <?php if (empty($tarjetas)): ?>
            <div class="tarjeta-admin-card">
                <div class="empty-state">
                    <i class="bi bi-grid-3x3-gap"></i>
                    <p class="mb-0 fw-600">No hay tarjetas configuradas</p>
                    <p class="text-muted small">Se mostrarán las tarjetas predeterminadas. Agrega tarjetas personalizadas abajo.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="row g-3">
                <?php foreach ($tarjetas as $t): ?>
                <div class="col-md-6">
                    <div class="tarjeta-admin-card">
                        <?php if ($t['imagen'] && file_exists(__DIR__ . '/' . $t['imagen'])): ?>
                        <div class="tarjeta-admin-img">
                            <img src="<?= e($t['imagen']); ?>" alt="<?= e($t['titulo']); ?>">
                        </div>
                        <?php endif; ?>
                        <div class="p-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <?php if (!$t['imagen'] || !file_exists(__DIR__ . '/' . $t['imagen'])): ?>
                                <div class="tarjeta-admin-icon flex-shrink-0">
                                    <i class="fas <?= e($t['icono'] ?: 'fa-star'); ?>"></i>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="status-dot <?= $t['activo'] ? 'on' : 'off'; ?>"></span>
                                        <strong style="color:#0d5132;"><?= e($t['titulo'] ?: 'Sin título'); ?></strong>
                                    </div>
                                    <span class="order-badge">Orden: <?= (int)$t['orden']; ?></span>
                                </div>
                            </div>
                            <?php if ($t['descripcion']): ?>
                            <p class="text-muted small mb-2"><?= e(mb_strimwidth($t['descripcion'],0,80,'…')); ?></p>
                            <?php endif; ?>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="?edit_tarjeta=<?= $t['id']; ?>#formEditTarjeta" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil me-1"></i>Editar
                                </a>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_tarjeta">
                                    <input type="hidden" name="id" value="<?= $t['id']; ?>">
                                    <button class="btn btn-sm <?= $t['activo'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>">
                                        <i class="bi bi-<?= $t['activo'] ? 'eye-slash' : 'eye'; ?> me-1"></i>
                                        <?= $t['activo'] ? 'Ocultar' : 'Mostrar'; ?>
                                    </button>
                                </form>
                                <a href="?delete_tarjeta=<?= $t['id']; ?>"
                                   onclick="return confirm('¿Eliminar esta tarjeta?');"
                                   class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-trash me-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Formularios de tarjetas -->
        <div class="col-lg-5">
            <?php if ($editTarjeta): ?>
            <div class="form-section mb-4" id="formEditTarjeta">
                <h5><i class="bi bi-pencil-square me-2 text-primary"></i>Editar Tarjeta</h5>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit_tarjeta">
                    <input type="hidden" name="id" value="<?= $editTarjeta['id']; ?>">
                    <input type="hidden" name="img_actual" value="<?= e($editTarjeta['imagen'] ?? ''); ?>">
                    <div class="mb-3">
                        <label class="form-label fw-600">Título <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" class="form-control" value="<?= e($editTarjeta['titulo']); ?>" required maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="2" maxlength="500"><?= e($editTarjeta['descripcion'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Ícono FontAwesome</label>
                        <input type="text" name="icono" class="form-control" value="<?= e($editTarjeta['icono'] ?? 'fa-star'); ?>" placeholder="ej: fa-utensils" id="iconInputEdit">
                        <div class="form-text">Se usa si no hay imagen. Ej: fa-utensils, fa-heartbeat</div>
                        <div class="icon-picker-grid mt-2">
                            <?php foreach ($iconos as $ico): ?>
                            <button type="button" class="icon-picker-btn <?= ($editTarjeta['icono'] ?? '')===$ico ? 'active' : ''; ?>"
                                onclick="document.getElementById('iconInputEdit').value='<?= $ico; ?>'; this.closest('.icon-picker-grid').querySelectorAll('.icon-picker-btn').forEach(b=>b.classList.remove('active')); this.classList.add('active');"
                                title="<?= $ico; ?>"><i class="fas <?= $ico; ?>"></i></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Enlace (opcional)</label>
                        <input type="text" name="enlace" class="form-control" value="<?= e($editTarjeta['enlace'] ?? ''); ?>" maxlength="400" placeholder="ej: #servicios">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Orden</label>
                        <input type="number" name="orden" class="form-control" value="<?= (int)$editTarjeta['orden']; ?>" min="0" max="99">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Imagen (opcional)</label>
                        <?php if ($editTarjeta['imagen'] && file_exists(__DIR__ . '/' . $editTarjeta['imagen'])): ?>
                        <div class="mb-2"><img src="<?= e($editTarjeta['imagen']); ?>" class="preview-img-thumb" alt="actual"></div>
                        <?php endif; ?>
                        <input type="file" name="imagen_tarjeta" class="form-control" accept="image/*" onchange="previewImg(this,'prevEditT')">
                        <img id="prevEditT" class="preview-img-thumb mt-2 d-none" alt="preview">
                        <div class="form-text">Si se sube imagen, se muestra en vez del ícono.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-save me-1"></i>Guardar</button>
                        <a href="edicioninicio.php#tarjetas" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="form-section" id="formAddTarjeta">
                <h5><i class="bi bi-plus-circle me-2 text-primary"></i>Agregar Tarjeta</h5>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_tarjeta">
                    <div class="mb-3">
                        <label class="form-label fw-600">Título <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" class="form-control" required maxlength="200" placeholder="ej: Planes Alimenticios">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="2" maxlength="500" placeholder="Breve descripción del servicio"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Ícono FontAwesome</label>
                        <input type="text" name="icono" class="form-control" value="fa-star" placeholder="ej: fa-utensils" id="iconInputAdd">
                        <div class="form-text">Se usa si no hay imagen. Ej: fa-utensils, fa-heartbeat</div>
                        <div class="icon-picker-grid mt-2">
                            <?php foreach ($iconos as $ico): ?>
                            <button type="button" class="icon-picker-btn"
                                onclick="document.getElementById('iconInputAdd').value='<?= $ico; ?>'; this.closest('.icon-picker-grid').querySelectorAll('.icon-picker-btn').forEach(b=>b.classList.remove('active')); this.classList.add('active');"
                                title="<?= $ico; ?>"><i class="fas <?= $ico; ?>"></i></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Enlace (opcional)</label>
                        <input type="text" name="enlace" class="form-control" maxlength="400" placeholder="ej: #servicios o URL">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Orden</label>
                        <input type="number" name="orden" class="form-control" value="<?= count($tarjetas); ?>" min="0" max="99">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600">Imagen (opcional)</label>
                        <input type="file" name="imagen_tarjeta" class="form-control" accept="image/*" onchange="previewImg(this,'prevAddT')">
                        <img id="prevAddT" class="preview-img-thumb mt-2 d-none" alt="preview">
                        <div class="form-text">JPG, PNG, WebP, AVIF. Si la subes, se muestra en vez del ícono.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-lg me-1"></i>Agregar Tarjeta
                    </button>
                </form>
            </div>

            <div class="mt-3 p-3 rounded-3" style="background:#eef3ff;border:1px solid #bbcfff;">
                <h6 class="text-primary mb-2"><i class="bi bi-info-circle me-1"></i>Sobre las tarjetas</h6>
                <ul class="small text-muted mb-0 ps-3">
                    <li>Las tarjetas aparecen en la sección <em>Nuestros Servicios</em> de la página principal.</li>
                    <li>Si no hay tarjetas activas, se muestran las predeterminadas.</li>
                    <li>Puedes usar un <strong>ícono</strong> o una <strong>imagen</strong> (la imagen tiene prioridad).</li>
                    <li>El campo <em>Enlace</em> hace la tarjeta clickeable.</li>
                </ul>
            </div>
        </div>
    </div>
        </div><!-- end tarjetas-pane -->
    </div><!-- end tab-content -->

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewImg(input, previewId){
    const img = document.getElementById(previewId);
    if(input.files && input.files[0]){
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; img.classList.remove('d-none'); };
        reader.readAsDataURL(input.files[0]);
    }
}
// Auto-select tab based on context
(function(){
    <?php if ($editPac): ?>
    var autoTab = 'pac-tab';
    <?php elseif ($editTarjeta): ?>
    var autoTab = 'tarjetas-tab';
    <?php else: ?>
    var autoTab = null;
    <?php endif; ?>

    var hash = window.location.hash.replace('#','');
    if (hash === 'pac' || hash === 'formEditPac' || hash === 'formAddPac') autoTab = 'pac-tab';
    else if (hash === 'tarjetas' || hash === 'formEditTarjeta' || hash === 'formAddTarjeta') autoTab = 'tarjetas-tab';

    if (autoTab) {
        var tabEl = document.getElementById(autoTab);
        if (tabEl) new bootstrap.Tab(tabEl).show();
    }

    // Update URL hash on tab change
    document.querySelectorAll('#adminTabs button[data-bs-toggle="pill"]').forEach(function(btn){
        btn.addEventListener('shown.bs.tab', function(e){
            var id = e.target.getAttribute('data-bs-target').replace('-pane','').replace('#','');
            if (id === 'banners') history.replaceState(null,null,' ');
            else history.replaceState(null,null,'#'+id);
        });
    });
})();
</script>
<script src="assets/js/form-integrity.js"></script>
</body>
</html>
