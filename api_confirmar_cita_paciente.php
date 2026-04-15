<?php
/**
 * api_confirmar_cita_paciente.php
 * Endpoint para confirmar citas desde el inicio del paciente
 * Solo permite que un paciente confirme sus propias citas
 */

session_start();

function respond_json($payload, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

set_error_handler(function($errno, $errstr) {
    respond_json(['ok' => false, 'error' => "Error interno: {$errstr}"], 500);
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        respond_json(['ok' => false, 'error' => 'Error interno de servidor.'], 500);
    }
});

// Verificar sesión activa
if (!isset($_SESSION['id_usuarios'])) {
    respond_json(['ok' => false, 'error' => 'No autenticado'], 401);
}

$userId = (int)$_SESSION['id_usuarios'];
$userRole = $_SESSION['rol'] ?? '';

// Solo pacientes pueden confirmar sus propias citas desde here
if ($userRole !== 'Paciente') {
    respond_json(['ok' => false, 'error' => 'Solo pacientes pueden usar este endpoint'], 403);
}

require_once __DIR__ . '/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(['ok' => false, 'error' => 'Método no permitido'], 405);
}

$citaId = (int)($_POST['id'] ?? 0);

if ($citaId <= 0) {
    respond_json(['ok' => false, 'error' => 'ID de cita inválido'], 400);
}

// Obtener la cita y verificar que pertenece al paciente
$qCheck = $conexion->prepare(
    "SELECT c.id, c.fecha, c.hora, c.nombre_completo, c.paciente_id, c.estado, c.medico_id,
            u.Correo_electronico, u.Nombre_completo AS usuario_nombre,
            m.Nombre_completo AS medico_nombre
     FROM citas c
     LEFT JOIN usuarios u ON u.id_usuarios = ?
     LEFT JOIN usuarios m ON m.id_usuarios = c.medico_id
     WHERE c.id = ? AND (c.paciente_id = ? OR c.nombre_completo = u.Nombre_completo)
     LIMIT 1"
);

if (!$qCheck) {
    respond_json(['ok' => false, 'error' => 'Error en la consulta'], 500);
}

$qCheck->bind_param('iii', $userId, $citaId, $userId);
$qCheck->execute();
$citaRow = $qCheck->get_result()->fetch_assoc();
$qCheck->close();

if (!$citaRow) {
    respond_json(['ok' => false, 'error' => 'La cita no existe o no es tuya'], 403);
}

// Verificar que la cita no esté ya confirmada
if ($citaRow['estado'] === 'confirmada') {
    respond_json(['ok' => false, 'error' => 'La cita ya estaba confirmada'], 409);
}

if ($citaRow['estado'] === 'cancelada') {
    respond_json(['ok' => false, 'error' => 'No se puede confirmar una cita cancelada'], 409);
}

// Actualizar la cita a confirmada
$qUpdate = $conexion->prepare("UPDATE citas SET estado = 'confirmada' WHERE id = ?");
if (!$qUpdate) {
    respond_json(['ok' => false, 'error' => 'Error al actualizar la cita'], 500);
}

$qUpdate->bind_param('i', $citaId);
$qUpdate->execute();
$updated = $qUpdate->affected_rows > 0;
$qUpdate->close();

if (!$updated) {
    respond_json(['ok' => false, 'error' => 'No se pudo actualizar la cita'], 500);
}

// Enviar correo de confirmación
$emailMsg = '';
$pacienteNombre = $citaRow['nombre_completo'] ?: ($citaRow['usuario_nombre'] ?: 'Paciente');
$pacienteEmail = $citaRow['Correo_electronico'] ?? '';
$medicoNombre = $citaRow['medico_nombre'] ?? 'Médico de la clínica';

if (!empty($pacienteEmail)) {
    try {
        $dt = new DateTime($citaRow['fecha'] . ' ' . $citaRow['hora'], new DateTimeZone('America/Mexico_City'));
        $fechaTxt = $dt->format('d/m/Y H:i');
    } catch (Exception $e) {
        $fechaTxt = $citaRow['fecha'] . ' ' . substr($citaRow['hora'], 0, 5);
    }

    require_once __DIR__ . '/email_config.php';
    $resultado = enviarCorreoConfirmacionCita(
        $pacienteEmail,
        $pacienteNombre,
        $fechaTxt,
        $medicoNombre
    );
    $emailMsg = $resultado['success']
        ? 'Correo enviado a ' . $pacienteEmail
        : 'Error al enviar correo: ' . $resultado['error'];
} else {
    $emailMsg = 'Paciente sin correo registrado.';
}

respond_json([
    'ok' => true,
    'email' => $emailMsg,
    'cita_id' => $citaId
]);
