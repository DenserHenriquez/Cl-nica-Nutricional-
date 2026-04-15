<?php
// api_citas_medico.php – Endpoint JSON para citas pendientes del médico autenticado
session_start();

function respond_json($payload, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

set_error_handler(function($errno, $errstr /* , $errfile, $errline */) {
    respond_json(['ok' => false, 'error' => "Error interno: {$errstr}"], 500);
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        respond_json(['ok' => false, 'error' => 'Error interno de servidor (fatal).'], 500);
    }
});

header('Content-Type: application/json; charset=utf-8');

// Verificar sesión activa
if (!isset($_SESSION['id_usuarios'])) {
    respond_json(['ok' => false, 'error' => 'No autenticado'], 401);
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
        if (!$stmt) {
            echo json_encode(['ok' => false, 'error' => 'Error en la consulta de actualización: ' . $conexion->error]);
            exit;
        }
        $stmt->bind_param('ii', $id, $medicoId);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();

        $emailMsg = '';
        if ($ok) {
            // Obtener datos de la cita y email del paciente
            $q = $conexion->prepare(
                "SELECT c.fecha, c.hora, c.nombre_completo, c.paciente_id,
                        COALESCE(u.Correo_electronico, pu.Correo_electronico) AS email,
                        COALESCE(p.id_usuarios, c.paciente_id) AS paciente_usuario_id,
                        m.Nombre_completo AS medico_nombre
                 FROM citas c
                 LEFT JOIN usuarios u ON u.id_usuarios = c.paciente_id
                 LEFT JOIN pacientes p ON p.id_pacientes = c.paciente_id
                 LEFT JOIN usuarios pu ON pu.id_usuarios = p.id_usuarios
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
                    $pacienteUsuarioId = (int)($rowC['paciente_usuario_id'] ?? 0);
                    $medicoNombre   = $rowC['medico_nombre'] ?? 'Médico de la clínica';

                    // Si paciente_id es id_pacientes, buscar el email real en usuarios
                    if (empty($pacienteEmail) && $pacienteUsuarioId > 0) {
                        $tmp = $conexion->prepare(
                            "SELECT Correo_electronico FROM usuarios WHERE id_usuarios = ? LIMIT 1"
                        );
                        if ($tmp) {
                            $tmp->bind_param('i', $pacienteUsuarioId);
                            $tmp->execute();
                            $urow = $tmp->get_result()->fetch_assoc();
                            $tmp->close();
                            if ($urow) {
                                $pacienteEmail = $urow['Correo_electronico'] ?? '';
                                if (!empty($pacienteEmail) && $rowC['paciente_id'] !== $pacienteUsuarioId) {
                                    $upd = $conexion->prepare("UPDATE citas SET paciente_id=? WHERE id=? AND medico_id=?");
                                    if ($upd) { $upd->bind_param('iii', $pacienteUsuarioId, $id, $medicoId); $upd->execute(); $upd->close(); }
                                }
                            }
                        }
                    }

                    // Fallback: buscar email por nombre si no hay paciente_id correcto
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

        if (!$ok) {
            echo json_encode(['ok' => false, 'error' => 'No se encontró la cita o no tienes permiso para modificarla.']);
            exit;
        }
        echo json_encode(['ok' => true, 'email' => $emailMsg]);
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

        $emailMsg = '';
        if ($ok) {
            // Obtener datos de la cita y email del paciente
            $q = $conexion->prepare(
                "SELECT c.fecha, c.hora, c.nombre_completo, c.paciente_id,
                        COALESCE(u.Correo_electronico, pu.Correo_electronico) AS email,
                        COALESCE(p.id_usuarios, c.paciente_id) AS paciente_usuario_id,
                        m.Nombre_completo AS medico_nombre
                 FROM citas c
                 LEFT JOIN usuarios u ON u.id_usuarios = c.paciente_id
                 LEFT JOIN pacientes p ON p.id_pacientes = c.paciente_id
                 LEFT JOIN usuarios pu ON pu.id_usuarios = p.id_usuarios
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
                    $pacienteUsuarioId = (int)($rowC['paciente_usuario_id'] ?? 0);
                    $medicoNombre   = $rowC['medico_nombre'] ?? 'Médico de la clínica';

                    // Si paciente_id es id_pacientes, buscar el email real en usuarios
                    if (empty($pacienteEmail) && $pacienteUsuarioId > 0) {
                        $tmp = $conexion->prepare(
                            "SELECT Correo_electronico FROM usuarios WHERE id_usuarios = ? LIMIT 1"
                        );
                        if ($tmp) {
                            $tmp->bind_param('i', $pacienteUsuarioId);
                            $tmp->execute();
                            $urow = $tmp->get_result()->fetch_assoc();
                            $tmp->close();
                            if ($urow) {
                                $pacienteEmail = $urow['Correo_electronico'] ?? '';
                                if (!empty($pacienteEmail) && $rowC['paciente_id'] !== $pacienteUsuarioId) {
                                    $upd = $conexion->prepare("UPDATE citas SET paciente_id=? WHERE id=? AND medico_id=?");
                                    if ($upd) { $upd->bind_param('iii', $pacienteUsuarioId, $id, $medicoId); $upd->execute(); $upd->close(); }
                                }
                            }
                        }
                    }

                    // Fallback: buscar email por nombre si no hay paciente_id correcto
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
                        $resultado = enviarCorreoCancelacionCita(
                            $pacienteEmail,
                            $pacienteNombre,
                            $fechaTxt,
                            $medicoNombre
                        );
                        $emailMsg = $resultado['success']
                            ? 'Correo de cancelación enviado a ' . $pacienteEmail
                            : 'Error al enviar correo: ' . $resultado['error'];
                    } else {
                        $emailMsg = 'Paciente sin correo registrado.';
                    }
                }
            }
        }
        echo json_encode(['ok' => $ok, 'email_msg' => $emailMsg]);
        exit;
    }
}

echo json_encode(['ok' => false, 'error' => 'Acción desconocida']);
