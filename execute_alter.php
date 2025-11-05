<?php
include 'db_connection.php';

$sql = "
USE clinica1;

-- Drop existing foreign key and index
ALTER TABLE ejercicios DROP FOREIGN KEY ejercicios_ibfk_1;
ALTER TABLE ejercicios DROP INDEX id_pacientes;

-- Rename column id_pacientes to paciente_id
ALTER TABLE ejercicios CHANGE id_pacientes paciente_id INT(11) NOT NULL;

-- Add hora column
ALTER TABLE ejercicios ADD COLUMN hora TIME NOT NULL DEFAULT '00:00:00' AFTER tiempo;

-- Make notas NOT NULL
ALTER TABLE ejercicios MODIFY notas TEXT NOT NULL;

-- Add new foreign key
ALTER TABLE ejercicios ADD CONSTRAINT fk_ejercicios_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(id_pacientes) ON DELETE CASCADE;

-- Add index
ALTER TABLE ejercicios ADD INDEX idx_paciente_fecha (paciente_id, fecha);
";

try {
    $conexion->multi_query($sql);
    echo "SQL executed successfully.";
} catch (Exception $e) {
    echo "Error executing SQL: " . $e->getMessage();
}
?>
