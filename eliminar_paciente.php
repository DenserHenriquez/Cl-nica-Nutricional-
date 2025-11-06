<?php
<?php
require_once 'db_connection.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // Elimina el paciente por ID
    $sql = "DELETE FROM pacientes WHERE id_pacientes = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // Redirige con mensaje de éxito
        header("Location: Activar_desactivar_paciente.php?ok=Paciente eliminado correctamente");
        exit;
    } else {
        // Redirige con mensaje de error
        header("Location: Activar_desactivar_paciente.php?error=No se pudo eliminar el paciente");
        exit;
    }
} else {
    header("Location: Activar_desactivar_paciente.php?error=ID de paciente no especificado");
    exit;
}
?>