<?php
$server = "localhost";
$user = "root";
$pass = "";
$db = "clinica";
$conexion = new mysqli($server, $user, $pass, $db);
if ($conexion->connect_errno) {
    die("Conexion Fallida". $conexion->connect_errno);
} 
?>