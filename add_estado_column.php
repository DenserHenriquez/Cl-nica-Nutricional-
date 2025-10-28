<?php
require_once 'db_connection.php';

$sql = "ALTER TABLE pacientes ADD COLUMN estado ENUM('Activo', 'Inactivo') DEFAULT 'Activo' NOT NULL";

if ($conexion->query($sql) === TRUE) {
    echo 'Columna estado agregada exitosamente.';
} else {
    echo 'Error al agregar columna: ' . $conexion->error;
}

$conexion->close();
?>
