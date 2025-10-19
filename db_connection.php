<?php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'clinica_nutricional';

$conexion = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conexion->connect_errno) {
    die('Error de conexión a la base de datos: ' . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4");
