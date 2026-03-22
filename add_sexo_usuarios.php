<?php
require_once 'db_connection.php';

echo "<h2>Agregar columna Sexo a tabla usuarios</h2>";

$check = $conexion->query("SHOW COLUMNS FROM usuarios LIKE 'sexo'");
if ($check->num_rows > 0) {
    echo "<div class='alert alert-info'>Columna 'sexo' ya existe. ✅</div>";
    $conexion->close();
    exit;
}

$sql = "ALTER TABLE usuarios 
        ADD COLUMN sexo ENUM('M','F') NOT NULL DEFAULT 'M' AFTER Nombre_completo";

if ($conexion->query($sql) === TRUE) {
    echo "<div class='alert alert-success'>✅ Columna 'sexo' agregada exitosamente a tabla usuarios.</div>";
    echo "<p><strong>Detalles:</strong> ENUM('M','F') NOT NULL DEFAULT 'M'</p>";
    echo "<p>Usuarios existentes mantienen su DEFAULT 'M'.</p>";
} else {
    echo "<div class='alert alert-danger'>❌ Error: " . $conexion->error . "</div>";
}

$verify = $conexion->query("DESCRIBE usuarios");
if ($verify) {
    echo "<h3>Tabla usuarios actualizada:</h3>";
    echo "<table class='table table-striped'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Default</th></tr>";
    while ($row = $verify->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conexion->close();
echo "<p><a href='index.php' class='btn btn-primary'>Volver</a> <a href='test_connection.php' class='btn btn-secondary'>Test BD</a></p>";
?>
