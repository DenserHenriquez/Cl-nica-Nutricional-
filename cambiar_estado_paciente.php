<?php
// cambiar_estado_paciente.php
require_once 'db_connection.php';

// Validar que se reciban parámetros por POST
if (isset($_POST['id']) && isset($_POST['estado'])) {
    $id_paciente = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
    $nuevo_estado = ($_POST['estado'] === 'Activo') ? 'Activo' : 'Inactivo';

    // Preparar consulta segura
    $sql = "UPDATE pacientes SET estado = ? WHERE id_paciente = ?";
    if ($stmt = $conexion->prepare($sql)) {
        $stmt->bind_param("si", $nuevo_estado, $id_paciente);
        if ($stmt->execute()) {
            // Respuesta exitosa
            echo json_encode(['status' => 'ok', 'estado' => $nuevo_estado]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Error al ejecutar la actualización']);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error al preparar la consulta']);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos']);
}

$conexion->close();
?>
