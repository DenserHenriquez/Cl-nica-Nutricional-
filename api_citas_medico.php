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

        $emailMsg = '';
        if ($ok) {
            // Obtener datos de la cita y email del paciente
            $q = $conexion->prepare(
                "SELECT c.fecha, c.hora, c.nombre_completo, c.paciente_id,
                        u.Correo_electronico AS email,
                        m.Nombre_completo AS medico_nombre
                 FROM citas c
                 LEFT JOIN usuarios u ON u.id_usuarios = c.paciente_id
                 LEFT JOIN usuarios m ON m.id_usuarios = ?
                 WHERE c.id = ? AND c.medico_id = ?
                 LIMIT 1"
            );
            if ($q) {
                $q->bind_param('iii', $medicoId, $id, $medicoId);
                $q->execute();
                $rowC = $q->get_result()->fetch_assoc();
                $q->close();

                if ($rowC) {
                    $pacienteNombre = $rowC['nombre_completo'] ?: 'Paciente';
                    $pacienteEmail  = $rowC['email'] ?? '';
                    $medicoNombre   = $rowC['medico_nombre'] ?? 'Médico de la clínica';

                    // Fallback: buscar email por nombre si no hay paciente_id
                    if (empty($pacienteEmail)) {
                        $tmp = $conexion->prepare(
                            "SELECT Correo_electronico, id_usuarios FROM usuarios WHERE Nombre_completo = ? LIMIT 1"
                        );
                        if ($tmp) {
                            $tmp->bind_param('s', $pacienteNombre);
                            $tmp->execute();
                            $urow = $tmp->get_result()->fetch_assoc();
                            $tmp->close();
                            if ($urow) {
                                $pacienteEmail = $urow['Correo_electronico'] ?? '';
                                if (empty($rowC['paciente_id']) && !empty($urow['id_usuarios'])) {
                                    $upd = $conexion->prepare("UPDATE citas SET paciente_id=? WHERE id=? AND medico_id=?");
                                    if ($upd) { $upd->bind_param('iii', $urow['id_usuarios'], $id, $medicoId); $upd->execute(); $upd->close(); }
                                }
                            }
                        }
                    }

                    if (!empty($pacienteEmail)) {
                        try {
                            $dt = new DateTime($rowC['fecha'] . ' ' . $rowC['hora'], new DateTimeZone('America/Mexico_City'));
                            $fechaTxt = $dt->format('d/m/Y H:i');
                        } catch (Exception $e) {
                            $fechaTxt = $rowC['fecha'] . ' ' . substr($rowC['hora'], 0, 5);
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
                }
            }
        }

        echo json_encode(['ok' => $ok, 'email' => $emailMsg]);
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
