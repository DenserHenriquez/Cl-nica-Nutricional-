<?php
// db_connection.php
// Conexión a la base de datos MySQL/MariaDB

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'clinica';

$conexion = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conexion->connect_errno) {
    die('Error de conexión a la base de datos: ' . $conexion->connect_error);
}

// Establecer el juego de caracteres
if (!$conexion->set_charset('utf8mb4')) {
    // Si falla el charset, podrías registrar el error
}

?>
