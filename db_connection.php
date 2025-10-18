<?php
// Conexión a la base de datos
// Ajusta estos valores si tu configuración es distinta
$DB_HOST = 'localhost'; // o 'localhost'
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'clinica_nutricional';

$conexion = new mysqli($SERVER, $USER, $PASS, $DB);

if ($conexion->connect_errno) {
    die('Error de conexión a la base de datos: ' . $conexion->connect_error);
}else{
        echo "conectado";
// Asegurar juego de caracteres correcto
}

