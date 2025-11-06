<?php
// Configuración de conexión directa
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'clinica1';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Conexión exitosa a la base de datos '$DB_NAME' usando PDO.\n";

    // Verificar si la tabla 'usuarios' existe y tiene datos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Tabla 'usuarios' encontrada con " . $row['total'] . " registros.\n";

    // Mostrar algunos usuarios para verificar
    $stmt = $pdo->query("SELECT Usuario, Correo_electronico FROM usuarios LIMIT 5");
    while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Usuario: " . $user['Usuario'] . ", Correo: " . $user['Correo_electronico'] . "\n";
    }

} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage() . "\n";
}
?>
