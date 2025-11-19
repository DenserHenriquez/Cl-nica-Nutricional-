<?php
// cambiar_rol_usuario.php
// Endpoint para que solo los administradores puedan cambiar el rol de usuarios

session_start();
require_once 'db_connection.php';

// Verificar que el usuario esté autenticado y sea Administrador
if (!isset($_SESSION['id_usuarios']) || !isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Solo administradores pueden cambiar roles.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usuario = isset($_POST['id_usuarios']) ? (int)$_POST['id_usuarios'] : 0;
    $nuevo_rol = isset($_POST['rol']) ? trim($_POST['rol']) : '';

    // Validar datos
    if ($id_usuario <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de usuario inválido']);
        exit;
    }

    if (!in_array($nuevo_rol, ['Paciente', 'Medico'], true)) {
        echo json_encode(['success' => false, 'message' => 'Rol inválido. Solo se permite Paciente o Medico.']);
        exit;
    }

    // Verificar que la columna Rol exista
    $checkRol = $conexion->query("SHOW COLUMNS FROM usuarios LIKE 'Rol'");
    if ($checkRol->num_rows === 0) {
        // Intentar crear la columna
        if (!$conexion->query("ALTER TABLE usuarios ADD COLUMN Rol VARCHAR(20) NOT NULL DEFAULT 'Paciente'")) {
            echo json_encode(['success' => false, 'message' => 'Error al verificar estructura de base de datos']);
            exit;
        }
    }

    // Actualizar el rol del usuario
    $stmt = $conexion->prepare("UPDATE usuarios SET Rol = ? WHERE id_usuarios = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error al preparar consulta: ' . $conexion->error]);
        exit;
    }

    $stmt->bind_param('si', $nuevo_rol, $id_usuario);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Rol actualizado exitosamente a ' . $nuevo_rol]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se encontró el usuario o el rol ya era ' . $nuevo_rol]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar rol: ' . $stmt->error]);
    }

    $stmt->close();
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}

$conexion->close();
?>
