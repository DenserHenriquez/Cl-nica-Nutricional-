<?php
// Exportar_Receta.php
// Genera y descarga un PDF de una receta específica

require_once __DIR__ . '/db_connection.php';
session_start();

// Verificar sesión de usuario
if (!isset($_SESSION['id_usuarios'])) {
    header('Location: index.php');
    exit;
}

$user_id = (int)$_SESSION['id_usuarios'];

// Obtener id del paciente desde la BD usando id_usuarios
$stmt = $conexion->prepare("SELECT id_pacientes FROM pacientes WHERE id_usuarios = ? LIMIT 1");
if (!$stmt) {
    die("Error preparing statement: " . $conexion->error);
}
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $paciente_id = (int)$row['id_pacientes'];
} else {
    header('Location: Menuprincipal.php?error=No eres un paciente registrado.');
    exit;
}
$stmt->close();

// Obtener ID de la receta desde GET
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: Gestion_Receta.php?error=ID de receta inválido.');
    exit;
}
$receta_id = (int)$_GET['id'];

// Obtener datos de la receta
$stmt = $conexion->prepare("SELECT nombre, ingredientes, porciones, instrucciones, nota_nutricional, foto_path, created_at FROM recetas WHERE id = ? AND id_pacientes = ? LIMIT 1");
$stmt->bind_param('ii', $receta_id, $paciente_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$receta = $result->fetch_assoc()) {
    header('Location: Gestion_Receta.php?error=Receta no encontrada.');
    exit;
}
$stmt->close();

// Generar PDF usando HTML básico (sin librerías externas)
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9\-_]/', '_', $receta['nombre']) . '.html"');

// Crear contenido HTML para el PDF
$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($receta['nombre'], ENT_QUOTES, 'UTF-8') . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #0d6efd; text-align: center; }
        h2 { color: #495057; border-bottom: 1px solid #dee2e6; padding-bottom: 5px; }
        p { line-height: 1.6; }
        .section { margin-bottom: 20px; }
        .footer { text-align: center; font-size: 12px; color: #6c757d; margin-top: 40px; }
    </style>
</head>
<body>
    <h1>' . htmlspecialchars($receta['nombre'], ENT_QUOTES, 'UTF-8') . '</h1>
    <div class="section">
        <h2>Ingredientes</h2>
        <p>' . nl2br(htmlspecialchars($receta['ingredientes'], ENT_QUOTES, 'UTF-8')) . '</p>
    </div>
    <div class="section">
        <h2>Porciones</h2>
        <p>' . ($receta['porciones'] ? htmlspecialchars($receta['porciones'], ENT_QUOTES, 'UTF-8') : 'N/A') . '</p>
    </div>
    <div class="section">
        <h2>Instrucciones</h2>
        <p>' . nl2br(htmlspecialchars($receta['instrucciones'] ?: 'N/A', ENT_QUOTES, 'UTF-8')) . '</p>
    </div>
    <div class="section">
        <h2>Nota Nutricional</h2>
        <p>' . nl2br(htmlspecialchars($receta['nota_nutricional'] ?: 'N/A', ENT_QUOTES, 'UTF-8')) . '</p>
    </div>
    ' . (!empty($receta['foto_path']) ? '<div class="section"><h2>Foto</h2><img src="' . htmlspecialchars($receta['foto_path'], ENT_QUOTES, 'UTF-8') . '" alt="Foto de receta" style="max-width: 300px; height: auto;" /></div>' : '') . '
    <div class="footer">
        <p>Generado el ' . date('d/m/Y H:i:s') . ' | Paciente ID: ' . $paciente_id . '</p>
    </div>
</body>
</html>
';

// Usar wkhtmltopdf o similar si disponible, de lo contrario, mostrar HTML como PDF básico
// Nota: Para un PDF real, necesitarías una librería como TCPDF, FPDF o DomPDF
// Por ahora, enviamos como HTML que el navegador puede convertir a PDF

echo $html;
exit;
?>
