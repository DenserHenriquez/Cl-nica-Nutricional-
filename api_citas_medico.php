<?php
// api_citas_medico.php – Endpoint JSON para citas pendientes del médico autenticado
session_start();

header('Content-Type: application/json; charset=utf-8');

// Verificar sesión activa
if (!isset($_SESSION['id_usuarios'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

$role = $_SESSION['rol'] ?? '';
if ($role !== 'Medico' && $role !== 'Administrador') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
    exit;
}

require_once __DIR__ . '/db_connection.php';

$medicoId = (int)$_SESSION['id_usuarios'];
$action   = $_GET['action'] ?? ($_POST['action'] ?? 'pending');

// ── GET: obtener citas pendientes ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'pending') {
    $stmt = $conexion->prepare(
        "SELECT id, nombre_completo, fecha, hora, motivo, estado, creado_en
         FROM citas
         WHERE medico_id = ? AND estado = 'pendiente'
         ORDER BY fecha ASC, hora ASC
         LIMIT 50"
    );
    if (!$stmt) {
        echo json_encode(['ok' => false, 'error' => 'Error de base de datos']);
        exit;
    }
    $stmt->bind_param('i', $medicoId);
    $stmt->execute();
    $res   = $stmt->get_result();
    $citas = [];
    while ($row = $res->fetch_assoc()) {
        $citas[] = $row;
    }
    $stmt->close();
    echo json_encode(['ok' => true, 'citas' => $citas, 'count' => count($citas)]);
    exit;
}

// ── POST: confirmar o cancelar cita ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'ID inválido']);
        exit;
    }

    if ($action === 'confirmar') {
        $stmt = $conexion->prepare(
            "UPDATE citas SET estado = 'confirmada' WHERE id = ? AND medico_id = ?"
        );
        $stmt->bind_param('ii', $id, $medicoId);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        echo json_encode(['ok' => $ok]);
        exit;
    }

    if ($action === 'cancelar') {
        $stmt = $conexion->prepare(
            "UPDATE citas SET estado = 'cancelada' WHERE id = ? AND medico_id = ?"
        );
        $stmt->bind_param('ii', $id, $medicoId);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        echo json_encode(['ok' => $ok]);
        exit;
    }
}

echo json_encode(['ok' => false, 'error' => 'Acción desconocida']);
